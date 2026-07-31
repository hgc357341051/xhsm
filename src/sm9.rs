// SM9 标识基密码算法实现（Task 5）
//
// 基于 sm9 crate（纯 Rust，GM/T 0044-2016 / GB/T 38635）实现 SM9 标识基密码算法。
// sm9 crate 内部使用 sm9_core 双线性对运算，#![forbid(unsafe_code)]，无任何 C/openssl 依赖。
//
// 对外暴露为 Xhsm\Sm9 类，提供六个静态方法：
// generateMasterKeyPair / extractUserPrivateKey / encrypt / decrypt / sign / verify
//
// ===== 密钥格式说明 =====
// SM9 标准区分加密主密钥对与签名主密钥对，generateMasterKeyPair 返回四项：
//   - master_enc_private_key / master_enc_public_key：加密主密钥对
//   - master_sig_private_key / master_sig_public_key：签名主密钥对
// 密钥以 hex 编码的 PEM 字符串表示（hex(pem_utf8_bytes)），与 sm9 crate 的 PEM 接口对接。
//
// extractUserPrivateKey 第三参数 type（可选，默认 "enc"）：
//   - "enc"：抽取加密用户私钥，返回 hex(pem)
//   - "sig"：抽取签名用户私钥，返回捆绑格式 hex(uspk_pem):hex(mspk_pem)
//     （SM9 签名算法需主签名公钥参与，故捆绑返回）
//
// sign 方法接受捆绑格式的签名私钥，或通过可选第四参数单独传入主签名公钥。
//
// 数据/密文/签名均为 hex 编码字符串；decrypt 返回 UTF-8 原文字符串。
//
// 注意：因 sm9 crate 的密钥生成/抽取 API 为文件接口，内部使用临时文件中转，
// 临时文件在作用域结束时自动清理。

use crate::exception::{xhsm_exception_code, Exception};
use ext_php_rs::exception::PhpException;
use ext_php_rs::prelude::*;
use std::panic::{catch_unwind, AssertUnwindSafe};
use std::sync::atomic::{AtomicU64, Ordering};

// 错误码常量：从 Exception 类复用，供本模块各错误路径统一引用。
const ERR_INVALID_FORMAT: i32 = Exception::ERR_INVALID_FORMAT;
const ERR_INVALID_PARAM: i32 = Exception::ERR_INVALID_PARAM;
const ERR_DECODE: i32 = Exception::ERR_DECODE;
const ERR_INTERNAL: i32 = Exception::ERR_INTERNAL;

/// 全局临时文件计数器，保证多线程下文件名唯一。
static TEMP_COUNTER: AtomicU64 = AtomicU64::new(0);

/// SM9 标识基密码算法封装类。
///
/// 提供主密钥生成、用户私钥抽取、标识加解密、标识签名验签能力。
/// 基于 sm9 crate（纯 Rust，GM/T 0044-2016）。
#[php_class]
#[php(name = "Xhsm\\Sm9")]
#[derive(Default)]
pub struct Sm9;

#[php_impl]
impl Sm9 {
    /// 生成 SM9 主密钥对（加密 + 签名各一组）。
    ///
    /// 返回 PHP 关联数组，包含：
    /// - master_enc_private_key：加密主私钥（hex 编码 PEM）
    /// - master_enc_public_key：加密主公钥（hex 编码 PEM）
    /// - master_sig_private_key：签名主私钥（hex 编码 PEM）
    /// - master_sig_public_key：签名主公钥（hex 编码 PEM）
    #[php(name = "generateMasterKeyPair")]
    pub fn generate_master_key_pair() -> Result<Vec<(String, String)>, PhpException> {
        // 加密主密钥对
        let enc_msk = TempFile::new("enc_msk");
        let enc_mpk = TempFile::new("enc_mpk");
        run_or_panic("SM9 加密主密钥生成失败", || {
            ::sm9::Sm9::generate_random_master_private_key_to_pem(enc_msk.path());
            ::sm9::Sm9::generate_master_public_key_to_pem(enc_msk.path(), enc_mpk.path());
        })?;
        let enc_msk_pem = read_pem(enc_msk.path())?;
        let enc_mpk_pem = read_pem(enc_mpk.path())?;

        // 签名主密钥对
        let sig_msk = TempFile::new("sig_msk");
        let sig_mpk = TempFile::new("sig_mpk");
        run_or_panic("SM9 签名主密钥生成失败", || {
            ::sm9::Sm9::generate_random_master_private_key_to_pem(sig_msk.path());
            ::sm9::Sm9::generate_master_signature_public_key_to_pem(sig_msk.path(), sig_mpk.path());
        })?;
        let sig_msk_pem = read_pem(sig_msk.path())?;
        let sig_mpk_pem = read_pem(sig_mpk.path())?;

        Ok(vec![
            (
                "master_enc_private_key".to_string(),
                pem_to_hex(&enc_msk_pem),
            ),
            (
                "master_enc_public_key".to_string(),
                pem_to_hex(&enc_mpk_pem),
            ),
            (
                "master_sig_private_key".to_string(),
                pem_to_hex(&sig_msk_pem),
            ),
            (
                "master_sig_public_key".to_string(),
                pem_to_hex(&sig_mpk_pem),
            ),
        ])
    }

