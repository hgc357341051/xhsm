// verify.test.js — 用 PHP xhsm 扩展生成的真实签名向量测试 verifySignature
//
// 入口：web.js（已注入 sm-crypto 的 sm2）
// 向量：./vectors.json（由 /tmp/gen_vectors.php 生成，PHP 自检 4 个 php_verify_* 均为 true）
//
// 测试用例：
//   1. 4 个 format（der-hex / raw-hex / der-base64 / raw-base64）各验一次正确签名 → 全部 true
//   2. 篡改 msg（data 末尾加 'x'）→ 4 个 format 全部 false
//   3. 篡改 sig（签名首字符改）→ 4 个 format 全部 false
//   4. 自定义 userId：用错误 userId '0000000000000000' 验签 → false（验证 userId 参与运算）
//   5. 非法 format 抛错（try/catch 断言抛 Error）
//
// 全部 pass 时 exit 0，有 fail 时 exit 1。末尾打印总结 `verify.test: N/N passed`。

const { verifySignature } = require('../web.js')
const vectors = require('./vectors.json')

const { public_key: PK, data } = vectors
const SIGS = {
  'der-hex':    vectors.der_hex,
  'raw-hex':    vectors.raw_hex,
  'der-base64': vectors.der_base64,
  'raw-base64': vectors.raw_base64,
}
const FORMATS = Object.keys(SIGS)

let pass = 0
let fail = 0
const failures = []

function check(name, actual, expected) {
  const ok = actual === expected
  if (ok) {
    pass++
    console.log(`  [PASS] ${name}`)
  } else {
    fail++
    console.log(`  [FAIL] ${name}`)
    console.log(`         expected: ${JSON.stringify(expected)}`)
    console.log(`         actual:   ${JSON.stringify(actual)}`)
    failures.push(name)
  }
}

// 修改签名靠近末尾的一个 payload 字符（属 s 值的一部分），使其变为另一个同字母表字符。
// 选索引 len-8：s 是 DER/RAW 签名的最后一个字段，其"值"占末尾字节，
// 末尾 8 字符必然落在 s 值内（避开 DER 的 tag/length 头部、INTEGER tag 字节，
// 也避开 base64 末尾的 '=' padding），对 4 种 format 都能可靠改变 s → 验签失败。
// 注：sm-crypto 的 decodeDer 对 SEQUENCE/INTEGER tag 均不校验（只读 length），
// 故改首字符或中部 tag 字节不能保证 DER 验签失败。
function tamperPayloadChar(s) {
  const hexAlpha = '0123456789abcdef'
  const b64Alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
  const isHex = /^[0-9a-f]+$/.test(s)
  const alpha = isHex ? hexAlpha : b64Alpha
  const idx = s.length - 8
  const orig = s[idx]
  let replacement = alpha[0]
  if (replacement === orig) replacement = alpha[1]
  return s.slice(0, idx) + replacement + s.slice(idx + 1)
}

console.log('===== verify.test 开始 =====')
console.log(`  publicKey: ${PK.slice(0, 16)}...(${PK.length} hex chars)`)
console.log(`  data: ${JSON.stringify(data)}`)

// ---------- 1. 4 个 format 正确签名验签 → true ----------
console.log('--- 4 个 format 正确签名验签 → true ---')
for (const fmt of FORMATS) {
  const ok = verifySignature(data, SIGS[fmt], PK, { format: fmt })
  check(`正确签名 format=${fmt} → true`, ok, true)
}

// ---------- 2. 篡改 msg（末尾加 'x'）→ 4 个 format 全部 false ----------
console.log('--- 篡改 msg（data 末尾加 "x"）→ 4 个 format 全部 false ---')
const tamperedMsg = data + 'x'
for (const fmt of FORMATS) {
  const ok = verifySignature(tamperedMsg, SIGS[fmt], PK, { format: fmt })
  check(`篡改 msg format=${fmt} → false`, ok, false)
}

// ---------- 3. 篡改 sig（修改签名 s 值字节）→ 4 个 format 全部 false ----------
// 说明：sm-crypto 的 decodeDer 对外层 SEQUENCE tag 与 INTEGER tag 均不校验（只读 length），
// 仅改"首字符"或中部 tag 字节不会改变解析出的 r/s，DER 仍会验过。
// 因此改为修改签名末尾 s 值内的一个字符（见 tamperPayloadChar），对 4 种 format 都能可靠
// 改变 s → 验签失败；同时避开 base64 末尾的 '=' padding。
console.log('--- 篡改 sig（修改签名末尾 s 值字节）→ 4 个 format 全部 false ---')
for (const fmt of FORMATS) {
  const badSig = tamperPayloadChar(SIGS[fmt])
  const ok = verifySignature(data, badSig, PK, { format: fmt })
  check(`篡改 sig format=${fmt} → false`, ok, false)
}

// ---------- 4. 自定义 userId：错误 userId 验签 → false ----------
console.log("--- 自定义 userId：错误 userId '0000000000000000' 验签 → false ---")
{
  const ok = verifySignature(data, SIGS['der-hex'], PK, {
    format: 'der-hex',
    userId: '0000000000000000',
  })
  check("错误 userId '0000000000000000' → false", ok, false)
}

// ---------- 5. 非法 format 抛 Error ----------
console.log('--- 非法 format 抛 Error ---')
{
  let threw = false
  let thrownIsError = false
  try {
    verifySignature(data, SIGS['der-hex'], PK, { format: 'invalid-format' })
  } catch (e) {
    threw = true
    thrownIsError = e instanceof Error
  }
  check('非法 format 抛 Error', threw && thrownIsError, true)
}

// ---------- 总结 ----------
const total = pass + fail
console.log('-----')
console.log(`verify.test: ${pass}/${total} passed`)
if (fail > 0) {
  console.log(`FAILED cases: ${failures.join(', ')}`)
  process.exit(1)
}
process.exit(0)
