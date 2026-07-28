// xhsm：基于 Rust + ext-php-rs 0.15 构建的国密算法 PHP 扩展
//
// 当前实现：
// - Task 1：最小可编译骨架，提供 `xhsm_version()` 冒烟函数
// - Task 2：SM3 摘要与 HMAC-SM3（Xhsm\Sm3）
// - Task 3：SM4 对称加密 ECB/CBC/CTR/GCM（Xhsm\Sm4）
// - Task 4：SM2 非对称加解密与签名验签（Xhsm\Sm2）
// - 异常统一为 Xhsm\Exception

use ext_php_rs::prelude::*;

mod exception;
mod sm2;
mod sm3;
mod sm4;

use exception::Exception;
use sm2::Sm2;
use sm3::Sm3;
use sm4::Sm4;

/// 返回 xhsm 扩展的版本号字符串。
///
/// 作为骨架阶段的冒烟测试入口，保留向后兼容。
#[php_function]
pub fn xhsm_version() -> String {
    "0.1.0".to_string()
}

/// 模块入口：由 ext-php-rs 的 `#[php_module]` 宏生成 PHP 扩展导出符号。
///
/// 注册扩展函数与所有 OOP 类：
/// - Xhsm\Exception：统一异常类型
/// - Xhsm\Sm2：SM2 非对称算法
/// - Xhsm\Sm3：SM3 杂凑算法
/// - Xhsm\Sm4：SM4 对称算法
#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
        .function(wrap_function!(xhsm_version))
        .class::<Exception>()
        .class::<Sm2>()
        .class::<Sm3>()
        .class::<Sm4>()
}
