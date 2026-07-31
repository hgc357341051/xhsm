const sm2 = require('sm-crypto').sm2
const { createVerify } = require('./src/verify')
const { base64ToHex, hexToBase64 } = require('./src/codec')
const verifySignature = createVerify(sm2)
module.exports = { verifySignature, base64ToHex, hexToBase64 }
