<?php
// SM2 包装器
//
// 将扩展层 Xhsm\Sm2 的静态方法包装为实例方法，便于链式调用 Xhsm::sm2()->sign(...)。
//
// 数据格式语义（友好转换）：
// - 密钥（$privateKey/$publicKey）：保持 hex 字符串（与扩展层一致）。
// - 待签名/加解密的数据 $data：接受**原始字符串**，内部 bin2hex() 转为 hex 后调用扩展。
// - sign/verify 返回/接受 hex 编码的签名串（DER 或 RAW，由 $format 决定）。
// - encrypt 返回 hex 编码的密文；decrypt 接受 hex 密文，返回**原始字符串**明文。
//
// 这样 ThinkPHP 用户可直观地：$sm2->sign($priv, 'hello') / $sm2->decrypt($priv, $ct) === 'hello'。

namespace Xhsm\Think\Wrapper;

class Sm2
{
    /**
     * 生成 SM2 密钥对
     *
     * @return array{private_key: string, public_key: string}
     */
    public function generateKeyPair(): array
    {
        return \Xhsm\Sm2::generateKeyPair();
    }

    /**
     * SM2 加密
     *
     * @param string $publicKey  公钥（hex）
     * @param string $data       原始字符串明文
     * @param string $mode       密文顺序：C1C3C2（默认）/ C1C2C3 / ASN1
     * @return string            hex 编码的密文
     */
    public function encrypt(string $publicKey, string $data, string $mode = 'C1C3C2'): string
    {
        // 原始字符串 → hex，交给扩展层
        return \Xhsm\Sm2::encrypt($publicKey, bin2hex($data), $mode);
    }

    /**
     * SM2 解密
     *
     * @param string $privateKey 私钥（hex）
     * @param string $ciphertext hex 编码的密文
     * @param string $mode       密文顺序：C1C3C2（默认）/ C1C2C3 / ASN1
     * @return string            原始字符串明文
     */
    public function decrypt(string $privateKey, string $ciphertext, string $mode = 'C1C3C2'): string
    {
        // 扩展层返回 hex 明文，转回原始字符串
        return hex2bin(\Xhsm\Sm2::decrypt($privateKey, $ciphertext, $mode));
    }

    /**
     * SM2 签名
     *
     * @param string $privateKey 私钥（hex）
     * @param string $data       原始字符串数据
     * @param string $format     签名编码：DER（默认）/ RAW
     * @return string            hex 编码的签名
     */
    public function sign(string $privateKey, string $data, string $format = 'DER'): string
    {
        return \Xhsm\Sm2::sign($privateKey, bin2hex($data), $format);
    }

    /**
     * SM2 验签
     *
     * @param string $publicKey  公钥（hex）
     * @param string $data       原始字符串数据
     * @param string $signature  hex 编码的签名
     * @param string $format     签名编码：DER（默认）/ RAW
     * @return bool
     */
    public function verify(string $publicKey, string $data, string $signature, string $format = 'DER'): bool
    {
        return \Xhsm\Sm2::verify($publicKey, bin2hex($data), $signature, $format);
    }
}
