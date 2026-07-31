/**
 * base64 字符串转 hex 字符串（纯 JS，跨小程序平台通用，含防溢出）
 * @param {string} base64
 * @returns {string} hex
 */
function base64ToHex(base64) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
  const lookup = new Int8Array(128)
  for (let i = 0; i < 128; i++) lookup[i] = -1
  for (let i = 0; i < chars.length; i++) lookup[chars.charCodeAt(i)] = i

  // 去 padding
  base64 = base64.replace(/=+$/, '')

  let buffer = 0
  let bits = 0
  const bytes = []

  for (let i = 0; i < base64.length; i++) {
    const code = base64.charCodeAt(i)
    const val = code < 128 ? lookup[code] : -1
    if (val < 0) continue // 跳过非法字符
    buffer = (buffer << 6) | val
    bits += 6
    if (bits >= 8) {
      bits -= 8
      bytes.push((buffer >> bits) & 0xff)
      // 关键：清除已输出位防长串溢出
      buffer &= (1 << bits) - 1
    }
  }

  return bytes
    .map(function (b) {
      return (b & 0xff).toString(16).padStart(2, '0')
    })
    .join('')
}

/**
 * hex 字符串转 base64（纯 JS）
 * @param {string} hex
 * @returns {string} base64
 */
function hexToBase64(hex) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'

  // 兼容奇数长度（前置补 0）
  if (hex.length % 2 !== 0) hex = '0' + hex

  const bytes = []
  for (let i = 0; i < hex.length; i += 2) {
    bytes.push(parseInt(hex.substr(i, 2), 16))
  }

  let out = ''
  for (let i = 0; i < bytes.length; i += 3) {
    const b0 = bytes[i] || 0
    const b1 = bytes[i + 1]
    const b2 = bytes[i + 2]

    out += chars[(b0 >> 2) & 0x3f]
    out += chars[((b0 << 4) & 0x30) | ((b1 !== undefined ? b1 : 0) >> 4 & 0x0f)]

    if (b1 === undefined) {
      out += '=='
      break
    }
    out += chars[((b1 << 2) & 0x3c) | ((b2 !== undefined ? b2 : 0) >> 6 & 0x03)]

    if (b2 === undefined) {
      out += '='
      break
    }
    out += chars[b2 & 0x3f]
  }

  return out
}

module.exports = { base64ToHex, hexToBase64 }
