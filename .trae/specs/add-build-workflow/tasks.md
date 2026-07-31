# Tasks

- [x] Task 1: 为 src/lib.rs 添加 Windows 编译支持
  - [x] SubTask 1.1: 在 `src/lib.rs` 文件最顶部（现有注释之前）添加 inner attribute `#![cfg_attr(windows, feature(abi_vectorcall))]`，使 ext-php-rs 0.15 在 Windows 上可启用 `abi_vectorcall` 调用约定（nightly-only）。不改动任何业务逻辑代码。
  - [x] SubTask 1.2: 验证 Linux 下 `cargo build --release` 仍可正常编译（attribute 受 `cfg(windows)` 限制，Linux 不受影响）

- [x] Task 2: 新增 `.cargo/config.toml` 配置 Windows 链接器
  - [x] SubTask 2.1: 创建 `.cargo/config.toml`，为 `x86_64-pc-windows-msvc` target 配置 `rustflags = ["-C", "link-arg=-fuse-ld=lld"]`，使用 `rust-lld` 链接器，避免 MSVC `link.exe` 触发 `STATUS_STACK_BUFFER_OVERRUN` (0xc0000409) 崩溃
  - [x] SubTask 2.2: 确认配置仅影响 Windows target，不影响 Linux/macOS 本地编译

- [x] Task 3: 新增 `.github/workflows/build-rust.yml` 自动编译流水线
  - [x] SubTask 3.1: 创建 `.github/workflows/` 目录与 `build-rust.yml` 文件，`name: Build xhsm Rust Extension`
  - [x] SubTask 3.2: 触发条件严格设为 `on: push: tags: ['v*']`，不含任何 `push.branches` / `pull_request` / `workflow_dispatch` / `schedule`（**核心约束**）
  - [x] SubTask 3.3: 全局 env：`EXTENSION_NAME: xhsm`、`RUST_TOOLCHAIN: stable`、`RUST_TOOLCHAIN_WINDOWS: nightly`
  - [x] SubTask 3.4: `build-linux` job — Ubuntu 24.04 × PHP 8.1~8.5 矩阵；安装 libclang-18-dev/clang-18；`cargo build --release`（**无** `--features php`，**无** `working-directory: rust`）；产物 `libxhsm.so` → `xhsm-rust-linux-php{ver}.so`；加载验证 `php -d extension=xhsm -r "echo xhsm_version();"`；运行 `tests/*.php`（去掉 socat/mock_server，直接 `php -d extension=xhsm` 遍历执行）；upload-artifact
  - [x] SubTask 3.5: `build-macos` job — macos-14 × PHP 8.1~8.5；brew install llvm 设置 CC/LIBCLANG_PATH；`cargo build --release`；产物 `libxhsm.dylib`/`.so` → `xhsm-rust-macos-php{ver}`；upload-artifact
  - [x] SubTask 3.6: `build-windows` job — windows-2022 × PHP 8.1~8.5 × {nts,ts}；nightly Rust + `x86_64-pc-windows-msvc`；KyleMayes/install-llvm-action LLVM 17 设 LIBCLANG_PATH；`cargo build --release`；产物 `xhsm.dll` → `xhsm-rust-windows-php{ver}-{ts}.dll`；upload-artifact
  - [x] SubTask 3.7: `lint-and-test` job — ubuntu-24.04；`cargo fmt -- --check` + `cargo clippy --all-targets -- -D warnings` + `cargo test --lib`（**均无** `--features php`，**无** `working-directory: rust`）；缓存 key 用 `hashFiles('Cargo.lock')`
  - [x] SubTask 3.8: `release` job — `if: startsWith(github.ref, 'refs/tags/v')`，`needs: [build-linux, build-macos, build-windows, lint-and-test]`，`permissions: contents: write`；download-artifact pattern `xhsm-rust-*`；softprops/action-gh-release@v2 创建 `xhsm {tag}` Release，body 改为 xhsm 产物说明，`files: release/*`

- [x] Task 4: 提交并推送到远程 main 主分支
  - [x] SubTask 4.1: 确认当前处于 `main` 分支（已确认），`git add` 上述新增/修改文件（`.github/workflows/build-rust.yml`、`src/lib.rs`、`.cargo/config.toml`）
  - [x] SubTask 4.2: `git commit` 提交，commit message 描述新增自动编译流水线
  - [x] SubTask 4.3: `git push origin main` 推送到远程主分支

# Task Dependencies
- [Task 3] 依赖 [Task 1] 与 [Task 2]（Windows job 需要 lib.rs attribute 与 .cargo/config.toml 才能编译）
- [Task 4] 依赖 [Task 1]、[Task 2]、[Task 3] 全部完成
- [Task 1] 与 [Task 2] 可并行
