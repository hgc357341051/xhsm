// 业务场景预设模块（Task 7）
//
// 在 Xhsm\Scenario\ 命名空间下提供四个业务场景预设类，根据业务标准预配置算法参数，
// 对外提供统一的 sign/verify/encrypt/decrypt/hash 五个静态方法。
//
// 数据约定（与 Sm2/Sm3 等基础类不同，更贴近应用层使用）：
// - 明文 data 为原始字符串（PHP 字符串字节）
// - 签名输出为字符串（按场景预配置的 encoding + output 组合）
// - 加密输出为 hex 字符串；解密输入为 hex 字符串，输出为原始字符串
// - hash 输出为 64 字符的 hex 字符串
//
// ===== 各场景预配置 =====
// - Finance（金融）：sign DER + hex（GB/T 32918 ASN.1 标准），encrypt C1C3C2 + hex
// - Payment（支付）：sign RAW + hex（主流支付 API 常用 raw 格式），encrypt C1C3C2 + hex
// - Government（政府）：sign DER + hex（政务 PKI 通常用 ASN.1 DER），encrypt C1C3C2 + hex
// - MiniProgram（小程序）：sign DER + base64（小程序常用 base64 传输），encrypt C1C3C2 + hex
//
// 注意：Finance 与 Government 配置相同（业务语义不同），代码结构保持清晰以便后续按
// 实际业务标准调整参数。
//
// ===== 实现要点 =====
// - #[php_impl] 标注的方法不能直接作为普通 Rust 函数调用，故 scenario.rs 内部直接
//   调用 smcrypto / sm3 crate，与 signature.rs 的做法一致
// - DER↔RAW 转换、hex/base64 输出编解码、公钥 04 前缀处理复用 crate::sign_util

use crate::exception::xhsm_exception;
use crate::sign_util::{
    decode_output, der_to_raw, encode_output, raw_to_der, strip_04_prefix,
};
use ext_php_rs::exception::PhpException;
use ext_php_rs::prelude::*;
use sm3::Sm3 as Sm3Hasher;
use smcrypto::sm2;

// ============================== 场景配置 ==============================

/// 单个场景的签名与加密参数配置。
///
/// 各场景通过 `const` 实例化此结构来表达业务标准差异，
/// 后续如需按实际业务标准调整，只需修改对应 `const` 即可。
struct ScenarioConfig {
    /// 签名编码 "DER"（ASN.1）/ "RAW"（r||s 64 字节）
    encoding: &'static str,
    /// 签名输出编码 "hex" / "base64"
    output: &'static str,
    /// 场景描述（业务标准说明）
    description: &'static str,
}

/// 金融场景：DER + hex（GB/T 32918 ASN.1 标准）。
const FINANCE_CONFIG: ScenarioConfig = ScenarioConfig {
    encoding: "DER",
    output: "hex",
    description: "金融行业标准（GB/T 32918 + ASN.1 DER）",
};

/// 支付场景：RAW + hex（主流支付 API 常用 raw r||s 格式）。
const PAYMENT_CONFIG: ScenarioConfig = ScenarioConfig {
    encoding: "RAW",
    output: "hex",
    description: "支付行业常用格式（RAW r||s）",
};

/// 政务场景：DER + hex（政务 PKI 通常用 ASN.1 DER）。
const GOVERNMENT_CONFIG: ScenarioConfig = ScenarioConfig {
    encoding: "DER",
    output: "hex",
    description: "政务 PKI 标准（ASN.1 DER）",
};

/// 小程序场景：DER + base64（小程序平台常用 base64 传输）。
const MINIPROGRAM_CONFIG: ScenarioConfig = ScenarioConfig {
    encoding: "DER",
    output: "base64",
    description: "小程序平台传输格式（DER + base64）",
};

// ============================== 内部辅助函数 ==============================

/// 按场景配置进行 SM2 签名，返回签名串。
///
/// 参数：
/// - private_key：hex 私钥
/// - data：待签名的原始字节
/// - cfg：场景配置（encoding + output）
fn scenario_sign(
    private_key: &str,
    data: &[u8],
    cfg: &ScenarioConfig,
) -> Result<String, PhpException> {
    let sign_ctx = sm2::Sign::new(private_key);
    // smcrypto 的 sign() 始终返回 ASN.1 DER 编码
    let der_sig = sign_ctx.sign(data);
    let sig_bytes = match cfg.encoding.to_uppercase().as_str() {
        "DER" => der_sig,
        "RAW" => der_to_raw(&der_sig)
            .map_err(|e| xhsm_exception(format!("DER 转 RAW 签名失败: {}", e)))?,
        _ => {
            return Err(xhsm_exception(format!(
                "不支持的签名编码: {}（支持 DER/RAW）",
                cfg.encoding
            )))
        }
    };
    encode_output(&sig_bytes, cfg.output)
}

