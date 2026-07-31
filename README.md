# xhsm

xhsm 是一个基于 Rust（[ext-php-rs](https://github.com/phper-framework/ext-php-rs) 0.15）开发的 PHP 国密算法扩展，使用纯 Rust 实现国密 SM2/SM3/SM4/SM9 全套算法，**不依赖服务器系统内置的 OpenSSL**（`ldd libxhsm.so` 无 `libssl`/`libcrypto`）。

扩展加载后以 OOP 风格在 `Xhsm\` 命名空间下提供静态方法 API，覆盖非对称加解密、摘要、对称加解密、标识基算法、可扩展签名版本体系与业务场景预设，并附带 ThinkPHP 8 适配层（composer 包 `xhsm/thinkphp`）。

## 特性

- **纯 Rust 实现，无 OpenSSL 依赖**：所有密码学算法来自 Rust 生态 crate（smcrypto / RustCrypto sm3·sm4·ghash / sm9），构建产物不链接系统 `libssl`/`libcrypto`，部署环境无需安装 OpenSSL 开发库。
- **国密算法全覆盖**：SM2（非对称）、SM3（摘要 + HMAC）、SM4（ECB/CBC/CTR/GCM 对称）、SM9（标识基加解密与签名验签）。
- **可扩展签名版本体系**：内置 `s2`/`s3`/`s4` 三个签名版本（DER/RAW × hex/base64 组合），支持运行时 `register()` 注册自定义版本。
- **业务场景预设**：`Xhsm\Scenario\Finance`、`Payment`、`Government`、`MiniProgram` 四个场景类，按行业标准预配置签名与加密参数。
- **ThinkPHP 8 兼容**：通过 `xhsm/thinkphp` 提供 Manager、Facade、ServiceProvider 与配置发布命令，Wrapper 层做原始字符串↔hex 友好转换。
- **OOP 静态方法 API**：所有能力以 `Xhsm\Sm2`、`Xhsm\Sm3` 等类的静态方法暴露，统一异常类型 `Xhsm\Exception` 携带语义错误码。

## 环境要求

- **PHP >= 8.0**（需安装 PHP 开发头文件，`php-config` 在 `PATH` 中）
- **Rust** 工具链（`cargo`）
- **libclang**（ext-php-rs 的 bindgen 生成 FFI 绑定所需）
- 操作系统：Linux / Windows（macOS 理论可用但未专门测试）

## 构建与安装

### 1. 编译扩展

```bash
cargo build --release
```

产物路径：

- Linux：`target/release/libxhsm.so`
- Windows：`target/release/xhsm.dll`

### 2. 安装到 PHP

**方式 A：使用 cargo-php（推荐）**

```bash
cargo install cargo-php
cargo php install --release
```

`cargo-php` 会自动将扩展复制到 PHP 扩展目录并在 `php.ini` 注册 `extension=xhsm`。

**方式 B：手动加载**

将编译产物复制到 PHP 扩展目录，并在 `php.ini` 添加：

```ini
extension=xhsm
; 或使用绝对路径
; extension=/path/to/libxhsm.so
```

### 3. 验证加载

```bash
php -d extension=/path/to/libxhsm.so -r 'echo extension_loaded("xhsm") ? "OK" : "FAIL";'
# 预期输出：OK
```

也可调用冒烟函数确认版本：

```bash
php -d extension=/path/to/libxhsm.so -r 'echo xhsm_version();'
# 预期输出：0.1.0
```

### 4. 确认无 OpenSSL 依赖

```bash
ldd target/release/libxhsm.so | grep -E 'ssl|crypto'
# 预期：无输出（不依赖 libssl/libcrypto）
```

## 快速开始

以下示例演示 SM2 密钥对生成 → 签名验签 → 加密解密的完整流程。注意 `Xhsm\Sm2` 的 `sign`/`encrypt` 接收 **hex 编码** 的数据。

```php
<?php
// 1. 生成 SM2 密钥对
$pair = Xhsm\Sm2::generateKeyPair();
$privateKey = $pair['private_key']; // 64 hex 字符
$publicKey  = $pair['public_key'];  // 130 hex 字符（带 04 非压缩前缀）

// 2. 签名与验签（data 为 hex 编码）
$data   = bin2hex('hello xhsm'); // 原始字符串转 hex
$sig    = Xhsm\Sm2::sign($privateKey, $data, 'DER'); // 默认 DER
$ok     = Xhsm\Sm2::verify($publicKey, $data, $sig, 'DER');
var_dump($ok); // bool(true)

// 3. 加密与解密（data 为 hex 编码）
$ciphertext = Xhsm\Sm2::encrypt($publicKey, $data, 'C1C3C2'); // 默认 C1C3C2
$plaintext  = Xhsm\Sm2::decrypt($privateKey, $ciphertext, 'C1C3C2');
echo hex2bin($plaintext); // hello xhsm

// 4. SM3 摘要（直接接收原始字符串）
echo Xhsm\Sm3::hash('abc') . PHP_EOL;
// 66c7f0f462eeedd9d1f2d46bdc10e4e24167c4875cf2f7a2297da02b8f4ba8e0

// 5. SM4 对称加解密（key/iv/data 均为 hex）
$key = '0123456789abcdeffedcba9876543210';
$iv  = '000102030405060708090a0b0c0d0e0f';
$pt  = bin2hex('secret message');
$ct  = Xhsm\Sm4::encrypt($key, $iv, $pt, 'CBC'); // 默认 CBC
$dec = Xhsm\Sm4::decrypt($key, $iv, $ct, 'CBC');
echo hex2bin($dec); // secret message
```

## API 参考

所有类位于 `Xhsm\` 命名空间，方法均为静态方法。

### Xhsm\Sm2 —— SM2 非对称算法

| 方法 | 说明 |
| --- | --- |
| `generateKeyPair(): array` | 生成密钥对，返回 `['private_key' => hex(64字符), 'public_key' => hex(130字符, 带 04 前缀)]` |
| `encrypt(string $publicKey, string $data, string $mode = 'C1C3C2'): string` | 加密，`$data` 为 hex 明文，返回 hex 密文。`$mode`：`C1C3C2`（默认）/`C1C2C3`/`ASN1` |
| `decrypt(string $privateKey, string $data, string $mode = 'C1C3C2'): string` | 解密，`$data` 为 hex 密文，返回 hex 明文 |
| `sign(string $privateKey, string $data, string $format = 'DER'): string` | 签名，`$data` 为 hex 数据，返回 hex 签名。`$format`：`DER`（默认，ASN.1）/`RAW`（r‖s 共 64 字节） |
| `verify(string $publicKey, string $data, string $signature, string $format = 'DER'): bool` | 验签，参数编码与 `sign` 对应 |

公钥可带或不带 `04` 前缀；密钥均为 hex 字符串。

### Xhsm\Sm3 —— SM3 摘要

| 方法 | 说明 |
| --- | --- |
| `hash(string $data): string` | 计算 SM3 摘要，`$data` 为**原始字符串**，返回 64 字符 hex（32 字节） |
| `hmac(string $key, string $data): string` | 计算 HMAC-SM3，`$key` 与 `$data` 均为原始字符串，返回 64 字符 hex |

### Xhsm\Sm4 —— SM4 对称算法

| 方法 | 说明 |
| --- | --- |
| `encrypt(string $key, string $iv, string $data, string $mode = 'CBC', ?string $aad = null): string` | 加密，`$key`/`$iv`/`$data`/`$aad` 均为 hex，返回 hex 密文 |
| `decrypt(string $key, string $iv, string $data, string $mode = 'CBC', ?string $aad = null): string` | 解密，参数同 `encrypt`，返回 hex 明文 |

参数约定：

- `$key`：16 字节（32 hex 字符）
- `$iv`：CBC/CTR 为 16 字节，GCM 为 12 字节，ECB 忽略
- `$mode`：`ECB`/`CBC`（默认）/`CTR`/`GCM`
- `$aad`：仅 GCM 模式使用，附加认证数据（hex）
- GCM 模式输出为 **密文 ‖ 16 字节 Tag**，解密时输入同样需包含 Tag；Tag 校验失败抛 `Xhsm\Exception`（`ERR_DECODE`）

### Xhsm\Sm9 —— SM9 标识基算法

SM9 区分加密主密钥对与签名主密钥对，密钥以 hex 编码的 PEM 字符串表示。

| 方法 | 说明 |
| --- | --- |
| `generateMasterKeyPair(): array` | 生成主密钥对，返回含 `master_enc_private_key`/`master_enc_public_key`/`master_sig_private_key`/`master_sig_public_key` 四项（均 hex 编码 PEM） |
| `extractUserPrivateKey(string $masterPrivateKey, string $id, string $type = 'enc'): string` | 按标识抽取用户私钥。`type='enc'` 返回 hex(PEM)；`type='sig'` 返回捆绑格式 `hex(uspk):hex(mspk)`（签名需主签名公钥参与） |
| `encrypt(string $masterPublicKey, string $id, string $data): string` | 标识加密，`$masterPublicKey` 为加密主公钥，`$data` 为 hex 明文，返回 hex 密文 |
| `decrypt(string $userPrivateKey, string $id, string $data): string` | 标识解密，`$data` 为 hex 密文，返回 **UTF-8 原文字符串** |
| `sign(string $userPrivateKey, string $id, string $data, ?string $masterPublicKey = null): string` | 标识签名，`$data` 为 hex 数据，返回 hex 签名。`$userPrivateKey` 为捆绑格式时可省略第四参数，否则需提供 `$masterPublicKey` |
| `verify(string $masterPublicKey, string $id, string $data, string $signature): bool` | 标识验签 |

> 注意：SM9 `decrypt` 返回原始字符串（与 SM2 返回 hex 不同），因为 SM9 通常用于加密文本内容。

### Xhsm\Signature —— 可扩展签名版本体系

基于 SM2 构建，`$data` 为**原始字符串**（对其字节直接签名，与 `Sm2::sign` 接收 hex 不同）。

| 方法 | 说明 |
| --- | --- |
| `sign(string $version, string $privateKey, string $data): string` | 按版本签名，`$data` 为原始字符串，返回按版本 output 编码的签名串 |
| `verify(string $version, string $publicKey, string $data, string $signature): bool` | 按版本验签 |
| `register(string $version, array $config): void` | 注册自定义版本，重名抛异常 |
| `versions(): array` | 返回已注册版本名列表（含内置 s2/s3/s4） |
| `describe(string $version): array` | 返回版本配置（含 `algorithm`/`encoding`/`output`/`user_id`/`description`） |

### Xhsm\Scenario\* —— 业务场景预设

`Finance`、`Payment`、`Government`、`MiniProgram` 四个类，每个提供相同的方法签名，`$data` 均为**原始字符串**：

| 方法 | 说明 |
| --- | --- |
| `sign(string $privateKey, string $data): string` | 场景签名，输出格式由场景配置决定 |
| `verify(string $publicKey, string $data, string $signature): bool` | 场景验签 |
| `encrypt(string $publicKey, string $data): string` | SM2 加密（C1C3C2 + hex） |
| `decrypt(string $privateKey, string $data): string` | SM2 解密，`$data` 为 hex 密文，返回原始字符串明文 |
| `hash(string $data): string` | SM3 摘要（64 字符 hex） |
| `description(): string` | 返回场景业务标准描述 |

### Xhsm\Exception —— 异常

继承 PHP 内置 `\Exception`，作为扩展所有 Rust 层错误的统一异常类型。携带语义错误码常量：

| 常量 | 值 | 语义 |
| --- | --- | --- |
| `ERR_INVALID_FORMAT` | 1001 | 格式/编码错误（如 hex 解码失败） |
| `ERR_INVALID_KEY` | 1002 | 密钥无效 |
| `ERR_INVALID_PARAM` | 1003 | 参数非法（如不支持的 mode/format/version） |
| `ERR_DECODE` | 1004 | 解码/解析失败（DER 解析、UTF-8 还原、GCM Tag 校验失败） |
| `ERR_INTERNAL` | 1005 | 内部错误（如 SM9 crate panic、锁中毒） |
| `ERR_UNSUPPORTED` | 1006 | 不支持的算法/操作 |

```php
try {
    Xhsm\Sm4::encrypt('short', '', '00', 'ECB');
} catch (Xhsm\Exception $e) {
    echo $e->getCode();   // 错误码
    echo $e->getMessage(); // 错误消息
}
```

## 签名版本体系

`Xhsm\Signature` 通过版本配置组合出不同签名输出格式。内置三个版本：

| 版本 | algorithm | encoding | output | 说明 |
| --- | --- | --- | --- | --- |
| `s2` | SM2 | DER | hex | 经典 ASN.1 DER + hex 编码 |
| `s3` | SM2 | RAW | hex | 原始 r‖s + hex 编码 |
| `s4` | SM2 | DER | base64 | ASN.1 DER + base64 编码 |

> `user_id` 为保留字段（当前底层固定 `1234567812345678`），切换支持自定义 user_id 的 SM2 实现后即生效。

### 注册自定义版本

```php
<?php
// 注册一个 RAW + base64 的自定义版本 s5
Xhsm\Signature::register('s5', [
    'algorithm'   => 'SM2',      // 仅支持 SM2
    'encoding'    => 'RAW',      // DER / RAW
    'output'      => 'base64',   // hex / base64
    'user_id'     => '1234567812345678', // 保留字段
    'description' => '自定义 RAW + base64 版本',
]);

// 查询已注册版本
var_dump(Xhsm\Signature::versions());
// ["s2", "s3", "s4", "s5"]

// 查看版本配置
var_dump(Xhsm\Signature::describe('s5'));

// 使用自定义版本签名验签
$pair = Xhsm\Sm2::generateKeyPair();
$sig  = Xhsm\Signature::sign('s5', $pair['private_key'], 'hello');
var_dump(Xhsm\Signature::verify('s5', $pair['public_key'], 'hello', $sig)); // bool(true)
```

## 业务场景预设

四个场景类按行业标准预配置签名参数（加密统一使用 SM2 C1C3C2 + hex）：

| 场景类 | 签名 encoding | 签名 output | 业务标准 |
| --- | --- | --- | --- |
| `Xhsm\Scenario\Finance` | DER | hex | 金融行业标准（GB/T 32918 + ASN.1 DER） |
| `Xhsm\Scenario\Payment` | RAW | hex | 支付行业常用格式（RAW r‖s） |
| `Xhsm\Scenario\Government` | DER | hex | 政务 PKI 标准（ASN.1 DER） |
| `Xhsm\Scenario\MiniProgram` | DER | base64 | 小程序平台传输格式（DER + base64） |

```php
<?php
$pair = Xhsm\Sm2::generateKeyPair();

// 金融场景：DER + hex 签名
$sig = Xhsm\Scenario\Finance::sign($pair['private_key'], 'order payload');
var_dump(Xhsm\Scenario\Finance::verify($pair['public_key'], 'order payload', $sig)); // bool(true)

// 支付场景：RAW + hex 签名
$sig = Xhsm\Scenario\Payment::sign($pair['private_key'], 'payment data');
var_dump(Xhsm\Scenario\Payment::verify($pair['public_key'], 'payment data', $sig)); // bool(true)

// 小程序场景：DER + base64 签名
$sig = Xhsm\Scenario\MiniProgram::sign($pair['private_key'], 'mini payload');
var_dump(Xhsm\Scenario\MiniProgram::verify($pair['public_key'], 'mini payload', $sig)); // bool(true)

// 场景加密解密（统一 C1C3C2 + hex）
$ct = Xhsm\Scenario\Finance::encrypt($pair['public_key'], 'hello');
echo Xhsm\Scenario\Finance::decrypt($pair['private_key'], $ct); // hello

// 场景描述
echo Xhsm\Scenario\Finance::description();
// 金融行业标准（GB/T 32918 + ASN.1 DER）
```

## 小程序端验签

`Xhsm\Scenario\MiniProgram` 场景输出 **SM2 签名（ASN.1 DER 编码 + base64）**，`user_id` 固定为 `1234567812345678`。小程序端（微信 / 支付宝 / 字节等）可使用 [`sm-crypto`](https://github.com/JuneAndGreen/sm-crypto)（Node/浏览器）或 [`miniprogram-sm-crypto`](https://github.com/wechat-miniprogram/sm-crypto)（小程序专用，依赖小程序 npm 构建）验签。

### 参数对照

| 参数 | 后端 (xhsm) | 小程序端 (sm-crypto) |
| --- | --- | --- |
| 算法 | SM2 | SM2 |
| user_id | `1234567812345678`（smcrypto 固定） | `userId: '1234567812345678'`（sm-crypto 默认值，显式写出更清晰） |
| 杂凑 | SM3 + Z 值预处理（smcrypto 默认） | `hash: true`（sm-crypto 默认） |
| 签名编码 | DER（ASN.1 SEQUENCE） | `der: true` |
| 签名输出 | **base64** 字符串 | 需转为 **hex** 传入 sm-crypto |
| 公钥 | hex（130 字符，带 `04` 前缀） | hex（直接传入，sm-crypto 兼容带/不带 `04`） |

> 关键：两端 `user_id`、`hash`、DER 编码必须完全一致，否则验签失败。smcrypto (Rust) 与 sm-crypto (JS) 均遵循 GB/T 32918 标准，Z 值预处理与 SM3 流程互通。

### 1. 后端：生成签名（PHP）

```php
<?php
// 1. 生成密钥对（私钥后端留存，公钥下发小程序）
$pair = Xhsm\Sm2::generateKeyPair();
$privateKey = $pair['private_key']; // 64 hex 字符（后端保密）
$publicKey  = $pair['public_key'];  // 130 hex 字符（下发给小程序）

// 2. 对业务数据签名（DER + base64）
$data = 'mini payload'; // 业务数据，原始字符串
$sig  = Xhsm\Scenario\MiniProgram::sign($privateKey, $data);
// $sig 形如 "MEUCIQD...=="（base64）

// 3. 把 publicKey 与 sig 返回给小程序（经 JSON 接口）
echo json_encode([
    'public_key' => $publicKey,
    'data'       => $data,
    'signature'  => $sig,
]);
```

### 2. 小程序端：验签（JS）

安装依赖（在小程序项目根目录）：

```bash
# 微信小程序（推荐 miniprogram-sm-crypto，需在开发者工具「工具 → 构建 npm」）
npm install --save miniprogram-sm-crypto

# 或标准 sm-crypto（适用于 Taro/uni-app 等基于构建工具的小程序框架）
npm install --save sm-crypto
```

验签代码：

```js
// utils/sm2-verify.js
// 引入 sm-crypto（按需调整 require 路径与包名）
const sm2 = require('miniprogram-sm-crypto').sm2
// const sm2 = require('sm-crypto').sm2  // Taro/uni-app 等

/**
 * base64 字符串转 hex 字符串（纯 JS 实现，跨小程序平台通用）
 * sm-crypto 的 doVerifySignature 接收 hex 签名，而后端输出 base64，需先转换
 */
function base64ToHex(base64) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
  const lookup = {}
  for (let i = 0; i < chars.length; i++) lookup[chars[i]] = i

  base64 = base64.replace(/=+$/, '') // 去除 padding
  const bytes = []
  let buffer = 0
  let bits = 0

  for (let i = 0; i < base64.length; i++) {
    const val = lookup[base64[i]]
    if (val === undefined) continue
    buffer = (buffer << 6) | val
    bits += 6
    if (bits >= 8) {
      bits -= 8
      bytes.push((buffer >> bits) & 0xff)
      buffer &= (1 << bits) - 1 // 清除已输出位，防止长签名累积溢出 JS 安全整数精度
    }
  }

  return bytes
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('')
}

