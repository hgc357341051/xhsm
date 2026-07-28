// xhsm 异常类定义
//
// 定义 Xhsm\Exception 异常类，继承 PHP 内置 \Exception，
// 作为扩展所有 Rust 层错误的统一异常类型。
// 各算法模块通过 xhsm_exception / xhsm_exception_code 辅助函数将错误包装为此类型抛出。
//
// ===== 错误码常量（Task 8）=====
// 在 #[php_impl] impl 块内定义的 pub const 会被 ext-php-rs 自动导出为 PHP 类常量，
// PHP 侧通过 Xhsm\Exception::ERR_XXX 访问，用于在 catch 后按 getCode() 区分错误类别。
//
// 错误码语义分类（数值稳定，不再变更）：
// - ERR_INVALID_FORMAT = 1001：格式/编码错误（如 hex 解码失败）
// - ERR_INVALID_KEY    = 1002：密钥无效
// - ERR_INVALID_PARAM  = 1003：参数非法（如不支持的 mode/format/version）
// - ERR_DECODE         = 1004：解码/解析失败（如 DER 解析、UTF-8 还原、GCM Tag 校验失败）
// - ERR_INTERNAL       = 1005：内部错误（如 SM9 crate panic、锁中毒）
// - ERR_UNSUPPORTED    = 1006：不支持的算法/操作

use ext_php_rs::class::RegisteredClass;
use ext_php_rs::exception::PhpException;
use ext_php_rs::prelude::*;
use ext_php_rs::zend::ce;

/// Xhsm\Exception 异常类。
///
/// 继承 PHP 内置 \Exception，作为 xhsm 扩展所有错误的统一异常类型。
/// 所有 Rust 层错误均通过 xhsm_exception / xhsm_exception_code 辅助函数转换为此类型抛出。
///
/// 携带语义化错误码常量（ERR_INVALID_FORMAT 等），供 PHP 侧按 getCode() 分类处理。
#[php_class]
#[php(name = "Xhsm\\Exception")]
#[php(extends(ce = ce::exception, stub = "\\Exception"))]
#[derive(Default)]
pub struct Exception;

#[php_impl]
impl Exception {
    /// 格式/编码错误（如 hex 解码失败）。
    pub const ERR_INVALID_FORMAT: i32 = 1001;
    /// 密钥无效。
    pub const ERR_INVALID_KEY: i32 = 1002;
    /// 参数非法（如不支持的 mode/format/version）。
    pub const ERR_INVALID_PARAM: i32 = 1003;
    /// 解码/解析失败（如 DER 解析、UTF-8 还原、GCM Tag 校验失败）。
    pub const ERR_DECODE: i32 = 1004;
    /// 内部错误（如 SM9 crate panic、锁中毒）。
    pub const ERR_INTERNAL: i32 = 1005;
    /// 不支持的算法/操作。
    pub const ERR_UNSUPPORTED: i32 = 1006;
}

/// 将错误消息包装为 Xhsm\Exception 类型的 PhpException（默认 code=0）。
///
/// 供各算法模块在 Result::Err 分支中统一调用，确保所有错误抛出为 Xhsm\Exception。
/// 保留此函数以兼容现有不携带语义错误码的调用路径。
pub fn xhsm_exception(msg: impl ToString) -> PhpException {
    PhpException::from_class::<Exception>(msg.to_string())
}

/// 将错误码与消息包装为带语义错误码的 Xhsm\Exception。
///
/// 与 xhsm_exception 不同，本函数允许指定非零错误码（取自 Exception 类的 ERR_* 常量），
/// 用于在 PHP 侧通过 getCode() 区分错误类别。
///
/// 实现说明：ext-php-rs 0.15 的 `PhpException::from_class` 仅支持 message 不支持 code，
/// 故这里通过 `PhpException::new(msg, code, Exception::get_metadata().ce())` 构造，
/// `Exception::get_metadata().ce()` 返回 `&'static ClassEntry`，满足 new 的签名要求。
pub fn xhsm_exception_code(code: i32, msg: impl ToString) -> PhpException {
    PhpException::new(msg.to_string(), code, Exception::get_metadata().ce())
}
