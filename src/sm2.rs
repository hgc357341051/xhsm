// SM2 非对称加密算法实现（Task 4）
//
// 基于 smcrypto crate 实现 SM2 密钥对生成、加解密（C1C3C2/C1C2C3/ASN.1）、签名验签。
// 对外暴露为 Xhsm\Sm2 类，提供五个静态方法：
// generateKeyPair / encrypt / decrypt / sign / verify
//
// 输入输出约定：
// - 密钥：hex 字符串（公钥可带或不带 "04" 前缀）
// - 数据/密文/签名：hex 编码字符串
// - generateKeyPair 返回 PHP 关联数组：["private_key" => hex, "public_key" => hex(带04前缀)]
//
// 签名格式说明：
// smcrypto 内部始终使用 ASN.1 DER 编码。
// - "DER" 模式：直接使用 smcrypto 的 DER 输出
// - "RAW" 模式：将 DER 转换为 r||s 原始拼接（各 32 字节，共 64 字节）

use crate::exception::{xhsm_exception_code, Exception};
use ext_php_rs::exception::PhpException;
use ext_php_rs::prelude::*;
use smcrypto::sm2;

// 错误码常量：从 Exception 类复用，供本模块各错误路径统一引用。
const ERR_INVALID_FORMAT: i32 = Exception::ERR_INVALID_FORMAT;
const ERR_INVALID_PARAM: i32 = Exception::ERR_INVALID_PARAM;
const ERR_DECODE: i32 = Exception::ERR_DECODE;

/// SM2 非对称加密算法封装类。
///
/// 提供密钥对生成、加解密（C1C3C2/C1C2C3/ASN.1）、签名验签（DER/RAW）能力。
#[php_class]
#[php(name = "Xhsm\\Sm2")]
#[derive(Default)]
pub struct Sm2;

#[php_impl]
impl Sm2 {
    /// 生成 SM2 密钥对。
    ///
    /// 返回 PHP 关联数组，包含：
    /// - private_key：64 hex 字符的私钥（32 字节）
    /// - public_key：130 hex 字符的公钥（64 字节 + "04" 非压缩前缀）
    #[php(name = "generateKeyPair")]
    pub fn generate_key_pair() -> Vec<(String, String)> {
        let (sk, pk) = sm2::gen_keypair();
        // smcrypto 返回的公钥不含 "04" 前缀，这里补上以符合标准非压缩格式
        vec![
            ("private_key".to_string(), sk),
            ("public_key".to_string(), format!("04{}", pk)),
        ]
    }

    /// SM2 加密。
    ///
    /// 参数：
    /// - public_key：hex 公钥（可带或不带 "04" 前缀）
    /// - data：hex 编码的明文
    /// - mode：密文排列模式 "C1C3C2"（默认）/"C1C2C3"/"ASN1"
    ///
    /// 返回：hex 编码的密文
    #[php(optional = mode)]
    pub fn encrypt(
        public_key: String,
        data: String,
        mode: Option<String>,
    ) -> Result<String, PhpException> {
        let mode = mode.unwrap_or_else(|| "C1C3C2".to_string());
        let pk = strip_04_prefix(&public_key);
        let data_bytes = hex_decode_or_err(&data, "数据")?;
        let enc_ctx = sm2::Encrypt::new(&pk);
        let ciphertext = match mode.to_uppercase().as_str() {
            "C1C3C2" => enc_ctx.encrypt(&data_bytes),
            "C1C2C3" => enc_ctx.encrypt_c1c2c3(&data_bytes),
            "ASN1" => enc_ctx.encrypt_asna1(&data_bytes),
            _ => {
                return Err(xhsm_exception_code(
                    ERR_INVALID_PARAM,
                    format!("不支持的加密模式: {}", mode),
                ))
            }
        };
        Ok(hex::encode(&ciphertext))
    }

    /// SM2 解密。
    ///
    /// 参数：
    /// - private_key：hex 私钥（64 hex 字符）
    /// - data：hex 编码的密文
    /// - mode：密文排列模式，需与加密时一致 "C1C3C2"（默认）/"C1C2C3"/"ASN1"
    ///
    /// 返回：hex 编码的明文
    #[php(optional = mode)]
    pub fn decrypt(
        private_key: String,
        data: String,
        mode: Option<String>,
    ) -> Result<String, PhpException> {
        let mode = mode.unwrap_or_else(|| "C1C3C2".to_string());
        let data_bytes = hex_decode_or_err(&data, "密文")?;
        let dec_ctx = sm2::Decrypt::new(&private_key);
        let plaintext = match mode.to_uppercase().as_str() {
            "C1C3C2" => dec_ctx.decrypt(&data_bytes),
            "C1C2C3" => dec_ctx.decrypt_c1c2c3(&data_bytes),
            "ASN1" => dec_ctx.decrypt_asna1(&data_bytes),
            _ => {
                return Err(xhsm_exception_code(
                    ERR_INVALID_PARAM,
                    format!("不支持的解密模式: {}", mode),
                ))
            }
        };
        Ok(hex::encode(&plaintext))
    }