    /// 按标识抽取用户私钥。
    ///
    /// 参数：
    /// - master_private_key：主私钥（hex 编码 PEM，来自 generateMasterKeyPair）
    /// - id：用户标识字符串
    /// - key_type：密钥类型 "enc"（默认，加密用户私钥）/"sig"（签名用户私钥，捆绑主签名公钥）
    ///
    /// 返回：
    /// - "enc"：hex 编码的加密用户私钥 PEM
    /// - "sig"：捆绑格式 hex(uspk_pem):hex(mspk_pem)
    #[php(name = "extractUserPrivateKey")]
    #[php(optional = key_type)]
    pub fn extract_user_private_key(
        master_private_key: String,
        id: String,
        key_type: Option<String>,
    ) -> Result<String, PhpException> {
        let key_type = key_type.unwrap_or_else(|| "enc".to_string());
        let msk_pem = hex_to_pem(&master_private_key)?;
        let id_bytes = id.as_bytes();

        // sm9 crate 的密钥抽取 API 为文件接口，需写入临时文件中转
        let msk_file = TempFile::new("msk");
        std::fs::write(msk_file.path(), &msk_pem).map_err(|e| {
            xhsm_exception_code(ERR_INTERNAL, format!("写入临时主私钥文件失败: {}", e))
        })?;

        match key_type.to_lowercase().as_str() {
            "enc" => {
                let upk_file = TempFile::new("upk");
                run_or_panic("SM9 加密用户私钥抽取失败", || {
                    ::sm9::Sm9::generate_user_private_key_to_pem(
                        msk_file.path(),
                        id_bytes,
                        upk_file.path(),
                    );
                })?;
                let upk_pem = read_pem(upk_file.path())?;
                Ok(pem_to_hex(&upk_pem))
            }
            "sig" => {
                // 签名用户私钥需捆绑主签名公钥，因 sign 算法需要 Ppub_s 参与
                let uspk_file = TempFile::new("uspk");
                let mspk_file = TempFile::new("mspk");
                run_or_panic("SM9 签名用户私钥抽取失败", || {
                    ::sm9::Sm9::generate_user_signature_private_key_to_pem(
                        msk_file.path(),
                        id_bytes,
                        uspk_file.path(),
                    );
                    ::sm9::Sm9::generate_master_signature_public_key_to_pem(
                        msk_file.path(),
                        mspk_file.path(),
                    );
                })?;
                let uspk_pem = read_pem(uspk_file.path())?;
                let mspk_pem = read_pem(mspk_file.path())?;
                // 捆绑格式：hex(uspk_pem):hex(mspk_pem)
                Ok(format!(
                    "{}:{}",
                    pem_to_hex(&uspk_pem),
                    pem_to_hex(&mspk_pem)
                ))
            }
            _ => Err(xhsm_exception_code(
                ERR_INVALID_PARAM,
                format!("不支持的密钥类型: {}（支持 enc/sig）", key_type),
            )),
        }
    }

    /// SM9 标识加密。
    ///
    /// 参数：
    /// - master_public_key：加密主公钥（hex 编码 PEM）
    /// - id：接收方标识字符串
    /// - data：hex 编码的明文
    ///
    /// 返回：hex 编码的密文（C1||C3||C2 格式）
    #[php(name = "encrypt")]
    pub fn encrypt(
        master_public_key: String,
        id: String,
        data: String,
    ) -> Result<String, PhpException> {
        let mpk_pem = hex_to_pem(&master_public_key)?;
        let data_bytes = hex_decode_or_err(&data, "数据")?;
        let id_bytes = id.as_bytes();

        let ciphertext = run_or_panic("SM9 加密失败：主公钥无效或内部错误", || {
            ::sm9::Sm9::encrypt2(&mpk_pem, id_bytes, &data_bytes)
        })?;

        Ok(hex::encode(&ciphertext))
    }

