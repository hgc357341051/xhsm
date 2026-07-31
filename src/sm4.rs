// SM4 对称加密算法实现（Task 3）
//
// 基于 RustCrypto 的 sm4 块密码 + cbc/ecb/ctr 工作模式 crate，
// 支持 ECB/CBC/CTR/GCM 四种模式。对外暴露为 Xhsm\Sm4 类。
//
// GCM 模式因 RustCrypto 通用 gcm crate 停留在 cipher 0.2（与 sm4 0.5 的 cipher 0.4 不兼容），
// 故使用 ghash crate 手动实现 GCM（CTR + GHASH），保证纯 Rust 无系统依赖。
//
// 所有输入（key/iv/data/aad）与输出均为 hex 编码字符串：
// - key：16 字节（32 hex 字符）
// - iv：CBC/CTR 为 16 字节，GCM 为 12 字节，ECB 忽略
// - data：明文/密文
// - aad：GCM 附加认证数据（其他模式忽略）
// - 返回：密文（GCM 模式为密文+16字节认证 Tag）

use crate::exception::{xhsm_exception, xhsm_exception_code, Exception};
use cipher::generic_array::GenericArray;
use ext_php_rs::exception::PhpException;
use ext_php_rs::prelude::*;
// 重命名 RustCrypto 的 Sm4 块密码为 Sm4Cipher，避免与下方 Xhsm\Sm4 类冲突
use sm4::Sm4 as Sm4Cipher;

// 错误码常量：从 Exception 类复用，供本模块各错误路径统一引用。
const ERR_INVALID_FORMAT: i32 = Exception::ERR_INVALID_FORMAT;
const ERR_INVALID_PARAM: i32 = Exception::ERR_INVALID_PARAM;
const ERR_DECODE: i32 = Exception::ERR_DECODE;

// 各工作模式类型别名
type Sm4EcbEnc = ecb::Encryptor<Sm4Cipher>;
type Sm4EcbDec = ecb::Decryptor<Sm4Cipher>;
type Sm4CbcEnc = cbc::Encryptor<Sm4Cipher>;
type Sm4CbcDec = cbc::Decryptor<Sm4Cipher>;
type Sm4Ctr = ctr::Ctr128BE<Sm4Cipher>;

/// SM4 对称加密算法封装类。
///
/// 支持 ECB/CBC/CTR/GCM 四种工作模式，通过 encrypt/decrypt 静态方法调用。
#[php_class]
#[php(name = "Xhsm\\Sm4")]
#[derive(Default)]
pub struct Sm4;