/// 按场景配置进行 SM2 验签，与 sign 严格对称。
///
/// 参数：
/// - public_key：hex 公钥（可带或不带 "04" 前缀）
/// - data：原始数据字节
/// - signature：按场景 output 编码的签名串
/// - cfg：场景配置（encoding + output）
fn scenario_verify(
    public_key: &str,
    data: &[u8],
    signature: &str,
    cfg: &ScenarioConfig,
) -> Result<bool, PhpException> {
    // 先按场景 output 解码签名串为字节
    let sig_bytes = decode_output(signature, cfg.output)?;
    let pk = strip_04_prefix(public_key);
    let verify_ctx = sm2::Verify::new(&pk);
    let result = match cfg.encoding.to_uppercase().as_str() {
        "DER" => verify_ctx.verify(data, &sig_bytes),
        "RAW" => {
            let der_sig = raw_to_der(&sig_bytes)
                .map_err(|e| xhsm_exception(format!("RAW 转 DER 签名失败: {}", e)))?;
            verify_ctx.verify(data, &der_sig)
        }
        _ => {
            return Err(xhsm_exception(format!(
                "不支持的签名编码: {}（支持 DER/RAW）",
                cfg.encoding
            )))
        }
    };
    Ok(result)
}

/// SM2 加密（C1C3C2 模式），返回 hex 编码的密文。
///
/// 所有场景加密统一使用 C1C3C2（GB/T 32918.4 标准）+ hex 输出。
fn scenario_encrypt(public_key: &str, data: &[u8]) -> Result<String, PhpException> {
    let pk = strip_04_prefix(public_key);
    let enc_ctx = sm2::Encrypt::new(&pk);
    let ciphertext = enc_ctx.encrypt(data);
    Ok(hex::encode(&ciphertext))
}

/// SM2 解密（C1C3C2 模式）。
///
/// 参数：
/// - private_key：hex 私钥
/// - data：hex 编码的密文
///
/// 返回：还原后的原始字符串（UTF-8 字节）
fn scenario_decrypt(private_key: &str, data: &str) -> Result<String, PhpException> {
    let data_bytes = hex::decode(data)
        .map_err(|e| xhsm_exception(format!("密文 hex 解码失败: {}", e)))?;
    let dec_ctx = sm2::Decrypt::new(private_key);
    let plaintext = dec_ctx.decrypt(&data_bytes);
    // 将明文字节还原为字符串
    String::from_utf8(plaintext)
        .map_err(|e| xhsm_exception(format!("明文 UTF-8 解码失败: {}", e)))
}

/// SM3 摘要，返回 64 字符 hex 字符串。
fn scenario_hash(data: &[u8]) -> String {
    use sm3::Digest;
    let mut hasher = Sm3Hasher::new();
    hasher.update(data);
    hex::encode(hasher.finalize())
}

// ============================== 场景类定义 ==============================

/// 金融业务场景预设类。
///
/// 签名：DER + hex（GB/T 32918 ASN.1 标准）
/// 加密：SM2 C1C3C2 + hex（GB/T 32918.4 标准）
#[php_class]
#[php(name = "Xhsm\\Scenario\\Finance")]
#[derive(Default)]
pub struct Finance;

#[php_impl]
impl Finance {
    /// 按金融场景预配置签名（DER + hex）。
    ///
    /// 参数：
    /// - privateKey：hex 编码的 SM2 私钥
    /// - data：原始字符串（对其字节直接签名）
    ///
    /// 返回：hex 编码的 ASN.1 DER 签名串
    pub fn sign(private_key: String, data: String) -> Result<String, PhpException> {
        scenario_sign(&private_key, data.as_bytes(), &FINANCE_CONFIG)
    }

    /// 按金融场景预配置验签，与 sign 严格对称。
    pub fn verify(
        public_key: String,
        data: String,
        signature: String,
    ) -> Result<bool, PhpException> {
        scenario_verify(&public_key, data.as_bytes(), &signature, &FINANCE_CONFIG)
    }

    /// SM2 加密（C1C3C2 + hex）。
    ///
    /// 参数：
    /// - publicKey：hex 公钥（可带或不带 "04" 前缀）
    /// - data：原始字符串明文
    ///
    /// 返回：hex 编码的密文
    pub fn encrypt(public_key: String, data: String) -> Result<String, PhpException> {
        scenario_encrypt(&public_key, data.as_bytes())
    }

    /// SM2 解密（C1C3C2）。
    ///
    /// 参数：
    /// - privateKey：hex 私钥
    /// - data：hex 编码的密文
    ///
    /// 返回：还原后的原始字符串
    pub fn decrypt(private_key: String, data: String) -> Result<String, PhpException> {
        scenario_decrypt(&private_key, &data)
    }

    /// SM3 摘要（hex）。
    ///
    /// 参数 data 为原始字符串，返回 64 字符 hex 字符串。
    pub fn hash(data: String) -> Result<String, PhpException> {
        Ok(scenario_hash(data.as_bytes()))
    }