/**
 * 验证 xhsm MiniProgram 场景签名
 * @param {string} msg       原始业务数据（须与后端签名时的 data 完全一致，UTF-8 字节）
 * @param {string} sigBase64 后端返回的 base64 签名（DER 编码）
 * @param {string} publicKey hex 公钥（后端返回，带或不带 04 前缀均可）
 * @returns {boolean} true 表示验签通过
 */
function verifyMiniProgramSignature(msg, sigBase64, publicKey) {
  const sigHex = base64ToHex(sigBase64)
  return sm2.doVerifySignature(msg, sigHex, publicKey, {
    hash: true,                    // SM3 杂凑（含 Z 值预处理），与后端一致（sm-crypto 默认 true）
    der: true,                     // 签名为 ASN.1 DER，库内部解析 r/s
    userId: '1234567812345678',    // 与后端 smcrypto 固定值一致
  })
}

module.exports = { verifyMiniProgramSignature, base64ToHex }
```

调用示例：

```js
// pages/index/index.js
const { verifyMiniProgramSignature } = require('../../utils/sm2-verify.js')

// 假设 wx.request 从后端拿到如下数据
const res = {
  public_key: '049d4f8b...（130 hex 字符）',
  data: 'mini payload',
  signature: 'MEUCIQD...==',
}

