#![cfg_attr(windows, feature(abi_vectorcall))]

// xhsm：基于 Rust + ext-php-rs 0.15 构建的国密算法 PHP 扩展
//
// 当前实现：
// - Task 1：最小可编译骨架，提供 `xhsm_version()` 冒烟函数
// - Task 2：SM3 摘要与 HMAC-SM3（Xhsm\Sm3）
// - Task 3：SM4 对称加密 ECB/CBC/CTR/GCM（Xhsm\Sm4）
// - Task 4：SM2 非对称加解密与签名验签（Xhsm\Sm2）
// - Task 5：SM9 标识基加解密与签名验签（Xhsm\Sm9）
// - Task 6：可扩展签名版本体系（Xhsm\Signature，内置 s2/s3/s4）
// - Task 7：业务场景预设（Xhsm\Scenario\Finance/Payment/Government/MiniProgram）
// - 异常统一为 Xhsm\Exception

use ext_php_rs::prelude::*;

mod exception;
mod scenario;
mod sign_util;
mod signature;
mod sm2;
mod sm3;
mod sm4;
mod sm9;

use exception::Exception;
use scenario::{Finance, Government, MiniProgram, Payment, Uniapp, Web};
use signature::Signature;
use sm2::Sm2;
use sm3::Sm3;
use sm4::Sm4;
use sm9::Sm9;

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
/// - Xhsm\Sm9：SM9 标识基算法
/// - Xhsm\Signature：可扩展签名版本体系
/// - Xhsm\Scenario\Finance：金融业务场景预设
/// - Xhsm\Scenario\Payment：支付业务场景预设
/// - Xhsm\Scenario\Government：政务业务场景预设
/// - Xhsm\Scenario\MiniProgram：小程序业务场景预设
#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
        .function(wrap_function!(xhsm_version))
        .class::<Exception>()
        .class::<Sm2>()
        .class::<Sm3>()
        .class::<Sm4>()
        .class::<Sm9>()
        .class::<Signature>()
        .class::<Finance>()
        .class::<Payment>()
        .class::<Government>()
        .class::<MiniProgram>()
        .class::<Uniapp>()
        .class::<Web>()
}
