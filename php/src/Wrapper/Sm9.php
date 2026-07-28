<?php
// SM9 包装器
//
// 将扩展层 Xhsm\Sm9 的静态方法包装为实例方法。
//
// 数据格式语义（友好转换）：
// - 主密钥/用户私钥/主公钥：保持 hex 字符串（与扩展层一致）。
// - 用户标识 $id：原始字符串（与扩展层一致）。
// - 待签名/加密的数据 $data：接受**原始字符串**，内部 bin2hex() 转为 hex 后调用扩展。
// - encrypt 返回 hex 密文；decrypt 接受 hex 密文，返回**原始字符串**明文（扩展层 decrypt 已返回原始字符串）。
// - sign 返回 hex 签名；verify 接受 hex 签名。

namespace Xhsm\Think\Wrapper;

class Sm9
{
    /**
     * 生成 SM9 主密钥对（加密 + 签名）
     *
     * @return array{master_enc_private_key: string, master_enc_public_key: string, master_sig_private_key: string, master_sig_public_key: string}
     */
    public function generateMasterKeyPair(): array
    {
        return \Xhsm\Sm9::generateMasterKeyPair();
    }

    /**
     * 抽取用户私钥
     *
     * @param string $masterPrivateKey 主私钥（hex）
     * @param string $id               用户标识（原始字符串）
     * @param string $keyType          密钥类型：enc（默认）/ sig
     * @return string                  用户私钥（hex；sig 类型为 hex(uspk):hex(mspk) 捆绑格式）
     */
    public function extractUserPrivateKey(string $masterPrivateKey, string $id, string $keyType = 'enc'): string
    {
        return \Xhsm\Sm9::extractUserPrivateKey($masterPrivateKey, $id, $keyType);
    }

    /**
     * SM9 标识加密
     *
     * @param string $masterPublicKey 加密主公钥（hex）
     * @param string $id              接收方用户标识（原始字符串）
     * @param string $data            原始字符串明文
     * @return string                 hex 编码的密文
     */
    public function encrypt(string $masterPublicKey, string $id, string $data): string
    {
        return \Xhsm\Sm9::encrypt($masterPublicKey, $id, bin2hex($data));
    }

    /**
     * SM9 标识解密
     *
     * @param string $userPrivateKey 接收方用户私钥（hex）
     * @param string $id             接收方用户标识（原始字符串）
     * @param string $ciphertext     hex 编码的密文
     * @return string                原始字符串明文
     */
    public function decrypt(string $userPrivateKey, string $id, string $ciphertext): string
    {
        // 扩展层 decrypt 已返回原始字符串
        return \Xhsm\Sm9::decrypt($userPrivateKey, $id, $ciphertext);
    }

    /**
     * SM9 标识签名
     *
     * @param string      $userPrivateKey 签名用户私钥（hex 捆绑格式 hex(uspk):hex(mspk)）
     * @param string      $id             签名方用户标识（原始字符串）
     * @param string      $data           原始字符串数据
     * @param string|null $masterPublicKey 签名主公钥（hex；当 $userPrivateKey 为纯用户私钥时需提供）
     * @return string                     hex 编码的签名
     */
    public function sign(string $userPrivateKey, string $id, string $data, ?string $masterPublicKey = null): string
    {
        return \Xhsm\Sm9::sign($userPrivateKey, $id, bin2hex($data), $masterPublicKey);
    }

    /**
     * SM9 标识验签
     *
     * @param string $masterPublicKey 签名主公钥（hex）
     * @param string $id              签名方用户标识（原始字符串）
     * @param string $data            原始字符串数据
     * @param string $signature       hex 编码的签名
     * @return bool
     */
    public function verify(string $masterPublicKey, string $id, string $data, string $signature): bool
    {
        return \Xhsm\Sm9::verify($masterPublicKey, $id, bin2hex($data), $signature);
    }
}
