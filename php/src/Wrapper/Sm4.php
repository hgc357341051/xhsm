<?php
// SM4 包装器
//
// 将扩展层 Xhsm\Sm4 的静态方法包装为实例方法。
//
// 数据格式语义（友好转换）：
// - 密钥 $key、初始向量 $iv：保持 hex 字符串（与扩展层一致，SM4 密钥固定 16 字节 = 32 hex）。
// - 明文/附加认证数据 $data/$aad：接受**原始字符串**，内部 bin2hex() 转为 hex。
// - encrypt 返回 hex 编码的密文；decrypt 接受 hex 密文，返回**原始字符串**明文。

namespace Xhsm\Think\Wrapper;

class Sm4
{
    /**
     * SM4 加密
     *
     * @param string      $key  密钥（hex，32 字符 = 16 字节）
     * @param string      $iv   初始向量（hex；ECB 模式可传空串）
     * @param string      $data 原始字符串明文
     * @param string      $mode 工作模式：CBC（默认）/ ECB / CTR / GCM
     * @param string|null $aad  附加认证数据（原始字符串，仅 GCM 模式）
     * @return string           hex 编码的密文（GCM 模式含末尾 16 字节 Tag）
     */
    public function encrypt(string $key, string $iv, string $data, string $mode = 'CBC', ?string $aad = null): string
    {
        $hexData = bin2hex($data);
        $hexAad = $aad !== null ? bin2hex($aad) : null;
        return \Xhsm\Sm4::encrypt($key, $iv, $hexData, $mode, $hexAad);
    }

    /**
     * SM4 解密
     *
     * @param string      $key       密钥（hex）
     * @param string      $iv        初始向量（hex；ECB 模式可传空串）
     * @param string      $ciphertext hex 编码的密文
     * @param string      $mode      工作模式：CBC（默认）/ ECB / CTR / GCM
     * @param string|null $aad       附加认证数据（原始字符串，仅 GCM 模式）
     * @return string                原始字符串明文
     */
    public function decrypt(string $key, string $iv, string $ciphertext, string $mode = 'CBC', ?string $aad = null): string
    {
        $hexAad = $aad !== null ? bin2hex($aad) : null;
        return hex2bin(\Xhsm\Sm4::decrypt($key, $iv, $ciphertext, $mode, $hexAad));
    }
}
