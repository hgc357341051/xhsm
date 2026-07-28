# xhsm 国密算法 PHP 扩展 Spec

## Why
当前 PHP 生态缺乏一个统一的、高性能且不依赖系统 openssl 的国密算法扩展。xhsm 旨在基于 Rust（ext-php-rs 0.15）构建一个支持 SM2/SM3/SM4/SM9 国密算法、可扩展签名版本（s2/s3/s4…）、面向多业务场景（金融/支付/政府/小程序）并兼容 ThinkPHP 8 框架的 PHP 扩展，解决金融与政务系统中加解密/签名验签的标准化与互操作问题。

## What Changes
- 新增基于 Rust + ext-php-rs 0.15.x 构建的 PHP 扩展 `xhsm`（产物 `xhsm.so` / `xhsm.dll`）
- 新增 SM2 非对称算法支持：密钥对生成、加解密（C1C2C3 / C1C3C2）、签名/验签、公钥压缩
- 新增 SM3 密码摘要算法支持：哈希计算、HMAC-SM3
- 新增 SM4 对称算法支持：ECB / CBC / CTR / GCM 四种工作模式
- 新增 SM9 标识基非对称算法支持：主密钥生成、私钥抽取、加解密、签名/验签
- 新增可扩展签名版本体系：内置 s2 / s3 / s4 三个版本，支持注册自定义版本（s5、s6…）
- 新增业务场景预设：金融 / 支付 / 政府 / 小程序 四类预设封装
- 新增 OOP 命名空间类 API：`Xhsm\Sm2`、`Xhsm\Sm3`、`Xhsm\Sm4`、`Xhsm\Sm9`、`Xhsm\Signature`、`Xhsm\Scenario\*`、`Xhsm\Exception`
- 新增 ThinkPHP 8 适配层：ServiceProvider、Facade、配置发布
- **BREAKING**：无（全新项目）

## Impact
- Affected specs: 无（首版）
- Affected code:
  - 新增 `Cargo.toml`、`src/lib.rs` 及各算法模块（`sm2.rs` / `sm3.rs` / `sm4.rs` / `sm9.rs` / `signature/` / `scenario/` / `exception.rs`）
  - 新增 `php/` PHP 适配层（composer 包，含 ThinkPHP 8 ServiceProvider/Facade/Config）
  - 新增构建脚本与 `cargo-php` 集成
  - 依赖：`ext-php-rs = "0.15"`、纯 Rust 国密 crate（候选 `smcrypto` / `sm2` / `sm3` / `sm4` / `sm9`，最终选型在实现阶段确认）
  - 运行环境：PHP >= 8.0（ext-php-rs 0.15 最低要求）

## ADDED Requirements

