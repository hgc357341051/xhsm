// xhsm 异常类定义
//
// 定义 Xhsm\Exception 异常类，继承 PHP 内置 \Exception，
// 作为扩展所有 Rust 层错误的统一异常类型。
// 各算法模块通过 xhsm_exception 辅助函数将错误包装为此类型抛出。

use ext_php_rs::exception::PhpException;
use ext_php_rs::prelude::*;
use ext_php_rs::zend::ce;

/// Xhsm\Exception 异常类。
///
/// 继承 PHP 内置 \Exception，作为 xhsm 扩展所有错误的统一异常类型。
/// 所有 Rust 层错误均通过 xhsm_exception 辅助函数转换为此类型抛出。
#[php_class]
#[php(name = "Xhsm\\Exception")]
#[php(extends(ce = ce::exception, stub = "\\Exception"))]
#[derive(Default)]
pub struct Exception;

#[php_impl]
impl Exception {}

/// 将错误消息包装为 Xhsm\Exception 类型的 PhpException。
///
/// 供各算法模块在 Result::Err 分支中统一调用，确保所有错误抛出为 Xhsm\Exception。
pub fn xhsm_exception(msg: impl ToString) -> PhpException {
    PhpException::from_class::<Exception>(msg.to_string())
}