const ok = verifyMiniProgramSignature(res.data, res.signature, res.public_key)
console.log(ok) // true 表示验签通过
if (ok) {
  wx.showToast({ title: '验签通过' })
} else {
  wx.showToast({ title: '验签失败', icon: 'error' })
}
```

### 3. 联调测试

两端联调前，建议先在本地验证签名互通性：

```php
<?php
// 后端打印一组测试向量（私钥可留作联调样本）
$pair = Xhsm\Sm2::generateKeyPair();
$data = 'mini payload';
$sig  = Xhsm\Scenario\MiniProgram::sign($pair['private_key'], $data);

echo "public_key: " . $pair['public_key'] . "\n";
echo "data:       " . $data . "\n";
echo "signature:  " . $sig . "\n";
echo "verify:     " . (Xhsm\Scenario\MiniProgram::verify($pair['public_key'], $data, $sig) ? 'true' : 'false') . "\n";
```

把输出的 `public_key` / `data` / `signature` 三项填入小程序端 `verifyMiniProgramSignature()`，应返回 `true`。若返回 `false`，按以下顺序排查：

1. **`msg` 不一致**：小程序端传入的字符串须与后端签名时的 `$data` 的 UTF-8 字节完全相同（注意中英文、空格、换行）。
2. **`publicKey` 被截断/转大小写**：hex 公钥保持原样传入，勿去掉 `04` 前缀（sm-crypto 兼容带/不带，但长度必须正确）。
3. **`userId` 被改**：必须为 `1234567812345678`，与后端 smcrypto 固定值一致。
4. **`der` / `hash` 漏设**：`der: true` 和 `hash: true` 缺一不可（`hash` 默认 true，但显式写出更安全）。
5. **base64 → hex 转换错误**：用一组已知 base64（如 `MEUCIQD...==`）对照在线工具验证 `base64ToHex` 输出。

## ThinkPHP 8 集成

`xhsm/thinkphp`（位于 `php/` 目录）提供 ThinkPHP 8 适配层，Wrapper 类对扩展层 API 做原始字符串↔hex 友好转换，让你可以直观地 `Xhsm::sm2()->sign($privateKey, 'hello')`。

### 1. 安装

```bash
composer require xhsm/thinkphp
```

### 2. 发布配置

```bash
php think xhsm:publish
# 强制覆盖已存在配置
# php think xhsm:publish --force
```

发布后的配置文件 `config/xhsm.php`：

```php
return [
    'default_signature_version' => 's2',
    'default_scenario'          => 'finance',
    'sm2' => ['mode' => 'C1C3C2'],
    'sm4' => ['mode' => 'CBC'],
    'keys' => [],
];
```

### 3. 使用 Facade

```php
<?php
use Xhsm\Think\Facades\Xhsm;