#[php_impl]
impl Sm4 {
    /// SM4 加密。
    ///
    /// 参数：
    /// - key：hex 编码的 16 字节密钥（32 hex 字符）
    /// - iv：hex 编码的初始化向量（CBC/CTR 16字节，GCM 12字节，ECB 忽略）
    /// - data：hex 编码的明文
    /// - mode：工作模式 "ECB"/"CBC"/"CTR"/"GCM"，默认 "CBC"
    /// - aad：GCM 附加认证数据（hex 编码），其他模式忽略，默认空
    ///
    /// 返回：hex 编码的密文（GCM 模式为密文+16字节认证 Tag）
    #[php(optional = mode)]
    pub fn encrypt(
        key: String,
        iv: String,
        data: String,
        mode: Option<String>,
        aad: Option<String>,
    ) -> Result<String, PhpException> {
        let mode = mode.unwrap_or_else(|| "CBC".to_string());
        let aad_str = aad.unwrap_or_default();
        let key_bytes = hex_decode_or_err(&key, "密钥")?;
        let iv_bytes = hex_decode_or_err(&iv, "IV")?;
        let data_bytes = hex_decode_or_err(&data, "数据")?;
        let aad_bytes = hex_decode_or_err(&aad_str, "AAD")?;

        if key_bytes.len() != 16 {
            return Err(xhsm_exception("SM4 密钥必须为 16 字节（32 hex 字符）"));
        }

        let mode_upper = mode.to_uppercase();
        let ciphertext = match mode_upper.as_str() {
            "ECB" => {
                use ecb::cipher::block_padding::Pkcs7;
                use ecb::cipher::{BlockEncryptMut, KeyInit};
                let enc = Sm4EcbEnc::new_from_slice(&key_bytes)
                    .map_err(|e| xhsm_exception(format!("ECB 初始化失败: {}", e)))?;
                enc.encrypt_padded_vec_mut::<Pkcs7>(&data_bytes)
            }
            "CBC" => {
                use cbc::cipher::block_padding::Pkcs7;
                use cbc::cipher::{BlockEncryptMut, KeyIvInit};
                if iv_bytes.len() != 16 {
                    return Err(xhsm_exception("CBC 模式 IV 必须为 16 字节"));
                }
                let key_ga = GenericArray::from_slice(&key_bytes);
                let iv_ga = GenericArray::from_slice(&iv_bytes);
                let enc = Sm4CbcEnc::new(key_ga, iv_ga);
                enc.encrypt_padded_vec_mut::<Pkcs7>(&data_bytes)
            }
            "CTR" => {
                use ctr::cipher::{KeyIvInit, StreamCipher};
                if iv_bytes.len() != 16 {
                    return Err(xhsm_exception("CTR 模式 IV 必须为 16 字节"));
                }
                let key_ga = GenericArray::from_slice(&key_bytes);
                let iv_ga = GenericArray::from_slice(&iv_bytes);
                let mut cipher = Sm4Ctr::new(key_ga, iv_ga);
                let mut buf = data_bytes.clone();
                cipher.apply_keystream(&mut buf);
                buf
            }
            "GCM" => gcm_encrypt(&key_bytes, &iv_bytes, &data_bytes, &aad_bytes)?,
            _ => {
                return Err(xhsm_exception_code(
                    ERR_INVALID_PARAM,
                    format!("不支持的工作模式: {}", mode),
                ))
            }
        };

        Ok(hex::encode(&ciphertext))
    }

    /// SM4 解密。
    ///
    /// 参数同 encrypt，data 为 hex 编码的密文（GCM 模式为密文+16字节 Tag）。
    /// 返回 hex 编码的明文。
    #[php(optional = mode)]
    pub fn decrypt(
        key: String,
        iv: String,
        data: String,
        mode: Option<String>,
        aad: Option<String>,
    ) -> Result<String, PhpException> {
        let mode = mode.unwrap_or_else(|| "CBC".to_string());
        let aad_str = aad.unwrap_or_default();
        let key_bytes = hex_decode_or_err(&key, "密钥")?;
        let iv_bytes = hex_decode_or_err(&iv, "IV")?;
        let data_bytes = hex_decode_or_err(&data, "数据")?;
        let aad_bytes = hex_decode_or_err(&aad_str, "AAD")?;

        if key_bytes.len() != 16 {
            return Err(xhsm_exception("SM4 密钥必须为 16 字节（32 hex 字符）"));
        }

        let mode_upper = mode.to_uppercase();
        let plaintext = match mode_upper.as_str() {
            "ECB" => {
                use ecb::cipher::block_padding::Pkcs7;
                use ecb::cipher::{BlockDecryptMut, KeyInit};
                let dec = Sm4EcbDec::new_from_slice(&key_bytes)
                    .map_err(|e| xhsm_exception(format!("ECB 初始化失败: {}", e)))?;
                dec.decrypt_padded_vec_mut::<Pkcs7>(&data_bytes)
                    .map_err(|e| xhsm_exception(format!("ECB 解密失败: {}", e)))?
            }
            "CBC" => {
                use cbc::cipher::block_padding::Pkcs7;
                use cbc::cipher::{BlockDecryptMut, KeyIvInit};
                if iv_bytes.len() != 16 {
                    return Err(xhsm_exception("CBC 模式 IV 必须为 16 字节"));
                }
                let key_ga = GenericArray::from_slice(&key_bytes);
                let iv_ga = GenericArray::from_slice(&iv_bytes);
                let dec = Sm4CbcDec::new(key_ga, iv_ga);
                dec.decrypt_padded_vec_mut::<Pkcs7>(&data_bytes)
                    .map_err(|e| xhsm_exception(format!("CBC 解密失败: {}", e)))?
            }
            "CTR" => {
                use ctr::cipher::{KeyIvInit, StreamCipher};
                if iv_bytes.len() != 16 {
                    return Err(xhsm_exception("CTR 模式 IV 必须为 16 字节"));
                }
                let key_ga = GenericArray::from_slice(&key_bytes);
                let iv_ga = GenericArray::from_slice(&iv_bytes);
                let mut cipher = Sm4Ctr::new(key_ga, iv_ga);
                let mut buf = data_bytes.clone();
                cipher.apply_keystream(&mut buf);
                buf
            }
            "GCM" => gcm_decrypt(&key_bytes, &iv_bytes, &data_bytes, &aad_bytes)?,
            _ => {
                return Err(xhsm_exception_code(
                    ERR_INVALID_PARAM,
                    format!("不支持的工作模式: {}", mode),
                ))
            }
        };

        Ok(hex::encode(&plaintext))
    }
}