    /// SM2 签名。
    ///
    /// 参数：
    /// - private_key：hex 私钥
    /// - data：hex 编码的待签名数据
    /// - format：签名编码格式 "DER"（默认，ASN.1 DER 编码）/"RAW"（r||s 原始拼接，64字节）
    ///
    /// 返回：hex 编码的签名
    #[php(optional = format)]
    pub fn sign(
        private_key: String,
        data: String,
        format: Option<String>,
    ) -> Result<String, PhpException> {
        let format = format.unwrap_or_else(|| "DER".to_string());
        let data_bytes = hex_decode_or_err(&data, "数据")?;
        let sign_ctx = sm2::Sign::new(&private_key);
        // smcrypto 的 sign() 始终返回 ASN.1 DER 编码
        let der_sig = sign_ctx.sign(&data_bytes);
        match format.to_uppercase().as_str() {
            "DER" => Ok(hex::encode(&der_sig)),
            "RAW" => {
                let raw_sig = der_to_raw(&der_sig).map_err(|e| {
                    xhsm_exception_code(ERR_DECODE, format!("DER 转 RAW 签名失败: {}", e))
                })?;
                Ok(hex::encode(&raw_sig))
            }
            _ => {
                return Err(xhsm_exception_code(
                    ERR_INVALID_PARAM,
                    format!("不支持的签名格式: {}", format),
                ))
            }
        }
    }

    /// SM2 验签。
    ///
    /// 参数：
    /// - public_key：hex 公钥（可带或不带 "04" 前缀）
    /// - data：hex 编码的原始数据
    /// - signature：hex 编码的签名
    /// - format：签名编码格式 "DER"（默认）/"RAW"
    ///
    /// 返回：bool，true 表示验签通过
    #[php(optional = format)]
    pub fn verify(
        public_key: String,
        data: String,
        signature: String,
        format: Option<String>,
    ) -> Result<bool, PhpException> {
        let format = format.unwrap_or_else(|| "DER".to_string());
        let pk = strip_04_prefix(&public_key);
        let data_bytes = hex_decode_or_err(&data, "数据")?;
        let sig_bytes = hex_decode_or_err(&signature, "签名")?;
        let verify_ctx = sm2::Verify::new(&pk);
        let result = match format.to_uppercase().as_str() {
            "DER" => verify_ctx.verify(&data_bytes, &sig_bytes),
            "RAW" => {
                let der_sig = raw_to_der(&sig_bytes).map_err(|e| {
                    xhsm_exception_code(ERR_DECODE, format!("RAW 转 DER 签名失败: {}", e))
                })?;
                verify_ctx.verify(&data_bytes, &der_sig)
            }
            _ => {
                return Err(xhsm_exception_code(
                    ERR_INVALID_PARAM,
                    format!("不支持的签名格式: {}", format),
                ))
            }
        };
        Ok(result)
    }
}

/// 去除公钥的 "04" 前缀（如果存在）。
///
/// smcrypto 内部使用不含 "04" 前缀的公钥格式，
/// 此函数统一处理 PHP 层传入的公钥格式。
fn strip_04_prefix(public_key: &str) -> String {
    let lower = public_key.to_lowercase();
    if lower.len() == 130 && lower.starts_with("04") {
        lower[2..].to_string()
    } else {
        lower
    }
}

