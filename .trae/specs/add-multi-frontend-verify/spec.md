# 多端验签优化 Spec

## Why
当前 `xhsm` 仅 `MiniProgram` 场景有前端验签文档，且前端需手写 `base64ToHex` 转换。生产环境面对**小程序 / uniapp / Web 三端** × **s2/s3/s4 三版本 + 四场景**，缺乏统一方案：每端重复实现转换逻辑、各格式对 JS 端友好度差异未说明、无官方前端工具包。需补齐后端场景预设、提供官方前端 npm 包、并完整文档化全部格式 × 多端的验签方案。

## What Changes
- **后端新增 2 个场景预设**（覆盖全部 4 种签名格式组合）：
  - `Xhsm\Scenario\Uniapp`：RAW + hex（sm-crypto 默认格式，uniapp 端零转换最友好）
  - `Xhsm\Scenario\Web`：RAW + base64（补全第四种组合，体积小）
  - 现有 4 场景 + 新增 2 场景 = 6 场景，覆盖 DER/RAW × hex/base64 全部 4 组合（Finance/Government=DER+hex、Payment=RAW+hex、MiniProgram=DER+base64、Uniapp=RAW+hex 备选、Web=RAW+base64）
- **新增前端 npm 工具包 `xhsm-verify`**（位于 `js/xhsm-verify/`）：
  - 核心工具：`base64ToHex` / `hexToBase64`（纯 JS，跨端通用，含防溢出）
  - 统一 API：`verifySignature(msg, sig, publicKey, options)`，options 指定 `format: 'der-hex'|'raw-hex'|'der-base64'|'raw-base64'`，内部自动转换并调用底层 sm-crypto
  - 多端入口：`xhsm-verify/miniprogram`（依赖 `miniprogram-sm-crypto`）、`xhsm-verify/web`（依赖 `sm-crypto`），共用核心逻辑
  - TypeScript 类型声明（.d.ts）
  - 单元测试（Node 环境，对照已知向量）
- **更新 README**：
  - 新增「多端验签总览」章节：全 4 种格式 × 3 端的对照表 + 选型建议
  - 各端验签代码示例（小程序 / uniapp / Web）
  - `xhsm-verify` npm 包使用说明
  - 更新「业务场景预设」章节加入 Uniapp/Web
- **测试**：扩展 `tests/scenario.php` 覆盖新场景；npm 包独立测试
- **提交**：本地 main 提交后推送到远程 main

## Impact
- Affected specs: `add-xhsm-extension`（扩展场景预设能力）
- Affected code:
  - 修改 `src/scenario.rs`（新增 Uniapp/Web 场景类）
  - 修改 `src/lib.rs`（注册新场景类到 module）
  - 修改 `tests/scenario.php`（新增场景测试）
  - 修改 `README.md`（多端验签文档）
  - 新增 `js/xhsm-verify/`（npm 包源码、测试、package.json、.d.ts）
- 运行环境：PHP 8.0+（后端）、Node.js 14+（前端包构建测试）
- **非 BREAKING**：现有 4 场景与签名版本不变，仅新增

## ADDED Requirements

### Requirement: 新增 Uniapp 与 Web 场景预设
系统 SHALL 在 `Xhsm\Scenario\` 命名空间下新增 `Uniapp`（RAW + hex）与 `Web`（RAW + base64）两个场景预设类，提供与现有场景一致的 sign/verify/encrypt/decrypt/hash/description 静态方法。

#### Scenario: Uniapp 场景签名格式
- **WHEN** 调用 `Xhsm\Scenario\Uniapp::sign($sk, $data)`
- **THEN** 返回 RAW(r‖s 64字节) + hex(128字符) 签名串，可被 sm-crypto `doVerifySignature(msg, sigHex, pk, {hash:true, userId:'1234567812345678'})` 直接验签（无需 der/base64 转换）

#### Scenario: Web 场景签名格式
- **WHEN** 调用 `Xhsm\Scenario\Web::sign($sk, $data)`
- **THEN** 返回 RAW + base64 签名串，前端 base64→hex 后可被 sm-crypto 验签

#### Scenario: 全格式覆盖
- **WHEN** 查看六场景配置
- **THEN** 覆盖 DER+hex / RAW+hex / DER+base64 / RAW+base64 全部 4 种组合

### Requirement: 前端 npm 工具包 xhsm-verify
系统 SHALL 提供前端 npm 包 `xhsm-verify`，封装签名格式转换与多端验签，前端一行引入即可验证任意 xhsm 签名。

#### Scenario: 统一验签 API
- **WHEN** 前端调用 `verifySignature(msg, sig, publicKey, { format: 'der-base64' })`
- **THEN** 内部自动 base64→hex + der 解析，调用 sm-crypto 验签，返回 bool

#### Scenario: 多端入口
- **WHEN** 小程序引入 `xhsm-verify/miniprogram`、Web 引入 `xhsm-verify/web`
- **THEN** 各自加载对应 sm-crypto 依赖，API 一致

### Requirement: 多端验签文档
README SHALL 包含「多端验签总览」章节，以对照表列出全 4 种签名格式 × 3 端（小程序/uniapp/Web）的验签方式与选型建议，并附各端完整代码示例。

#### Scenario: 文档完整性
- **WHEN** 开发者查阅 README
- **THEN** 能找到 s2/s3/s4 + 六场景的格式说明、各端验签代码、xhsm-verify 用法

## MODIFIED Requirements
无（仅新增，不改现有功能）。

## REMOVED Requirements
无。
