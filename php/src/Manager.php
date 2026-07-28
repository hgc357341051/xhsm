<?php
// Manager 门面底层管理器
//
// 提供 sm2()/sm3()/sm4()/sm9()/signature()/scenario() 方法返回对应 Wrapper 实例，
// 供 ThinkPHP Facade 转发调用（Xhsm::sm2()->sign(...)）。
//
// 设计要点：
// - **不依赖 think\*** 类，可在无 ThinkPHP 环境下独立实例化与测试。
// - Wrapper 实例按需创建并缓存（单例），避免重复构造。
// - 构造函数接受可选的配置数组（默认签名版本/场景等），便于从 TP 容器注入；
//   无配置时也可工作，使用内部默认值。

namespace Xhsm\Think;

use Xhsm\Think\Wrapper\Sm2;
use Xhsm\Think\Wrapper\Sm3;
use Xhsm\Think\Wrapper\Sm4;
use Xhsm\Think\Wrapper\Sm9;
use Xhsm\Think\Wrapper\Signature;
use Xhsm\Think\Wrapper\Scenario;

class Manager
{
    /** 默认配置 */
    private const DEFAULT_CONFIG = [
        'default_signature_version' => 's2',
        'default_scenario' => 'finance',
        'sm2' => ['mode' => 'C1C3C2'],
        'sm4' => ['mode' => 'CBC'],
        'keys' => [],
    ];

    private array $config;

    private ?Sm2 $sm2 = null;
    private ?Sm3 $sm3 = null;
    private ?Sm4 $sm4 = null;
    private ?Sm9 $sm9 = null;
    private ?Signature $signature = null;

    /**
     * @param array $config 配置（合并到默认配置之上），可由 TP 容器的 config('xhsm') 提供
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
    }

    /**
     * 获取配置（支持点分取值，如 'sm2.mode'）
     *
     * @param string|null $key     配置键，null 返回全部
     * @param mixed       $default 默认值
     * @return mixed
     */
    public function config(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        $value = $this->config;
        foreach (explode('.', $key) as $seg) {
            if (!is_array($value) || !array_key_exists($seg, $value)) {
                return $default;
            }
            $value = $value[$seg];
        }
        return $value;
    }

    /**
     * 获取默认签名版本
     */
    public function getDefaultSignatureVersion(): string
    {
        return $this->config('default_signature_version', 's2');
    }

    /**
     * 获取默认场景名称
     */
    public function getDefaultScenario(): string
    {
        return $this->config('default_scenario', 'finance');
    }

    /**
     * SM2 包装器（缓存单例）
     */
    public function sm2(): Sm2
    {
        return $this->sm2 ??= new Sm2();
    }

    /**
     * SM3 包装器（缓存单例）
     */
    public function sm3(): Sm3
    {
        return $this->sm3 ??= new Sm3();
    }

    /**
     * SM4 包装器（缓存单例）
     */
    public function sm4(): Sm4
    {
        return $this->sm4 ??= new Sm4();
    }

    /**
     * SM9 包装器（缓存单例）
     */
    public function sm9(): Sm9
    {
        return $this->sm9 ??= new Sm9();
    }

    /**
     * Signature 包装器（缓存单例）
     */
    public function signature(): Signature
    {
        return $this->signature ??= new Signature();
    }

    /**
     * 业务场景包装器（每次新建，因为持有场景名称状态）
     *
     * @param string|null $name 场景名称；null 使用默认场景
     */
    public function scenario(?string $name = null): Scenario
    {
        return new Scenario($name ?? $this->getDefaultScenario());
    }
}
