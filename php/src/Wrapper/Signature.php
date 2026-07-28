<?php
// Signature 包装器
//
// 将扩展层 Xhsm\Signature 的静态方法包装为实例方法。
//
// 数据格式语义：
// 扩展层 Xhsm\Signature::sign / verify 已直接接受**原始字符串**数据
// （内部会自行做 hex 转换），故本包装器直接透传 $data。

namespace Xhsm\Think\Wrapper;

class Signature
{
    /**
     * 按版本签名
     *
     * @param string $version    签名版本（如 s2/s3/s4 或自定义注册的版本）
     * @param string $privateKey 私钥（hex）
     * @param string $data       原始字符串数据
     * @return string            签名（hex 或 base64，由版本配置决定）
     */
    public function sign(string $version, string $privateKey, string $data): string
    {
        return \Xhsm\Signature::sign($version, $privateKey, $data);
    }

    /**
     * 按版本验签
     *
     * @param string $version   签名版本
     * @param string $publicKey 公钥（hex）
     * @param string $data      原始字符串数据
     * @param string $signature 签名（hex 或 base64，由版本配置决定）
     * @return bool
     */
    public function verify(string $version, string $publicKey, string $data, string $signature): bool
    {
        return \Xhsm\Signature::verify($version, $publicKey, $data, $signature);
    }

    /**
     * 注册自定义签名版本
     *
     * @param string $version 版本标识（如 s5）
     * @param array  $config  配置（algorithm/encoding/output/user_id/description 等）
     * @return void
     */
    public function register(string $version, array $config): void
    {
        \Xhsm\Signature::register($version, $config);
    }

    /**
     * 获取已注册的所有签名版本
     *
     * @return array<string>
     */
    public function versions(): array
    {
        return \Xhsm\Signature::versions();
    }

    /**
     * 获取版本配置描述
     *
     * @param string $version 版本标识
     * @return array
     */
    public function describe(string $version): array
    {
        return \Xhsm\Signature::describe($version);
    }
}