    /// SM9 标识解密。
    ///
    /// 参数：
    /// - user_private_key：加密用户私钥（hex 编码 PEM，来自 extractUserPrivateKey type="enc"）
    /// - id：接收方标识字符串
    /// - data：hex 编码的密文
    ///
    /// 返回：UTF-8 原文字符串
    #[php(name = "decrypt")]
    pub fn decrypt(
        user_private_key: String,
        id: String,
        data: String,
    ) -> Result<String, PhpException> {
        let upk_pem = hex_to_pem(&user_private_key)?;
        let data_bytes = hex_decode_or_err(&data, "密文")?;
        let id_bytes = id.as_bytes();

        let plaintext = run_or_panic(
            "SM9 解密失败：用户私钥无效或内部错误",
            || ::sm9::Sm9::decrypt2(&upk_pem, id_bytes, data_bytes),
        )?
        .ok_or_else(|| xhsm_exception_code(ERR_DECODE, "SM9 解密失败：密文无效或 MAC 校验失败"))?;

        String::from_utf8(plaintext).map_err(|e| {
            xhsm_exception_code(
                ERR_DECODE,
                format!("SM9 解密结果不是有效的 UTF-8 字符串: {}", e),
            )
        })
    }

    /// SM9 标识签名。
    ///
    /// 参数：
    /// - user_private_key：签名用户私钥
    ///   - 捆绑格式（来自 extractUserPrivateKey type="sig"）：hex(uspk_pem):hex(mspk_pem)
    ///   - 或纯 hex(uspk_pem)，此时需提供第四参数 master_public_key
    /// - id：签名方标识字符串
    /// - data：hex 编码的待签名数据
    /// - master_public_key：可选，签名主公钥（hex 编码 PEM）；若 user_private_key 为捆绑格式则可省略
    ///
    /// 返回：hex 编码的签名（h(32字节)||s(65字节)，共 97 字节）
    #[php(name = "sign")]
    #[php(optional = master_public_key)]
    pub fn sign(
        user_private_key: String,
        id: String,
        data: String,
        master_public_key: Option<String>,
    ) -> Result<String, PhpException> {
        // 解析签名私钥与主签名公钥
        // 注意：SM9 签名算法不需要用户 ID（ID 已在 extractUserPrivateKey 时编码进用户私钥），
        // 仅验签需要 ID。保留 id 参数以保证 API 与 spec 一致。
        let (uspk_pem, mspk_pem) = parse_sig_key(&user_private_key, master_public_key)?;
        let data_bytes = hex_decode_or_err(&data, "数据")?;
        let _ = &id; // id 参数仅用于 API 一致性，签名算法本身不需要

        let sig = run_or_panic("SM9 签名失败：密钥无效或内部错误", || {
            ::sm9::Sm9::sign2(&mspk_pem, &uspk_pem, &data_bytes)
        })?;

        Ok(hex::encode(sig.to_vec()))
    }

    /// SM9 标识验签。
    ///
    /// 参数：
    /// - master_public_key：签名主公钥（hex 编码 PEM）
    /// - id：签名方标识字符串
    /// - data：hex 编码的原始数据
    /// - signature：hex 编码的签名
    ///
    /// 返回：bool，true 表示验签通过
    #[php(name = "verify")]
    pub fn verify(
        master_public_key: String,
        id: String,
        data: String,
        signature: String,
    ) -> Result<bool, PhpException> {
        let mspk_pem = hex_to_pem(&master_public_key)?;
        let data_bytes = hex_decode_or_err(&data, "数据")?;
        let sig_bytes = hex_decode_or_err(&signature, "签名")?;
        let id_bytes = id.as_bytes();

        let sig = ::sm9::Signature::from_slice(&sig_bytes)
            .map_err(|e| xhsm_exception_code(ERR_DECODE, format!("SM9 签名解析失败: {}", e)))?;

        let result = run_or_panic("SM9 验签失败：主公钥无效或内部错误", || {
            ::sm9::Sm9::verify2(&mspk_pem, id_bytes, &data_bytes, &sig)
        })?;

        Ok(result)
    }
}

