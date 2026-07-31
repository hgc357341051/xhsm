// 可扩展签名版本体系实现（Task 6）
//
// 基于 SM2 签名能力构建可扩展的签名版本注册表，对外暴露 Xhsm\Signature 类。
// 通过版本配置（algorithm/encoding/output/user_id/description）组合出不同的签名输出格式，
// 支持内置版本（s2/s3/s4）与运行时自定义注册版本。
//
// ===== 设计说明 =====
// - 注册表使用 OnceLock<RwLock<HashMap>>，线程安全，首次访问时惰性初始化内置版本
// - 签名核心逻辑直接调用 smcrypto crate（与 Sm2 类独立，不改动已测试的 sm2.rs）
// - data 参数为原始字符串，内部直接对其字节签名（高层 API 语义），verify 严格对称
// - user_id 字段为保留字段：smcrypto 内部固定使用 "1234567812345678"，不支持自定义；
//   当前版本间真实差异通过 encoding（DER/RAW）与 output（hex/base64）体现，
//   未来切换支持自定义 user_id 的 SM2 实现后即可生效
// - DER↔RAW 转换、hex/base64 输出编解码、公钥 04 前缀处理等纯函数已抽到
//   crate::sign_util 共享模块，供本文件与 scenario.rs 复用
//
// ===== 内置版本 =====
// - s2：algorithm=SM2, encoding=DER,  output=hex,    description="经典 ASN.1 DER + hex 编码"
// - s3：algorithm=SM2, encoding=RAW,  output=hex,    description="原始 r||s + hex 编码"
// - s4：algorithm=SM2, encoding=DER,  output=base64, description="ASN.1 DER + base64 编码"

use crate::exception::{xhsm_exception_code, Exception};
use crate::sign_util::{decode_output, der_to_raw, encode_output, raw_to_der, strip_04_prefix};
use ext_php_rs::exception::PhpException;
use ext_php_rs::prelude::*;
use smcrypto::sm2;
use std::collections::HashMap;
use std::sync::{OnceLock, RwLock};

// 错误码常量：从 Exception 类复用，供本模块各错误路径统一引用。
const ERR_INVALID_PARAM: i32 = Exception::ERR_INVALID_PARAM;
const ERR_UNSUPPORTED: i32 = Exception::ERR_UNSUPPORTED;
const ERR_INTERNAL: i32 = Exception::ERR_INTERNAL;
const ERR_DECODE: i32 = Exception::ERR_DECODE;

/// 默认用户 ID（smcrypto 内部固定值，作为保留字段默认值）
const DEFAULT_USER_ID: &str = "1234567812345678";

/// 签名版本配置。
///
/// 每个版本定义签名的算法、编码、输出格式等组合，由注册表统一管理。
#[derive(Clone)]
struct SigVersion {
    /// 底层算法，目前仅支持 "SM2"
    algorithm: String,
    /// 签名编码 "DER"（ASN.1）/ "RAW"（r||s 各 32 字节，共 64 字节）
    encoding: String,
    /// 最终签名串编码 "hex" / "base64"
    output: String,
    /// 用户 ID，默认 "1234567812345678"（保留字段，当前由底层 smcrypto 固定）
    user_id: String,
    /// 版本描述
    description: String,
}

impl SigVersion {
    /// 按默认值构造版本配置（algorithm=SM2, encoding=DER, output=hex, user_id=默认）。
    fn new(description: &str) -> Self {
        Self {
            algorithm: "SM2".to_string(),
            encoding: "DER".to_string(),
            output: "hex".to_string(),
            user_id: DEFAULT_USER_ID.to_string(),
            description: description.to_string(),
        }
    }
}

/// 全局签名版本注册表（线程安全，惰性初始化）。
///
/// 首次访问时通过 `get_or_init` 注册内置版本 s2/s3/s4，
/// 后续 register/versions/describe/sign/verify 均通过该注册表读写。
static REGISTRY: OnceLock<RwLock<HashMap<String, SigVersion>>> = OnceLock::new();

/// 获取注册表引用，若未初始化则先注册内置版本 s2/s3/s4。
fn registry() -> &'static RwLock<HashMap<String, SigVersion>> {
    REGISTRY.get_or_init(|| {
        let mut map = HashMap::new();
        // s2：经典 ASN.1 DER + hex 编码
        let mut s2 = SigVersion::new("经典 ASN.1 DER + hex 编码");
        s2.encoding = "DER".to_string();
        s2.output = "hex".to_string();
        map.insert("s2".to_string(), s2);
        // s3：原始 r||s + hex 编码
        let mut s3 = SigVersion::new("原始 r||s + hex 编码");
        s3.encoding = "RAW".to_string();
        s3.output = "hex".to_string();
        map.insert("s3".to_string(), s3);
        // s4：ASN.1 DER + base64 编码
        let mut s4 = SigVersion::new("ASN.1 DER + base64 编码");
        s4.encoding = "DER".to_string();
        s4.output = "base64".to_string();
        map.insert("s4".to_string(), s4);
        RwLock::new(map)
    })
}