/// GCM 加密（手动实现：CTR + GHASH）。
///
/// 基于 SM4 块密码与 ghash crate 实现 GCM 认证加密。
/// 输出格式：密文 || 16字节认证 Tag
fn gcm_encrypt(
    key: &[u8],
    iv: &[u8],
    plaintext: &[u8],
    aad: &[u8],
) -> Result<Vec<u8>, PhpException> {
    use cipher::{BlockEncrypt, KeyInit};
    use ghash::universal_hash::UniversalHash;
    use ghash::GHash;

    if iv.len() != 12 {
        return Err(xhsm_exception("GCM 模式 IV 必须为 12 字节"));
    }

    let key_ga = GenericArray::from_slice(key);
    let cipher = Sm4Cipher::new(key_ga);

    // 1. H = E_K(0^128)，GHASH 的哈希子密钥
    let mut h = GenericArray::from([0u8; 16]);
    cipher.encrypt_block(&mut h);

    // 2. J0 = IV || 0^31 || 1（96 位 IV 情况）
    let mut j0 = [0u8; 16];
    j0[..12].copy_from_slice(iv);
    j0[15] = 1;

    // 3. CTR 加密：从 inc32(J0) 开始按计数器加密
    let mut ciphertext = plaintext.to_vec();
    let mut counter = j0;
    for chunk in ciphertext.chunks_mut(16) {
        inc32(&mut counter);
        let mut keystream = counter;
        let block = GenericArray::from_mut_slice(&mut keystream);
        cipher.encrypt_block(block);
        for (b, k) in chunk.iter_mut().zip(keystream.iter()) {
            *b ^= *k;
        }
    }

    // 4. GHASH(AAD || 0^v || C || 0^u || [len(A)]_64 || [len(C)]_64)
    let mut ghash = GHash::new(&h);
    ghash.update_padded(aad);
    ghash.update_padded(&ciphertext);
    let mut len_block = [0u8; 16];
    len_block[..8].copy_from_slice(&((aad.len() as u64) * 8).to_be_bytes());
    len_block[8..].copy_from_slice(&((ciphertext.len() as u64) * 8).to_be_bytes());
    let len_ga = GenericArray::from(len_block);
    ghash.update(std::slice::from_ref(&len_ga));
    let ghash_tag = ghash.finalize();

    // 5. T = E_K(J0) XOR GHASH
    let mut j0_enc = j0;
    {
        let block = GenericArray::from_mut_slice(&mut j0_enc);
        cipher.encrypt_block(block);
    }
    let mut tag = [0u8; 16];
    tag.copy_from_slice(ghash_tag.as_slice());
    for i in 0..16 {
        tag[i] ^= j0_enc[i];
    }

    // 输出：密文 || Tag
    ciphertext.extend_from_slice(&tag);
    Ok(ciphertext)
}

