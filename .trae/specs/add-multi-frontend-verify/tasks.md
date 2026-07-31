# Tasks

- [x] Task 1: 后端新增 Uniapp 与 Web 场景预设
  - [x] SubTask 1.1: 在 `src/scenario.rs` 新增 `UNIAPP_CONFIG`（encoding=RAW, output=hex, description="uniapp 跨端格式（RAW r||s + hex，sm-crypto 默认零转换）"）与 `WEB_CONFIG`（encoding=RAW, output=base64, description="Web 端格式（RAW r||s + base64，体积小）"）常量
  - [x] SubTask 1.2: 在 `src/scenario.rs` 新增 `Uniapp` 与 `Web` 两个 `#[php_class]` 类（命名空间 `Xhsm\Scenario\Uniapp` / `Xhsm\Scenario\Web`），实现 sign/verify/encrypt/decrypt/hash/description 六个静态方法，复用现有 `scenario_sign/verify/encrypt/decrypt/hash` 辅助函数（参考现有 Finance/Payment 类写法）
  - [x] SubTask 1.3: 在 `src/lib.rs` 的 `get_module` 中注册 `Uniapp` 与 `Web` 两个类（`.class::<Uniapp>()` / `.class::<Web>()`），并在文件顶部 `use scenario::{...}` 中导入这两个类型
  - [x] SubTask 1.4: 本地 `cargo build --release` 验证编译通过（若环境无 PHP 头文件，至少 `cargo check` 通过）

- [x] Task 2: 扩展后端场景测试
  - [x] SubTask 2.1: 在 `tests/scenario.php` 末尾（异常处理前）新增 Uniapp 与 Web 场景测试段：description 校验、sign→verify round-trip、格式特征校验（Uniapp 128 hex 字符；Web base64 解码后 64 字节）、encrypt→decrypt round-trip、hash 一致性、篡改验签失败、与 Payment(RAW+hex) 交叉验签（Uniapp 同 RAW+hex 应互通）
  - [x] SubTask 2.2: 本地用 `php -d extension=target/release/libxhsm.so tests/scenario.php` 运行通过（若无编译产物，至少代码 review 确认逻辑正确）

- [x] Task 3: 新增前端 npm 包 xhsm-verify 骨架
  - [x] SubTask 3.1: 创建 `js/xhsm-verify/` 目录结构：`package.json`（name=xhsm-verify, version=0.1.0, main=index.js, types=index.d.ts, exports 含 ./miniprogram 与 ./web 子路径）、`README.md`（简短说明）、`.gitignore`（node_modules）
  - [x] SubTask 3.2: `package.json` 中 `miniprogram-sm-crypto` 与 `sm-crypto` 声明为 peerDependencies（由用户按端安装），避免重复打包

- [x] Task 4: 实现 xhsm-verify 核心逻辑
  - [x] SubTask 4.1: 实现 `js/xhsm-verify/src/codec.js`：`base64ToHex(base64)`（纯 JS，含防溢出 buffer &= (1<<bits)-1）与 `hexToBase64(hex)`，附 JSDoc
  - [x] SubTask 4.2: 实现 `js/xhsm-verify/src/verify.js`：`verifySignature(msg, sig, publicKey, options)`，options 含 `format: 'der-hex'|'raw-hex'|'der-base64'|'raw-base64'`（必填）、`userId`（默认 '1234567812345678'）。内部按 format 决定是否 base64→hex，按 encoding 决定 der:true，调用注入的 sm2.doVerifySignature。导出工厂函数 `createVerify(sm2)` 注入底层 sm-crypto
  - [x] SubTask 4.3: 实现多端入口 `js/xhsm-verify/miniprogram.js`（require miniprogram-sm-crypto 并注入）与 `js/xhsm-verify/web.js`（require sm-crypto 并注入），均导出 verifySignature + base64ToHex + hexToBase64
  - [x] SubTask 4.4: 编写 `js/xhsm-verify/index.d.ts` TypeScript 类型声明

- [x] Task 5: xhsm-verify 单元测试
  - [x] SubTask 5.1: 创建 `js/xhsm-verify/test/codec.test.js`：测试 base64ToHex 与 hexToBase64 互逆性、边界（空串、1字节、padding）、与 Node Buffer 对照
  - [x] SubTask 5.2: 创建 `js/xhsm-verify/test/verify.test.js`：用一组后端生成的真实签名向量（der-hex/raw-hex/der-base64/raw-base64 各一组，可用 PHP 脚本生成）验证 verifySignature 四种 format 全部返回 true；篡改数据返回 false
  - [x] SubTask 5.3: `package.json` 加 `scripts.test: "node test/codec.test.js && node test/verify.test.js"`，本地 `npm test` 通过（需先 `npm install sm-crypto` 作为 devDependency 供测试）

- [x] Task 6: 更新 README 多端验签文档
  - [x] SubTask 6.1: 在「业务场景预设」章节的场景表与代码示例中加入 Uniapp（RAW+hex）与 Web（RAW+base64）两行
  - [x] SubTask 6.2: 新增「多端验签总览」章节（替换或扩展现有「小程序端验签」章节），含：① 全 4 格式 × 3 端对照表（格式/体积/前端转换/推荐场景）；② 选型建议（小程序/uniapp 推 RAW+hex 零转换，Web 推 RAW+base64 体积小，需 ASN.1 标准用 DER）；③ xhsm-verify npm 包用法（安装 + 各端引入 + verifySignature 示例）；④ 各端原生 sm-crypto 验签代码（小程序/uniapp/Web 三段，覆盖 RAW+hex 与 DER+base64 两种典型格式）
  - [x] SubTask 6.3: 保留联调测试与排查清单，更新为覆盖多格式

- [x] Task 7: 提交并推送到远程 main
  - [x] SubTask 7.1: `git add` 全部改动（src/scenario.rs, src/lib.rs, tests/scenario.php, README.md, js/xhsm-verify/）
  - [x] SubTask 7.2: `git commit` 提交，message 描述新增多端场景预设 + xhsm-verify 包 + 文档
  - [x] SubTask 7.3: `git push origin main` 推送到远程主分支

# Task Dependencies
- [Task 2] 依赖 [Task 1]
- [Task 4] 依赖 [Task 3]
- [Task 5] 依赖 [Task 4]，且 [Task 5.2] 的真实签名向量依赖 [Task 1] 完成后用 PHP 生成
- [Task 6] 依赖 [Task 1]（场景名）与 [Task 4]（npm 包 API）
- [Task 7] 依赖 [Task 1]~[Task 6] 全部完成
- 可并行：[Task 1] 与 [Task 3]（后端与前端骨架独立）
