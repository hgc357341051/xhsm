// 默认入口（Node / Webpack / Taro / uni-app 等基于构建工具的环境）
// 微信原生小程序请改用 require('xhsm-verify/miniprogram')
const sm2 = require('sm-crypto').sm2
const { createVerify } = require('./src/verify')
const { base64ToHex, hexToBase64 } = require('./src/codec')
const verifySignature = createVerify(sm2)
module.exports = { verifySignature, base64ToHex, hexToBase64 }