/// GCM 解密（手动实现：CTR + GHASH Tag 校验）。
///
/// 输入 data 为 密文 || 16字节 Tag，校验 Tag 通过后返回明文。
fn gcm_decrypt(key: &[u8], iv: &[u8], data: &[u8], aad: &[u8]) -> Result<Vec<u8>, PhpException> {
    use cipher::{BlockEncrypt, KeyInit};
    use ghash::universal_hash::UniversalHash;
    use ghash::GHash;

    if iv.len() != 12 {
        return Err(xhsm_exception("GCM 模式 IV 必须为 12 字节"));
    }
    if data.len() < 16 {
        return Err(xhsm_exception("GCM 解密数据长度不足（密文+16字节 Tag）"));
    }

    // 分离密文与 Tag
    let ct_len = data.len() - 16;
    let ciphertext = &data[..ct_len];
    let received_tag = &data[ct_len..];

    let key_ga = GenericArray::from_slice(key);
    let cipher = Sm4Cipher::new(key_ga);

    // 1. H = E_K(0^128)
    let mut h = GenericArray::from([0u8; 16]);
    cipher.encrypt_block(&mut h);

    // 2. J0 = IV || 0^31 || 1
    let mut j0 = [0u8; 16];
    j0[..12].copy_from_slice(iv);
    j0[15] = 1;

    // 3. 先计算 GHASH 校验 Tag（在解密前验证完整性）
    let mut ghash = GHash::new(&h);
    ghash.update_padded(aad);
    ghash.update_padded(ciphertext);
    let mut len_block = [0u8; 16];
    len_block[..8].copy_from_slice(&((aad.len() as u64) * 8).to_be_bytes());
    len_block[8..].copy_from_slice(&((ciphertext.len() as u64) * 8).to_be_bytes());
    let len_ga = GenericArray::from(len_block);
    ghash.update(std::slice::from_ref(&len_ga));
    let ghash_tag = ghash.finalize();

    // 4. T = E_K(J0) XOR GHASH
    let mut j0_enc = j0;
    {
        let block = GenericArray::from_mut_slice(&mut j0_enc);
        cipher.encrypt_block(block);
    }
    let mut expected_tag = [0u8; 16];
    expected_tag.copy_from_slice(ghash_tag.as_slice());
    for i in 0..16 {
        expected_tag[i] ^= j0_enc[i];
    }

    // 5. 常量时间比较 Tag
    if !constant_time_eq(&expected_tag, received_tag) {
        return Err(xhsm_exception_code(
            ERR_DECODE,
            "GCM 解密失败（Tag 校验失败或数据错误）",
        ));
    }

    // 6. Tag 校验通过，CTR 解密
    let mut plaintext = ciphertext.to_vec();
    let mut counter = j0;
    for chunk in plaintext.chunks_mut(16) {
        inc32(&mut counter);
        let mut keystream = counter;
        let block = GenericArray::from_mut_slice(&mut keystream);
        cipher.encrypt_block(block);
        for (b, k) in chunk.iter_mut().zip(keystream.iter()) {
            *b ^= *k;
        }
    }

    Ok(plaintext)
}

/// GCM 计数器递增：对 16 字节计数器的最后 32 位（大端）加 1，模 2^32。
fn inc32(counter: &mut [u8; 16]) {
    let n = u32::from_be_bytes([counter[12], counter[13], counter[14], counter[15]]);
    let n = n.wrapping_add(1);
    counter[12..16].copy_from_slice(&n.to_be_bytes());
}

/// 常量时间字节比较，防止 Tag 校验的时序侧信道攻击。
fn constant_time_eq(a: &[u8], b: &[u8]) -> bool {
    if a.len() != b.len() {
        return false;
    }
    let mut diff = 0u8;
    for (x, y) in a.iter().zip(b.iter()) {
        diff |= x ^ y;
    }
    diff == 0
}

/// hex 解码辅助函数，失败时返回 Xhsm\Exception 异常（错误码 ERR_INVALID_FORMAT）。
fn hex_decode_or_err(hex_str: &str, name: &str) -> Result<Vec<u8>, PhpException> {
    hex::decode(hex_str).map_err(|e| {
        xhsm_exception_code(ERR_INVALID_FORMAT, format!("{} hex 解码失败: {}", name, e))
    })
}
