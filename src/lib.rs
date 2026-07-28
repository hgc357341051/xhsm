// xhsm：基于 Rust + ext-php-rs 0.15 构建的国密算法 PHP 扩展
//
// 当前为 Task 1 的最小可编译骨架，仅提供 `xhsm_version()` 函数用于冒烟测试。
// 后续 Task 将在此基础之上逐步加入 SM2/SM3/SM4/SM9 等国密算法模块。

use ext_php_rs::prelude::*;

/// 返回 xhsm 扩展的版本号字符串。
///
/// 作为骨架阶段的冒烟测试入口，后续 Task 会替换为真正的国密能力。
#[php_function]
pub fn xhsm_version() -> String {
    "0.1.0".to_string()
}

/// 模块入口：由 ext-php-rs 的 `#[php_module]` 宏生成 PHP 扩展导出符号。
///
/// 这里将 `xhsm_version` 注册到扩展中，使 PHP 层可直接调用。
#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module.function(wrap_function!(xhsm_version))
}
