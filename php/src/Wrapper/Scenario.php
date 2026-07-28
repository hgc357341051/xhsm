<?php
// Scenario 业务场景包装器
//
// 按名称包装扩展层 Xhsm\Scenario\* 静态方法为实例方法。
// 支持 finance / payment / government / miniprogram 四类场景。
//
// 数据格式语义：
// 扩展层各 Scenario 类已直接接受**原始字符串**数据并返回 hex 密文/签名，
// decrypt 返回原始字符串明文，故本包装器直接透传。

namespace Xhsm\Think\Wrapper;

class Scenario
{
    /** 场景名称到扩展类的映射 */
    private const MAP = [
        'finance'      => \Xhsm\Scenario\Finance::class,
        'payment'      => \Xhsm\Scenario\Payment::class,
        'government'   => \Xhsm\Scenario\Government::class,
        'miniprogram'  => \Xhsm\Scenario\MiniProgram::class,
    ];

    private string $name;
    private string $class;

    /**
     * @param string $name 场景名称（finance/payment/government/miniprogram，大小写不敏感）
     */
    public function __construct(string $name)
    {
        $key = strtolower($name);
        if (!isset(self::MAP[$key])) {
            throw new \InvalidArgumentException("未知场景: {$name}，支持: " . implode(',', array_keys(self::MAP)));
        }
        $this->name = $key;
        $this->class = self::MAP[$key];
    }

    /**
     * 获取场景名称
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * 场景签名
     *
     * @param string $privateKey 私钥（hex）
     * @param string $data       原始字符串数据
     * @return string            签名（hex 或 base64，由场景配置决定）
     */
    public function sign(string $privateKey, string $data): string
    {
        return ($this->class)::sign($privateKey, $data);
    }

    /**
     * 场景验签
     *
     * @param string $publicKey  公钥（hex）
     * @param string $data       原始字符串数据
     * @param string $signature  签名
     * @return bool
     */
    public function verify(string $publicKey, string $data, string $signature): bool
    {
        return ($this->class)::verify($publicKey, $data, $signature);
    }

    /**
     * 场景加密
     *
     * @param string $publicKey 公钥（hex）
     * @param string $data      原始字符串明文
     * @return string           hex 编码的密文
     */
    public function encrypt(string $publicKey, string $data): string
    {
        return ($this->class)::encrypt($publicKey, $data);
    }

    /**
     * 场景解密
     *
     * @param string $privateKey 私钥（hex）
     * @param string $ciphertext hex 编码的密文
     * @return string            原始字符串明文
     */
    public function decrypt(string $privateKey, string $ciphertext): string
    {
        return ($this->class)::decrypt($privateKey, $ciphertext);
    }

    /**
     * 场景哈希（SM3）
     *
     * @param string $data 原始字符串数据
     * @return string      64 hex 字符的摘要
     */
    public function hash(string $data): string
    {
        return ($this->class)::hash($data);
    }
}
