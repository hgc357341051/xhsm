# Checklist

## 触发约束（核心）
- [x] `.github/workflows/build-rust.yml` 的 `on:` 仅含 `push.tags: ['v*']`，不含 `push.branches`
- [x] 流水线不含 `pull_request`、`workflow_dispatch`、`schedule`、`workflow_run` 等任何其他触发器
- [x] 推送 main 分支代码不会触发本流水线

## 项目适配
- [x] 扩展名统一为 `xhsm`（env `EXTENSION_NAME`、产物文件名、Release body）
- [x] 无 `working-directory: rust`（xhsm 代码在仓库根目录）
- [x] 无任何 `--features php`（ext-php-rs 是直接依赖）
- [x] 缓存/产物路径使用 `target`（非 `rust/target`）
- [x] 缓存 key 使用 `hashFiles('Cargo.lock')`（非 `rust/Cargo.lock`）
- [x] 加载验证使用 `php -d extension=xhsm -r "echo xhsm_version();"`
- [x] PHP 测试步骤使用 `tests/*.php`，无 socat / mock_server 逻辑

## Windows 编译支持
- [x] `src/lib.rs` 顶部已添加 `#![cfg_attr(windows, feature(abi_vectorcall))]`
- [x] `.cargo/config.toml` 已为 `x86_64-pc-windows-msvc` 配置 `rust-lld` 链接器
- [x] build-windows job 使用 Rust nightly（`RUST_TOOLCHAIN_WINDOWS: nightly`）
- [x] 上述改动不影响 Linux/macOS 编译（`cfg(windows)` 限定）

## Job 完整性
- [x] `build-linux`（Ubuntu 24.04 × PHP 8.1~8.5）存在，产物 `.so`
- [x] `build-macos`（macos-14 × PHP 8.1~8.5）存在，产物 `.dylib`/`.so`
- [x] `build-windows`（windows-2022 × PHP 8.1~8.5 × {nts,ts}）存在，产物 `.dll`
- [x] `lint-and-test` 存在，含 `cargo fmt --check` / `cargo clippy` / `cargo test --lib`
- [x] `release` 存在，`if: startsWith(github.ref, 'refs/tags/v')`，`needs` 依赖全部 build + lint job
- [x] `release` job `permissions: contents: write`，使用 `softprops/action-gh-release@v2`

## 提交与推送
- [x] 改动已在本地 `main` 分支提交
- [x] 已推送到远程 `origin/main` 主分支
- [x] 推送 main 分支后 GitHub Actions **未**触发本流水线（验证触发约束生效）
