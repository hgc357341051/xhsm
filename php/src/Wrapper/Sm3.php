<?php
// SM3 包装器
//
// 将扩展层 Xhsm\Sm3 的静态方法包装为实例方法。
//
// 数据格式语义：
// 扩展层 Sm3::hash / Sm3::hmac 已直接接受**原始字符串**数据并返回 hex 摘要，
// 故本包装器直接透传，无需额外转换。

namespace Xhsm\Think\Wrapper;

class Sm3
{
    /**
     * 计算 SM3 摘要
     *
     * @param string $data 原始字符串数据
     * @return string      64 hex 字符（32 字节）的摘要
     */
    public function hash(string $data): string
    {
        return \Xhsm\Sm3::hash($data);
    }

    /**
     * 计算 HMAC-SM3
     *
     * @param string $key  原始字符串密钥
     * @param string $data 原始字符串数据
     * @return string      64 hex 字符的 HMAC
     */
    public function hmac(string $key, string $data): string
    {
        return \Xhsm\Sm3::hmac($key, $data);
    }
}