/// 可扩展签名版本体系封装类。
///
/// 提供按版本配置的签名/验签能力，以及版本注册表管理（注册、查询、描述）。
#[php_class]
#[php(name = "Xhsm\\Signature")]
#[derive(Default)]
pub struct Signature;

#[php_impl]
impl Signature {
    /// 按版本配置对原始数据签名。
    ///
    /// 参数：
    /// - version：版本名（如 s2/s3/s4 或自定义版本）
    /// - privateKey：hex 编码的 SM2 私钥
    /// - data：原始字符串（对其字节直接签名，非 hex）
    ///
    /// 返回：按版本 output 配置编码的签名串（hex 或 base64）
    pub fn sign(
        version: String,
        private_key: String,
        data: String,
    ) -> Result<String, PhpException> {
        let ver = get_version(&version)?;
        // 按版本 encoding 调用 SM2 签名，得到签名字节
        let sig_bytes = sm2_sign_internal(&private_key, data.as_bytes(), &ver.encoding)?;
        // 按版本 output 编码最终签名串
        encode_output(&sig_bytes, &ver.output)
    }

    /// 按版本配置验签，与 sign 严格对称。
    ///
    /// 参数：
    /// - version：版本名
    /// - publicKey：hex 编码的 SM2 公钥（可带或不带 "04" 前缀）
    /// - data：原始字符串（与签名时一致，对其字节验签）
    /// - signature：按版本 output 编码的签名串
    ///
    /// 返回：bool，true 表示验签通过
    pub fn verify(
        version: String,
        public_key: String,
        data: String,
        signature: String,
    ) -> Result<bool, PhpException> {
        let ver = get_version(&version)?;
        // 先按版本 output 解码签名串为字节
        let sig_bytes = decode_output(&signature, &ver.output)?;
        // 再按版本 encoding 验签
        sm2_verify_internal(&public_key, data.as_bytes(), &sig_bytes, &ver.encoding)
    }

    /// 注册自定义签名版本。
    ///
    /// 参数：
    /// - version：版本名（不能与已存在版本重名）
    /// - config：关联数组，可含字段：
    ///   - algorithm：底层算法，仅支持 "SM2"（默认 SM2）
    ///   - encoding：签名编码 "DER"/"RAW"（默认 DER）
    ///   - output：输出编码 "hex"/"base64"（默认 hex）
    ///   - user_id：用户 ID（默认 "1234567812345678"，当前为保留字段）
    ///   - description：版本描述（默认空串）
    ///
    /// 已存在的版本名会抛 Xhsm\Exception；algorithm 非 "SM2" 抛异常。
    pub fn register(version: String, config: HashMap<String, String>) -> Result<(), PhpException> {
        // 校验版本名非空
        if version.trim().is_empty() {
            return Err(xhsm_exception_code(ERR_INVALID_PARAM, "版本名不能为空"));
        }

        let mut ver = SigVersion::new("");
        // 解析 algorithm（默认 SM2，非 SM2 抛异常）
        let algorithm = config
            .get("algorithm")
            .map(|s| s.trim().to_string())
            .unwrap_or_else(|| "SM2".to_string());
        if algorithm.to_uppercase() != "SM2" {
            return Err(xhsm_exception_code(
                ERR_UNSUPPORTED,
                format!("不支持的算法: {}（当前仅支持 SM2）", algorithm),
            ));
        }
        ver.algorithm = "SM2".to_string();

        // 解析 encoding（默认 DER）
        let encoding = config
            .get("encoding")
            .map(|s| s.trim().to_uppercase())
            .unwrap_or_else(|| "DER".to_string());
        if encoding != "DER" && encoding != "RAW" {
            return Err(xhsm_exception_code(
                ERR_INVALID_PARAM,
                format!("不支持的签名编码: {}（支持 DER/RAW）", encoding),
            ));
        }
        ver.encoding = encoding;

        // 解析 output（默认 hex）
        let output = config
            .get("output")
            .map(|s| s.trim().to_lowercase())
            .unwrap_or_else(|| "hex".to_string());
        if output != "hex" && output != "base64" {
            return Err(xhsm_exception_code(
                ERR_INVALID_PARAM,
                format!("不支持的输出编码: {}（支持 hex/base64）", output),
            ));
        }
        ver.output = output;

        // 解析 user_id（默认固定值，保留字段）
        ver.user_id = config
            .get("user_id")
            .map(|s| s.to_string())
            .unwrap_or_else(|| DEFAULT_USER_ID.to_string());

        // 解析 description（默认空串）
        ver.description = config
            .get("description")
            .map(|s| s.to_string())
            .unwrap_or_default();

        // 写入注册表，重名则抛异常
        let mut map = registry()
            .write()
            .map_err(|e| xhsm_exception_code(ERR_INTERNAL, format!("注册表锁获取失败: {}", e)))?;
        if map.contains_key(&version) {
            return Err(xhsm_exception_code(
                ERR_INVALID_PARAM,
                format!("版本已存在: {}", version),
            ));
        }
        map.insert(version, ver);
        Ok(())
    }

