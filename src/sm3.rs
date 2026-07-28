// SM3 密码杂凑算法实现（Task 2）
//
// 基于 RustCrypto 的 sm3 + hmac crate 实现 SM3 摘要与 HMAC-SM3。
// 对外暴露为 Xhsm\Sm3 类，提供两个静态方法：hash 与 hmac。

use crate::exception::xhsm_exception;
use ext_php_rs::exception::PhpException;
use ext_php_rs::prelude::*;
use hmac::{Hmac, Mac};
use sm3::Sm3 as Sm3Hasher;

/// HMAC-SM3 类型别名
type HmacSm3 = Hmac<Sm3Hasher>;

/// SM3 杂凑算法封装类。
///
/// 提供 SM3 摘要计算与 HMAC-SM3 消息认证码生成能力。
#[php_class]
#[php(name = "Xhsm\\Sm3")]
#[derive(Default)]
pub struct Sm3;

#[php_impl]
impl Sm3 {
    /// 计算 SM3 摘要。
    ///
    /// 参数 data 为原始字符串，返回 32 字节摘要的 hex 小写字符串（64 字符）。
    pub fn hash(data: String) -> Result<String, PhpException> {
        use sm3::Digest;
        let mut hasher = Sm3Hasher::new();
        hasher.update(data.as_bytes());
        let result = hasher.finalize();
        Ok(hex::encode(result))
    }

    /// 计算 HMAC-SM3 消息认证码。
    ///
    /// 参数 key 为密钥字符串，data 为待认证数据，返回 hex 小写字符串。
    pub fn hmac(key: String, data: String) -> Result<String, PhpException> {
        let mut mac = HmacSm3::new_from_slice(key.as_bytes())
            .map_err(|e| xhsm_exception(format!("HMAC-SM3 密钥初始化失败: {}", e)))?;
        mac.update(data.as_bytes());
        let result = mac.finalize();
        Ok(hex::encode(result.into_bytes()))
    }
}