/// 将 ASN.1 DER 编码的 SM2 签名转换为 RAW 格式（r || s，各 32 字节）。
///
/// DER 格式：SEQUENCE { INTEGER r, INTEGER s }
/// RAW 格式：r (32字节大端) || s (32字节大端)
fn der_to_raw(der: &[u8]) -> Result<Vec<u8>, &'static str> {
    if der.len() < 8 {
        return Err("DER 数据过短");
    }
    let mut pos = 0;
    // 读取 SEQUENCE 标签
    if der[pos] != 0x30 {
        return Err("期望 SEQUENCE 标签 0x30");
    }
    pos += 1;
    // 读取 SEQUENCE 长度（仅支持短格式）
    let _seq_len = read_der_length(der, &mut pos)? as usize;

    // 读取 r
    if der[pos] != 0x02 {
        return Err("期望 INTEGER 标签 0x02");
    }
    pos += 1;
    let r_len = read_der_length(der, &mut pos)? as usize;
    if pos + r_len > der.len() {
        return Err("r 值长度超出范围");
    }
    let r_bytes = &der[pos..pos + r_len];
    pos += r_len;

    // 读取 s
    if pos >= der.len() || der[pos] != 0x02 {
        return Err("期望第二个 INTEGER 标签 0x02");
    }
    pos += 1;
    let s_len = read_der_length(der, &mut pos)? as usize;
    if pos + s_len > der.len() {
        return Err("s 值长度超出范围");
    }
    let s_bytes = &der[pos..pos + s_len];

    // 去除前导 0x00（DER 正数标记），补齐到 32 字节
    let r_clean = strip_leading_zeros(r_bytes);
    let s_clean = strip_leading_zeros(s_bytes);

    let mut raw = vec![0u8; 64];
    // r 左填充到 32 字节
    let r_offset = 32 - r_clean.len();
    raw[r_offset..32].copy_from_slice(r_clean);
    // s 左填充到 32 字节
    let s_offset = 32 - s_clean.len();
    raw[32 + s_offset..].copy_from_slice(s_clean);

    Ok(raw)
}

/// 将 RAW 格式签名（r || s，各 32 字节）转换为 ASN.1 DER 编码。
fn raw_to_der(raw: &[u8]) -> Result<Vec<u8>, &'static str> {
    if raw.len() != 64 {
        return Err("RAW 签名必须为 64 字节");
    }
    let r = &raw[..32];
    let s = &raw[32..];

    let r_enc = encode_der_integer(r);
    let s_enc = encode_der_integer(s);

    // 构造 SEQUENCE
    let inner_len = r_enc.len() + s_enc.len();
    let mut der = Vec::with_capacity(2 + inner_len);
    der.push(0x30); // SEQUENCE
    der.push(inner_len as u8); // 长度（SM2 签名 inner_len < 128）
    der.extend_from_slice(&r_enc);
    der.extend_from_slice(&s_enc);

    Ok(der)
}

/// 将大端字节编码为 DER INTEGER（带 0x02 标签和长度）。
fn encode_der_integer(value: &[u8]) -> Vec<u8> {
    // 去除前导零
    let stripped = strip_leading_zeros(value);
    // 统一构造 Vec<u8>：如果最高位为 1，需补 0x00 以保证正数
    let bytes: Vec<u8> = if stripped.is_empty() {
        vec![0u8]
    } else if stripped[0] & 0x80 != 0 {
        // 需要补 0x00
        let mut v = Vec::with_capacity(stripped.len() + 1);
        v.push(0x00);
        v.extend_from_slice(stripped);
        v
    } else {
        stripped.to_vec()
    };

    let mut result = Vec::with_capacity(2 + bytes.len());
    result.push(0x02); // INTEGER 标签
    result.push(bytes.len() as u8); // 长度
    result.extend_from_slice(&bytes);
    result
}

/// 去除字节切片的前导零。
fn strip_leading_zeros(bytes: &[u8]) -> &[u8] {
    let mut start = 0;
    while start < bytes.len() - 1 && bytes[start] == 0 {
        start += 1;
    }
    &bytes[start..]
}

/// 读取 DER 长度字段（仅支持短格式，长度 < 128）。
fn read_der_length(data: &[u8], pos: &mut usize) -> Result<u8, &'static str> {
    if *pos >= data.len() {
        return Err("读取长度时数据越界");
    }
    let len_byte = data[*pos];
    *pos += 1;
    if len_byte & 0x80 != 0 {
        return Err("不支持的长格式 DER 长度");
    }
    Ok(len_byte)
}

/// hex 解码辅助函数，失败时返回 Xhsm\Exception 异常（错误码 ERR_INVALID_FORMAT）。
fn hex_decode_or_err(hex_str: &str, name: &str) -> Result<Vec<u8>, PhpException> {
    hex::decode(hex_str).map_err(|e| {
        xhsm_exception_code(
            ERR_INVALID_FORMAT,
            format!("{} hex 解码失败: {}", name, e),
        )
    })
}
