# xhsm 自动编译流水线 (GitHub Actions) Spec

## Why
当前 xhsm 项目没有任何 CI/CD，每次发版需要手动在本地多平台编译 PHP 扩展产物（`.so`/`.dylib`/`.dll`），效率低且易出错。参考 `hgc357341051/xhcurl` 仓库的 `build-rust.yml` 流水线，为 xhsm 建立同样的多平台自动编译 + 自动发布 Release 的流水线。

**核心约束**：流水线**仅在推送 `v*` tag 时触发**，不允许任何其他方式触发（main 分支推送代码、PR 等均不可触发），以避免消耗 GitHub Actions 分钟额度。

## What Changes
- 新增 `.github/workflows/build-rust.yml`：复刻自 xhcurl 的 `build-rust.yml`，适配 xhsm 项目结构
  - 触发条件：`on: push: tags: ['v*']`（**唯一触发方式**，不监听分支 push / PR）
  - Job 1 `build-linux`：Ubuntu 24.04 × PHP 8.1~8.5，编译 `libxhsm.so`，加载验证，运行 `tests/*.php` 纯密码学测试
  - Job 2 `build-macos`：macOS 14 × PHP 8.1~8.5，编译 `libxhsm.dylib`
  - Job 3 `build-windows`：Windows 2022 × PHP 8.1~8.5（NTS/TS），nightly Rust，编译 `xhsm.dll`
  - Job 4 `lint-and-test`：`cargo fmt --check` + `cargo clippy` + `cargo test --lib`
  - Job 5 `release`：`if: startsWith(github.ref, 'refs/tags/v')`，收集所有产物发布 GitHub Release
- 适配 xhsm 项目差异（相对 xhcurl 参考流水线）：
  - 扩展名 `xhcurl` → `xhsm`
  - 项目布局：xhcurl 代码在 `rust/` 子目录，xhsm 代码在仓库根目录 → 去掉 `working-directory: rust`，缓存/产物路径 `rust/target` → `target`，`hashFiles('rust/Cargo.lock')` → `hashFiles('Cargo.lock')`
  - feature 开关：xhcurl 用 `--features php`，xhsm 的 `ext-php-rs` 是直接依赖（无 feature gate）→ 移除所有 `--features php`
  - PHP 测试：xhcurl 需 mock_server（HTTP 协程测试），xhsm 是纯密码学测试（无网络）→ 用 `tests/*.php` 替换，去掉 socat / mock_server 逻辑
  - 冒烟函数：xhcurl 用 `XHCurl::version()`，xhsm 用 `xhsm_version()` 函数
- 新增 Windows 编译所需的源码/配置支持（ext-php-rs 0.15 在 Windows 上的硬性要求）：
  - 在 `src/lib.rs` 顶部添加 `#![cfg_attr(windows, feature(abi_vectorcall))]`（ext-php-rs 0.15 在 Windows 上需要 `abi_vectorcall` 调用约定，仅 nightly 可用）
  - 新增 `.cargo/config.toml`：Windows 下使用 `rust-lld` 链接器（避免 MSVC `link.exe` 触发 `STATUS_STACK_BUFFER_OVERRUN` 崩溃）
- 本地在 `main` 分支提交上述改动，并推送到远程 `main` 主分支

## Impact
- Affected specs: `add-xhsm-extension`（扩展本身，无功能改动，仅新增 Windows 编译所需的 crate 级 attribute）
- Affected code:
  - 新增 `.github/workflows/build-rust.yml`
  - 修改 `src/lib.rs`（仅在文件最顶部新增一行 inner attribute，不改动任何业务逻辑）
  - 新增 `.cargo/config.toml`
- 运行环境：GitHub Actions（ubuntu-24.04 / macos-14 / windows-2022），PHP 8.1~8.5，Rust stable（Linux/macOS）+ nightly（Windows）
- 触发成本：仅 `v*` tag 推送时消耗 Actions 分钟数，日常 main 分支开发零消耗

## ADDED Requirements

### Requirement: 仅 v* tag 触发
流水线 SHALL 仅在推送匹配 `v*` 的 tag 时触发，SHALL NOT 在任何分支推送（含 main）、PR、schedule、workflow_dispatch 等其他方式下触发。

#### Scenario: 推送 v* tag 触发
- **WHEN** 推送形如 `v0.1.0` 的 tag
- **THEN** `build-linux` / `build-macos` / `build-windows` / `lint-and-test` / `release` 全部 job 被触发

#### Scenario: main 分支推送不触发
- **WHEN** 向 `main` 分支推送代码提交
- **THEN** 流水线不运行（`on` 中无 `push.branches`、无 `pull_request`）

### Requirement: 多平台自动编译
系统 SHALL 在 Linux（Ubuntu 24.04）/ macOS 14 / Windows 2022 三平台上，针对 PHP 8.1~8.5 自动编译 xhsm 扩展，产物分别为 `.so` / `.dylib` / `.dll`，Windows 额外区分 NTS/TS。

#### Scenario: Linux 编译产物
- **WHEN** v* tag 触发 build-linux
- **THEN** 生成 `xhsm-rust-linux-php{8.1..8.5}.so` 并通过 `php -d extension=xhsm` 加载验证 + 运行 `tests/*.php` 全部通过

#### Scenario: Windows 编译依赖 nightly
- **WHEN** v* tag 触发 build-windows
- **THEN** 使用 Rust nightly（`abi_vectorcall` feature）+ `rust-lld` 链接器编译出 `xhsm-rust-windows-php{ver}-{nts|ts}.dll`

### Requirement: 自动发布 Release
系统 SHALL 在所有 build 与 lint job 通过后，针对触发 tag 创建 GitHub Release 并上传全部平台产物。

#### Scenario: Release 创建
- **WHEN** `build-linux` / `build-macos` / `build-windows` / `lint-and-test` 全部成功且 ref 以 `refs/tags/v` 开头
- **THEN** 创建名为 `xhsm {tag}` 的正式 Release，附件包含所有 `.so`/`.dylib`/`.dll`

### Requirement: Windows 编译支持改动
为使 Windows job 可编译，SHALL 在 `src/lib.rs` 顶部添加 `#![cfg_attr(windows, feature(abi_vectorcall))]`，并新增 `.cargo/config.toml` 为 Windows target 配置 `rust-lld` 链接器。此改动 SHALL NOT 影响 Linux/macOS 编译（仅 `cfg(windows)` 生效）。

## MODIFIED Requirements
无（本变更只新增流水线与 Windows 编译支持配置，不修改既有功能需求）。

## REMOVED Requirements
无。