// SM2：Wrapper 自动将原始字符串转 hex，签名/加密返回 hex，解密返回原始字符串
$pair = Xhsm::sm2()->generateKeyPair();
$sig  = Xhsm::sm2()->sign($pair['private_key'], 'hello'); // 直接传原始字符串
var_dump(Xhsm::sm2()->verify($pair['public_key'], 'hello', $sig)); // bool(true)

$ct = Xhsm::sm2()->encrypt($pair['public_key'], 'secret');
echo Xhsm::sm2()->decrypt($pair['private_key'], $ct); // secret

// SM3 / SM4 / SM9 同理
echo Xhsm::sm3()->hash('abc');
echo Xhsm::sm4()->encrypt($key, $iv, 'plaintext', 'CBC');

// Signature 版本体系
$sig = Xhsm::signature()->sign('s2', $pair['private_key'], 'hello');

// Scenario 业务场景（默认 finance，可传参切换）
$sig = Xhsm::scenario('payment')->sign($pair['private_key'], 'payment data');
$sig = Xhsm::scenario()->sign($pair['private_key'], 'finance data'); // 使用 default_scenario
```

> Wrapper 层约定：`sm2()->sign/encrypt` 接收原始字符串（内部 `bin2hex`），`sm2()->decrypt` 返回原始字符串明文；`sm3`/`sm4`/`sm9`/`signature`/`scenario` 直接透传扩展层语义。

## 测试向量

### SM3 摘要

| 输入 | SM3 hex |
| --- | --- |
| `"abc"`（GB/T 32905-2016 附录 A） | `66c7f0f462eeedd9d1f2d46bdc10e4e24167c4875cf2f7a2297da02b8f4ba8e0` |
| `""`（空字符串） | `1ab21d8355cfa17f8e61194831e81a8f22bec8c728fefb747ed035eb5082aa2b` |

### SM4 对称加密（GB/T 32907-2016）

| 模式 | 密钥（hex） | 明文（hex） | 密文（hex，首块） |
| --- | --- | --- | --- |
| ECB | `0123456789abcdeffedcba9876543210` | `0123456789abcdeffedcba9876543210` | `681edf34d206965e86b3e94f536e4246` |

> 注：ECB/CBC 模式使用 PKCS#7 填充，明文恰好为 16 字节单块时会补一整块填充，因此完整密文为 32 字节，上表给出第一块（与标准单块加密向量一致）。

### SM2 / SM9

SM2 与 SM9 含随机数，主要通过加解密、签名验签往返测试验证（见 `tests/sm2.php`、`tests/sm9.php`）。

## 测试

测试脚本位于 `tests/` 目录，每个脚本是一个独立可运行的 PHP 文件，内部使用 `assert_eq`/`assert_true`/`assert_false` 断言函数（见 `tests/assert.php`），失败时输出差异并 `exit(1)`。

运行单个测试（通过 `-d extension=` 加载扩展）：

```bash
php -d extension=/path/to/libxhsm.so tests/sm3.php
php -d extension=/path/to/libxhsm.so tests/sm4.php
php -d extension=/path/to/libxhsm.so tests/sm2.php
php -d extension=/path/to/libxhsm.so tests/sm9.php
php -d extension=/path/to/libxhsm.so tests/signature.php
php -d extension=/path/to/libxhsm.so tests/scenario.php
php -d extension=/path/to/libxhsm.so tests/exception.php
php -d extension=/path/to/libxhsm.so tests/thinkphp_adapter.php
```

若已在 `php.ini` 注册扩展，则可直接 `php tests/sm3.php`。

一键运行全部测试：

```bash
for t in tests/sm3.php tests/sm4.php tests/sm2.php tests/sm9.php tests/signature.php tests/scenario.php tests/exception.php tests/thinkphp_adapter.php; do
    php -d extension=target/release/libxhsm.so "$t" || exit 1
done
```

## 许可证

[MIT License](LICENSE) © 2026 小胡