### Requirement: 扩展基础架构
系统 SHALL 基于 Rust + ext-php-rs 0.15.x 构建 PHP 扩展 `xhsm`，通过 `#[php_module]` 宏注册模块，所有功能以 PHP 命名空间类形式暴露于 `Xhsm\` 命名空间下。

#### Scenario: 扩展加载
- **WHEN** 用户在 `php.ini` 中配置 `extension=xhsm` 并启动 PHP
- **THEN** 扩展成功加载，`extension_loaded('xhsm')` 返回 `true`
- **AND** `Xhsm\Sm2`、`Xhsm\Sm3`、`Xhsm\Sm4`、`Xhsm\Sm9`、`Xhsm\Signature` 等类可被 `class_exists` 检测到

#### Scenario: PHP 版本兼容
- **WHEN** 扩展在 PHP 8.0+ 环境加载
- **THEN** 扩展正常工作（ext-php-rs 0.15 要求 PHP >= 8.0）

### Requirement: 不依赖系统 OpenSSL
系统 SHALL 仅使用纯 Rust 密码学 crate 实现全部国密算法，扩展产物 SHALL NOT 链接系统 libssl / libcrypto。

#### Scenario: 静态自包含
- **WHEN** 在未安装 openssl 开发库的系统上加载扩展
- **THEN** 扩展加载与所有加解密功能正常
- **AND** `ldd xhsm.so`（Linux）输出中不出现 libssl / libcrypto 依赖

### Requirement: SM2 非对称算法
系统 SHALL 提供 `Xhsm\Sm2` 类，支持密钥对生成、非对称加解密、数字签名与验签。

#### Scenario: 密钥对生成
- **WHEN** 调用 `Xhsm\Sm2::generateKeyPair()`
- **THEN** 返回包含 `private_key` 与 `public_key` 的结构
- **AND** 公钥支持压缩与非压缩两种格式输出

#### Scenario: 加解密（C1C3C2 默认）
- **WHEN** 调用 `Xhsm\Sm2::encrypt($publicKey, $plaintext)` 后再 `Xhsm\Sm2::decrypt($privateKey, $ciphertext)`
- **THEN** 解密结果与原文一致
- **AND** 密文默认采用 GB/T 32918 标准的 C1C3C2 顺序

#### Scenario: 加解密（C1C2C3）
- **WHEN** 调用 `encrypt` 时指定模式为 C1C2C3
- **THEN** 密文按 C1C2C3 顺序输出，可被对应 `decrypt` 还原

#### Scenario: 签名与验签
- **WHEN** 调用 `Xhsm\Sm2::sign($privateKey, $data)` 生成签名
- **AND** 调用 `Xhsm\Sm2::verify($publicKey, $data, $signature)` 验签
- **THEN** 验签返回 `true`
- **AND** 签名支持 ASN.1 DER 与原始 hex 两种编码

### Requirement: SM3 密码摘要
系统 SHALL 提供 `Xhsm\Sm3` 类，支持 SM3 摘要计算与 HMAC-SM3。

#### Scenario: 摘要计算
- **WHEN** 调用 `Xhsm\Sm3::hash($data)`
- **THEN** 返回 32 字节摘要的 hex 字符串（64 字符）

#### Scenario: HMAC-SM3
- **WHEN** 调用 `Xhsm\Sm3::hmac($key, $data)`
- **THEN** 返回基于 SM3 的 HMAC 结果

### Requirement: SM4 对称算法
系统 SHALL 提供 `Xhsm\Sm4` 类，支持 ECB / CBC / CTR / GCM 四种工作模式。

#### Scenario: CBC 加解密
- **WHEN** 调用 `Xhsm\Sm4::encrypt($key, $iv, $data, 'CBC')` 与对应 `decrypt`
- **THEN** 解密结果与原文一致，默认 PKCS#7 填充

#### Scenario: GCM 认证加密
- **WHEN** 使用 GCM 模式并提供 AAD
- **THEN** 输出包含密文与认证 Tag，解密时校验 Tag

### Requirement: SM9 标识基算法
系统 SHALL 提供 `Xhsm\Sm9` 类，支持主密钥生成、用户私钥抽取、加解密与签名验签。

#### Scenario: 标识加解密
- **WHEN** 生成主密钥对后，按标识 `$id` 抽取用户私钥
- **AND** 用主公钥与 `$id` 加密，用用户私钥解密
- **THEN** 解密结果与原文一致

#### Scenario: 标识签名
- **WHEN** 用用户私钥对数据签名，用 `$id` 与主公钥验签
- **THEN** 验签返回 `true`

### Requirement: 可扩展签名版本体系
系统 SHALL 提供可扩展的签名版本管理，内置 s2 / s3 / s4 三个版本，并允许通过注册机制新增版本（s5、s6…）。

每个签名版本 SHALL 定义以下可配置项：
- 使用的底层算法（SM2 / SM2+SM3 等）
- 密文/签名输出格式（C1C2C3 / C1C3C2 / ASN.1 DER）
- 编码方式（hex / base64 / raw binary）
- 默认用户 ID（默认 `1234567812345678`）
- 其他业务参数

#### Scenario: 内置版本调用
- **WHEN** 调用 `Xhsm\Signature::sign('s3', $privateKey, $data)`
- **THEN** 按 s3 版本定义的算法与格式输出签名

#### Scenario: 版本扩展
- **WHEN** 调用 `Xhsm\Signature::register('s5', $config)` 注册新版本
- **AND** 随后调用 `Xhsm\Signature::sign('s5', ...)`
- **THEN** 使用新注册的配置完成签名

#### Scenario: 版本列表
- **WHEN** 调用 `Xhsm\Signature::versions()`
- **THEN** 返回当前已注册的所有签名版本标识

### Requirement: 业务场景预设
系统 SHALL 提供面向典型业务场景的预设封装类，位于 `Xhsm\Scenario\` 命名空间下，至少包含：
- `Xhsm\Scenario\Finance`（金融）
- `Xhsm\Scenario\Payment`（支付）
- `Xhsm\Scenario\Government`（政府）
- `Xhsm\Scenario\MiniProgram`（小程序）

每个预设 SHALL 根据对应业务标准预配置签名版本、密文格式、编码、用户 ID 等。

#### Scenario: 金融场景
- **WHEN** 调用 `Xhsm\Scenario\Finance::sign($privateKey, $data)`
- **THEN** 按金融行业标准（如 GB/T 32918 + ASN.1 DER）输出签名

#### Scenario: 小程序场景
- **WHEN** 调用 `Xhsm\Scenario\MiniProgram::encrypt($publicKey, $data)`
- **THEN** 按主流小程序平台约定格式输出密文

### Requirement: 异常处理
系统 SHALL 提供 `Xhsm\Exception` 异常类，所有 Rust 层错误 SHALL 转换为该异常抛出，保留错误码与错误消息。

#### Scenario: 错误抛出
- **WHEN** 传入非法密钥或数据导致 Rust 层返回错误
- **THEN** PHP 层抛出 `Xhsm\Exception`，包含错误码与可读消息

### Requirement: ThinkPHP 8 兼容
系统 SHALL 提供 ThinkPHP 8 适配层（PHP composer 包），包含 ServiceProvider、Facade 与配置文件发布能力。

#### Scenario: 服务注册
- **WHEN** 在 ThinkPHP 8 项目中通过 composer 安装适配包并发布配置
- **THEN** 可通过 `Xhsm::sm2()->sign(...)` 或 Facade 调用扩展能力

#### Scenario: 配置发布
- **WHEN** 执行 `php think xhsm:publish`
- **THEN** 在 `config/xhsm.php` 生成默认配置（默认签名版本、场景、密钥路径等）

### Requirement: 构建与分发
系统 SHALL 提供标准 Cargo 构建流程，并集成 `cargo-php` 工具支持扩展安装。

#### Scenario: 构建扩展
- **WHEN** 执行 `cargo build --release`
- **THEN** 在 `target/release/` 生成可加载的扩展产物

#### Scenario: 安装扩展
- **WHEN** 执行 `cargo php install`
- **THEN** 扩展被自动安装到 PHP 扩展目录并写入 `php.ini`
