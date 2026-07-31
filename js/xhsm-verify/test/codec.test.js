// codec.test.js — 测试 src/codec.js 的 base64ToHex / hexToBase64
//
// 覆盖：
//   1. 互逆性：随机 10 组 hex → hexToBase64 → base64ToHex → hex 还原一致
//   2. 边界：空串、'00'、'ff'、'deadbeef'、单字节、带 padding 输入
//   3. 与 Node Buffer 对照：base64ToHex(b64) === Buffer.from(b64,'base64').toString('hex')（10 组随机）
//
// 全部 pass 时 exit 0，有 fail 时 exit 1。末尾打印总结 `codec.test: N/N passed`。

const crypto = require('crypto')
const { base64ToHex, hexToBase64 } = require('../src/codec')

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

function randHex(byteLen) {
  return crypto.randomBytes(byteLen).toString('hex')
}

console.log('===== codec.test 开始 =====')

// ---------- 1. 互逆性：hex → base64 → hex ----------
console.log('--- 互逆性：hex → hexToBase64 → base64ToHex → hex （10 组随机） ---')
for (let i = 0; i < 10; i++) {
  const byteLen = 1 + Math.floor(Math.random() * 32) // 1..32 字节，覆盖各种对齐
  const hex = randHex(byteLen)
  const b64 = hexToBase64(hex)
  const back = base64ToHex(b64)
  // 互逆时需保证还原 hex 与原 hex 完全一致（小端字节序，无前导 0 影响）
  check(`互逆 #${i + 1} (${byteLen}B) hex=${hex.slice(0, 16)}...`, back, hex)
}

// ---------- 2. 边界用例 ----------
console.log('--- 边界用例 ---')
check("base64ToHex('') === '' (空串)", base64ToHex(''), '')
check("base64ToHex('AA==') === '00'", base64ToHex('AA=='), '00')
check("base64ToHex('/w==') === 'ff'", base64ToHex('/w=='), 'ff')
check("base64ToHex('3q2+7w==') === 'deadbeef'", base64ToHex('3q2+7w=='), 'deadbeef')
check("base64ToHex('fw==') === '7f' (单字节)", base64ToHex('fw=='), '7f')
check("base64ToHex('YWJjZGU=') === '6162636465' (带 = padding 输入)", base64ToHex('YWJjZGU='), '6162636465')

// ---------- 3. 与 Node Buffer 对照 ----------
console.log('--- 与 Node Buffer 对照：base64ToHex(b64) === Buffer.from(b64,"base64").toString("hex") （10 组随机） ---')
for (let i = 0; i < 10; i++) {
  const byteLen = 1 + Math.floor(Math.random() * 32)
  const hex = randHex(byteLen)
  const b64 = Buffer.from(hex, 'hex').toString('base64')
  const fromCodec = base64ToHex(b64)
  const fromBuffer = Buffer.from(b64, 'base64').toString('hex')
  check(`Buffer 对照 #${i + 1} (${byteLen}B) b64=${b64.slice(0, 16)}...`, fromCodec, fromBuffer)
}

// ---------- 总结 ----------
const total = pass + fail
console.log('-----')
console.log(`codec.test: ${pass}/${total} passed`)
if (fail > 0) {
  console.log(`FAILED cases: ${failures.join(', ')}`)
  process.exit(1)
}
process.exit(0)