    /// 返回金融场景的业务标准描述。
    pub fn description() -> String {
        FINANCE_CONFIG.description.to_string()
    }
}

/// 支付业务场景预设类。
///
/// 签名：RAW(r||s) + hex（主流支付 API 常用 raw 格式）
/// 加密：SM2 C1C3C2 + hex
#[php_class]
#[php(name = "Xhsm\\Scenario\\Payment")]
#[derive(Default)]
pub struct Payment;

#[php_impl]
impl Payment {
    /// 按支付场景预配置签名（RAW + hex）。
    pub fn sign(private_key: String, data: String) -> Result<String, PhpException> {
        scenario_sign(&private_key, data.as_bytes(), &PAYMENT_CONFIG)
    }

    /// 按支付场景预配置验签，与 sign 严格对称。
    pub fn verify(
        public_key: String,
        data: String,
        signature: String,
    ) -> Result<bool, PhpException> {
        scenario_verify(&public_key, data.as_bytes(), &signature, &PAYMENT_CONFIG)
    }

    /// SM2 加密（C1C3C2 + hex）。
    pub fn encrypt(public_key: String, data: String) -> Result<String, PhpException> {
        scenario_encrypt(&public_key, data.as_bytes())
    }

    /// SM2 解密（C1C3C2）。
    pub fn decrypt(private_key: String, data: String) -> Result<String, PhpException> {
        scenario_decrypt(&private_key, &data)
    }

    /// SM3 摘要（hex）。
    pub fn hash(data: String) -> Result<String, PhpException> {
        Ok(scenario_hash(data.as_bytes()))
    }

    /// 返回支付场景的业务标准描述。
    pub fn description() -> String {
        PAYMENT_CONFIG.description.to_string()
    }
}

/// 政务业务场景预设类。
///
/// 签名：DER + hex（政务 PKI 通常用 ASN.1 DER）
/// 加密：SM2 C1C3C2 + hex
#[php_class]
#[php(name = "Xhsm\\Scenario\\Government")]
#[derive(Default)]
pub struct Government;

#[php_impl]
impl Government {
    /// 按政务场景预配置签名（DER + hex）。
    pub fn sign(private_key: String, data: String) -> Result<String, PhpException> {
        scenario_sign(&private_key, data.as_bytes(), &GOVERNMENT_CONFIG)
    }

    /// 按政务场景预配置验签，与 sign 严格对称。
    pub fn verify(
        public_key: String,
        data: String,
        signature: String,
    ) -> Result<bool, PhpException> {
        scenario_verify(&public_key, data.as_bytes(), &signature, &GOVERNMENT_CONFIG)
    }

    /// SM2 加密（C1C3C2 + hex）。
    pub fn encrypt(public_key: String, data: String) -> Result<String, PhpException> {
        scenario_encrypt(&public_key, data.as_bytes())
    }

    /// SM2 解密（C1C3C2）。
    pub fn decrypt(private_key: String, data: String) -> Result<String, PhpException> {
        scenario_decrypt(&private_key, &data)
    }

    /// SM3 摘要（hex）。
    pub fn hash(data: String) -> Result<String, PhpException> {
        Ok(scenario_hash(data.as_bytes()))
    }

    /// 返回政务场景的业务标准描述。
    pub fn description() -> String {
        GOVERNMENT_CONFIG.description.to_string()
    }
}

/// 小程序业务场景预设类。
///
/// 签名：DER + base64（小程序平台常用 base64 传输）
/// 加密：SM2 C1C3C2 + hex
#[php_class]
#[php(name = "Xhsm\\Scenario\\MiniProgram")]
#[derive(Default)]
pub struct MiniProgram;

#[php_impl]
impl MiniProgram {
    /// 按小程序场景预配置签名（DER + base64）。
    pub fn sign(private_key: String, data: String) -> Result<String, PhpException> {
        scenario_sign(&private_key, data.as_bytes(), &MINIPROGRAM_CONFIG)
    }

    /// 按小程序场景预配置验签，与 sign 严格对称。
    pub fn verify(
        public_key: String,
        data: String,
        signature: String,
    ) -> Result<bool, PhpException> {
        scenario_verify(&public_key, data.as_bytes(), &signature, &MINIPROGRAM_CONFIG)
    }

    /// SM2 加密（C1C3C2 + hex）。
    pub fn encrypt(public_key: String, data: String) -> Result<String, PhpException> {
        scenario_encrypt(&public_key, data.as_bytes())
    }

    /// SM2 解密（C1C3C2）。
    pub fn decrypt(private_key: String, data: String) -> Result<String, PhpException> {
        scenario_decrypt(&private_key, &data)
    }

    /// SM3 摘要（hex）。
    pub fn hash(data: String) -> Result<String, PhpException> {
        Ok(scenario_hash(data.as_bytes()))
    }

    /// 返回小程序场景的业务标准描述。
    pub fn description() -> String {
        MINIPROGRAM_CONFIG.description.to_string()
    }
}
