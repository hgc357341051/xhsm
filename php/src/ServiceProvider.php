<?php
// ServiceProvider —— ThinkPHP 8 服务提供者
//
// 职责：
// - register()：将 Manager 绑定到容器（同时绑定类名与 'xhsm' 别名），
//   并从应用 config('xhsm') 注入配置。
// - boot()：注册发布命令 xhsm:publish。
//
// 通过 composer.json 的 extra.think.services 可被 TP8 自动注册。

namespace Xhsm\Think;

use Xhsm\Think\command\Publish;
use think\app\Service;

class ServiceProvider extends Service
{
    /**
     * 注册服务到容器
     */
    public function register(): void
    {
        // 把 Manager 绑定到容器，注入 xhsm 配置
        $this->app->bind(Manager::class, function ($app) {
            $config = $app->config->get('xhsm', []);
            return new Manager($config);
        });
        // 同时提供 'xhsm' 别名，便于 app('xhsm') 解析
        $this->app->bind('xhsm', Manager::class);
    }

    /**
     * 启动服务：注册发布命令
     */
    public function boot(): void
    {
        $this->commands(Publish::class);
    }
}
