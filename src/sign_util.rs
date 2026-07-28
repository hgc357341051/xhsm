// 签名编码工具模块（Task 7 抽出）
//
// 将 SM2 签名的 DER ↔ RAW 转换、hex/base64 输出编解码、公钥 04 前缀处理等
// 纯函数集中到本共享模块，供 signature.rs 与 scenario.rs 复用，避免重复实现。
//
// ===== 函数清单 =====
// - der_to_raw / raw_to_der：ASN.1 DER 与 RAW(r||s) 互转
// - encode_der_integer / strip_leading_zeros / read_der_length：DER INTEGER 编码辅助
// - encode_output / decode_output：签名字节的 hex/base64 编解码
// - strip_04_prefix：去除公钥的 "04" 非压缩前缀

use crate::exception::xhsm_exception;
use base64::Engine as _;
use ext_php_rs::exception::PhpException;

/// 将 ASN.1 DER 编码的 SM2 签名转换为 RAW 格式（r || s，各 32 字节）。
///
/// DER 格式：SEQUENCE { INTEGER r, INTEGER s }
/// RAW 格式：r (32字节大端) || s (32字节大端)
pub fn der_to_raw(der: &[u8]) -> Result<Vec<u8>, &'static str> {
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
pub fn raw_to_der(raw: &[u8]) -> Result<Vec<u8>, &'static str> {
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

/// 按输出编码格式编码签名字节为字符串。
///
/// 支持的 output：`"hex"` / `"base64"`。
pub fn encode_output(sig: &[u8], output: &str) -> Result<String, PhpException> {
    match output.to_lowercase().as_str() {
        "hex" => Ok(hex::encode(sig)),
        "base64" => Ok(base64::engine::general_purpose::STANDARD.encode(sig)),
        _ => Err(xhsm_exception(format!(
            "不支持的输出编码: {}（支持 hex/base64）",
            output
        ))),
    }
}

/// 按输出编码格式解码签名串为字节。
///
/// 支持的 output：`"hex"` / `"base64"`。
pub fn decode_output(sig: &str, output: &str) -> Result<Vec<u8>, PhpException> {
    match output.to_lowercase().as_str() {
        "hex" => {
            hex::decode(sig).map_err(|e| xhsm_exception(format!("签名 hex 解码失败: {}", e)))
        }
        "base64" => base64::engine::general_purpose::STANDARD
            .decode(sig)
            .map_err(|e| xhsm_exception(format!("签名 base64 解码失败: {}", e))),
        _ => Err(xhsm_exception(format!(
            "不支持的输出编码: {}（支持 hex/base64）",
            output
        ))),
    }
}

/// 去除公钥的 "04" 前缀（如果存在）。
///
/// smcrypto 内部使用不含 "04" 前缀的公钥格式，
/// 此函数统一处理 PHP 层传入的公钥格式（小写化并按需剥离前缀）。
pub fn strip_04_prefix(public_key: &str) -> String {
    let lower = public_key.to_lowercase();
    if lower.len() == 130 && lower.starts_with("04") {
        lower[2..].to_string()
    } else {
        lower
    }
}
