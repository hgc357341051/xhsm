<?php
// Xhsm 门面
//
// 用法（ThinkPHP 8 项目中）：
//   use Xhsm\Think\Facades\Xhsm;
//   Xhsm::sm2()->sign($privateKey, 'hello');
//   Xhsm::sm3()->hash('abc');
//   Xhsm::signature()->sign('s2', $privateKey, 'hello');
//
// 静态调用会被 think\Facade 转发到容器中绑定的 Manager 实例。

namespace Xhsm\Think\Facades;

use Xhsm\Think\Facade;
use Xhsm\Think\Manager;

class Xhsm extends Facade
{
    /**
     * 返回容器绑定名（与 ServiceProvider 中 bind 的标识一致）
     */
    protected static function getFacadeClass(): string
    {
        return Manager::class;
    }
}
