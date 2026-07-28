<?php
// Facade 基类适配
//
// 直接继承 ThinkPHP 8 的 think\Facade，作为本包所有门面的统一基类，
// 便于后续扩展统一的门面行为（如默认解析容器名）。
// 若无特殊需求，子门面也可直接继承 think\Facade。

namespace Xhsm\Think;

use think\Facade as ThinkFacade;

abstract class Facade extends ThinkFacade
{
    // 子类实现 getFacadeClass() 返回容器绑定名或类名
}