    /// 返回已注册版本名列表。
    ///
    /// 返回索引数组，包含所有已注册版本名（含内置 s2/s3/s4 与自定义版本）。
    pub fn versions() -> Result<Vec<String>, PhpException> {
        let map = registry()
            .read()
            .map_err(|e| xhsm_exception_code(ERR_INTERNAL, format!("注册表锁获取失败: {}", e)))?;
        let mut names: Vec<String> = map.keys().cloned().collect();
        names.sort();
        Ok(names)
    }

    /// 返回指定版本的配置（关联数组），便于调试。
    ///
    /// 参数：
    /// - version：版本名
    ///
    /// 返回关联数组，含 algorithm/encoding/output/user_id/description 五个字段。
    pub fn describe(version: String) -> Result<Vec<(String, String)>, PhpException> {
        let ver = get_version(&version)?;
        Ok(vec![
            ("algorithm".to_string(), ver.algorithm.clone()),
            ("encoding".to_string(), ver.encoding.clone()),
            ("output".to_string(), ver.output.clone()),
            ("user_id".to_string(), ver.user_id.clone()),
            ("description".to_string(), ver.description.clone()),
        ])
    }
}

// ============================== 内部辅助函数 ==============================

/// 从注册表读取指定版本配置，不存在则抛异常。
fn get_version(version: &str) -> Result<SigVersion, PhpException> {
    let map = registry()
        .read()
        .map_err(|e| xhsm_exception_code(ERR_INTERNAL, format!("注册表锁获取失败: {}", e)))?;
    map.get(version).cloned().ok_or_else(|| {
        xhsm_exception_code(ERR_INVALID_PARAM, format!("未知的签名版本: {}", version))
    })
}

/// SM2 签名内部实现（直接调用 smcrypto，与 Sm2 类独立）。
///
/// 参数：
/// - private_key：hex 私钥
/// - data：待签名的原始字节
/// - encoding：签名编码 "DER"/"RAW"
///
/// 返回签名字节（DER 或 RAW 格式）。
fn sm2_sign_internal(
    private_key: &str,
    data: &[u8],
    encoding: &str,
) -> Result<Vec<u8>, PhpException> {
    let sign_ctx = sm2::Sign::new(private_key);
    // smcrypto 的 sign() 始终返回 ASN.1 DER 编码
    let der_sig = sign_ctx.sign(data);
    match encoding.to_uppercase().as_str() {
        "DER" => Ok(der_sig),
        "RAW" => der_to_raw(&der_sig)
            .map_err(|e| xhsm_exception_code(ERR_DECODE, format!("DER 转 RAW 签名失败: {}", e))),
        _ => Err(xhsm_exception_code(
            ERR_INVALID_PARAM,
            format!("不支持的签名编码: {}（支持 DER/RAW）", encoding),
        )),
    }
}

/// SM2 验签内部实现（直接调用 smcrypto，与 Sm2 类独立）。
///
/// 参数：
/// - public_key：hex 公钥（可带或不带 "04" 前缀）
/// - data：原始数据字节
/// - sig：签名字节（DER 或 RAW 格式）
/// - encoding：签名编码 "DER"/"RAW"
fn sm2_verify_internal(
    public_key: &str,
    data: &[u8],
    sig: &[u8],
    encoding: &str,
) -> Result<bool, PhpException> {
    let pk = strip_04_prefix(public_key);
    let verify_ctx = sm2::Verify::new(&pk);
    match encoding.to_uppercase().as_str() {
        "DER" => Ok(verify_ctx.verify(data, sig)),
        "RAW" => {
            let der_sig = raw_to_der(sig).map_err(|e| {
                xhsm_exception_code(ERR_DECODE, format!("RAW 转 DER 签名失败: {}", e))
            })?;
            Ok(verify_ctx.verify(data, &der_sig))
        }
        _ => Err(xhsm_exception_code(
            ERR_INVALID_PARAM,
            format!("不支持的签名编码: {}（支持 DER/RAW）", encoding),
        )),
    }
}