// ============================== 辅助函数 ==============================

/// 临时文件守卫，Drop 时自动删除文件。
struct TempFile(std::path::PathBuf);

impl TempFile {
    /// 创建唯一的临时文件路径（不创建文件，仅生成路径）。
    fn new(suffix: &str) -> Self {
        let counter = TEMP_COUNTER.fetch_add(1, Ordering::SeqCst);
        let name = format!("xhsm_sm9_{}_{}_{}", std::process::id(), counter, suffix);
        TempFile(std::env::temp_dir().join(name))
    }

    fn path(&self) -> &std::path::Path {
        &self.0
    }
}

impl Drop for TempFile {
    fn drop(&mut self) {
        let _ = std::fs::remove_file(&self.0);
    }
}

/// 执行可能 panic 的闭包，将 panic 转换为 Xhsm\Exception（错误码 ERR_INTERNAL）。
///
/// sm9 crate 在密钥无效时使用 assert!/expect 直接 panic，
/// 此函数通过 catch_unwind 捕获 panic 并包装为携带 ERR_INTERNAL 错误码的异常。
fn run_or_panic<F, R>(msg: &str, f: F) -> Result<R, PhpException>
where
    F: FnOnce() -> R,
{
    catch_unwind(AssertUnwindSafe(f)).map_err(|_| xhsm_exception_code(ERR_INTERNAL, msg))
}

/// 读取文件内容为 PEM 字符串。
fn read_pem(path: &std::path::Path) -> Result<String, PhpException> {
    std::fs::read_to_string(path)
        .map_err(|e| xhsm_exception_code(ERR_INTERNAL, format!("读取临时密钥文件失败: {}", e)))
}

/// 将 PEM 字符串编码为 hex（用于 PHP 层密钥表示）。
fn pem_to_hex(pem: &str) -> String {
    hex::encode(pem.as_bytes())
}

/// 将 hex 解码为 PEM 字符串（PHP 层密钥 → 内部 PEM）。
///
/// hex 解码失败 → ERR_INVALID_FORMAT；UTF-8 还原失败 → ERR_DECODE。
fn hex_to_pem(hex_str: &str) -> Result<String, PhpException> {
    let bytes = hex::decode(hex_str).map_err(|e| {
        xhsm_exception_code(ERR_INVALID_FORMAT, format!("密钥 hex 解码失败: {}", e))
    })?;
    String::from_utf8(bytes).map_err(|e| {
        xhsm_exception_code(
            ERR_DECODE,
            format!("密钥 PEM 转换失败（非有效 UTF-8）: {}", e),
        )
    })
}

/// 解析签名私钥与主签名公钥。
///
/// 支持两种输入方式：
/// 1. user_private_key 为捆绑格式 hex(uspk):hex(mspk)，master_public_key 为 None
/// 2. user_private_key 为 hex(uspk)，master_public_key 为 Some(hex(mspk))
fn parse_sig_key(
    user_private_key: &str,
    master_public_key: Option<String>,
) -> Result<(String, String), PhpException> {
    if let Some(mpk_hex) = master_public_key {
        let uspk_pem = hex_to_pem(user_private_key)?;
        let mspk_pem = hex_to_pem(&mpk_hex)?;
        Ok((uspk_pem, mspk_pem))
    } else if let Some(idx) = user_private_key.find(':') {
        // 捆绑格式：hex(uspk_pem):hex(mspk_pem)
        let uspk_hex = &user_private_key[..idx];
        let mspk_hex = &user_private_key[idx + 1..];
        let uspk_pem = hex_to_pem(uspk_hex)?;
        let mspk_pem = hex_to_pem(mspk_hex)?;
        Ok((uspk_pem, mspk_pem))
    } else {
        Err(xhsm_exception_code(
            ERR_INVALID_PARAM,
            "SM9 签名私钥格式错误：需为捆绑格式 hex(uspk):hex(mspk)，或通过第四参数提供主签名公钥",
        ))
    }
}

/// hex 解码辅助函数，失败时返回 Xhsm\Exception 异常（错误码 ERR_INVALID_FORMAT）。
fn hex_decode_or_err(hex_str: &str, name: &str) -> Result<Vec<u8>, PhpException> {
    hex::decode(hex_str).map_err(|e| {
        xhsm_exception_code(ERR_INVALID_FORMAT, format!("{} hex 解码失败: {}", name, e))
    })
}
