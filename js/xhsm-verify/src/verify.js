const { base64ToHex } = require('./codec')

/**
 * 创建 verifySignature 函数（注入 sm2 实现）
 * @param {object} sm2 - sm-crypto 的 sm2 对象
 * @returns {function} verifySignature
 */
function createVerify(sm2) {
  /**
   * 验证 xhsm 签名
   * @param {string} msg       原始业务数据（须与后端签名时一致，UTF-8 字节）
   * @param {string} sig       签名串（hex 或 base64，取决于 format）
   * @param {string} publicKey hex 公钥（带或不带 04 前缀）
   * @param {object} options
   * @param {'der-hex'|'raw-hex'|'der-base64'|'raw-base64'} options.format 签名格式（必填）
   * @param {string} [options.userId='1234567812345678'] 须与后端一致
   * @returns {boolean}
   */
  function verifySignature(msg, sig, publicKey, options) {
    const format = options && options.format
    const userId = (options && options.userId) || '1234567812345678'
    // 按 format 解析：是否 der、是否 base64
    let isDer, isBase64
    switch (format) {
      case 'der-hex':     isDer = true;  isBase64 = false; break
      case 'raw-hex':     isDer = false; isBase64 = false; break
      case 'der-base64':  isDer = true;  isBase64 = true;  break
      case 'raw-base64':  isDer = false; isBase64 = true;  break
      default: throw new Error("options.format 必须为 'der-hex'|'raw-hex'|'der-base64'|'raw-base64'")
    }
    let sigHex = isBase64 ? base64ToHex(sig) : sig
    return sm2.doVerifySignature(msg, sigHex, publicKey, {
      hash: true,
      der: isDer,
      userId: userId,
    })
  }
  return verifySignature
}

module.exports = { createVerify }
