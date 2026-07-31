# xhsm-verify

xhsm 国密扩展前端验签工具包：封装 SM2 签名格式转换与多端验签，配合 PHP [xhsm](../..) 扩展使用。

## 包用途

PHP xhsm 扩展在服务端使用 SM2/SM3/SM4 等国密算法生成签名与密文，前端在验签时需要：

1. 将后端返回的 DER / Base64 签名格式转换为 `sm-crypto` 所需的 `r || s` 拼接 hex；
2. 调用对应端的国密库完成验签。

本包封装上述逻辑，提供统一的 `verifySignature` 入口，屏蔽多端差异。

## 与 PHP xhsm 扩展的关系

- 后端：PHP xhsm 扩展负责生成 SM2 签名、密钥对、加密解密等；
- 前端：本包负责接收后端产出的签名（默认 DER 编码、Base64 传输），转换后调用 `sm-crypto` 完成验签；
- 签名格式、公钥格式需与后端约定保持一致，详见主仓库 README。

## 安装

```bash
npm install xhsm-verify
```

按使用端安装对应的 peer 依赖（二选一，或按需安装）：

```bash
# Web / Node 端
npm install sm-crypto

# 小程序端
npm install miniprogram-sm-crypto
```

## 最小使用示例

```js
const { verifySignature } = require('xhsm-verify/web');

const ok = verifySignature({
  data: 'hello',            // 待验证明文（字符串或 Buffer）
  sig: 'MFYwEAYHKoZIzj0C...', // 后端返回的 Base64 签名（DER 编码）
  publicKey: '04xxxx...',     // SM2 公钥 hex（不带 04 也可）
  format: 'der-base64',       // 签名格式：der-base64 | der-hex | rs-hex
  // hash: true,              // 是否对 data 做 SM3 摘要，默认按后端约定
  // userId: '1234567812345678' // SM2 默认 userId
});

console.log(ok ? '验签通过' : '验签失败');
```

## 多端入口

| 入口 | 适用端 | 底层依赖 |
| --- | --- | --- |
| `xhsm-verify` | Node 通用入口 | `sm-crypto` |
| `xhsm-verify/web` | 浏览器 / Node | `sm-crypto` |
| `xhsm-verify/miniprogram` | 微信小程序等 | `miniprogram-sm-crypto` |

小程序端请使用 `require('xhsm-verify/miniprogram')`，避免打包 web 端依赖。

## API 一览

- `verifySignature({ data, sig, publicKey, format, hash, userId })`：SM2 验签，返回 `boolean`。
- `base64ToHex(b64)`：Base64 字符串转 hex 字符串。
- `hexToBase64(hex)`：hex 字符串转 Base64 字符串。
- 签名格式转换：`format` 支持 `der-base64` / `der-hex` / `rs-hex`，内部统一转为 `r || s` hex。

更详细的参数说明、错误码、与后端字段对应关系，请参考主仓库 README。
