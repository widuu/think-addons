<?php

namespace think\addons;

use think\addons\middleware\Addons;
use think\Cache;
use think\event\HttpRun;
use think\event\RouteLoaded;
use think\Request;
use think\Route;
use think\Service as BaseService;

class Service extends BaseService
{

    /**
     * 绑定
     * @var string[]
     */
    public $bind = [
        'addon' => Addon::class,
    ];

    /**
     * 注册插件服务
     */
    public function boot()
    {
        // 注册路由
        $this->app->event->listen(RouteLoaded::class, function (Route $route){
            // 路由调度器
            $dispatch = \think\addons\dispatch\Controller::class;
            // 路由前缀
            $namesapce = $this->app->addon->getNamespace();
            // 注册路由
            $route->rule($namesapce . '/:addon/:controller/:action', $dispatch)->middleware(Addons::class)->name('think_addons');
            // 获取自定义路由
            $custom_route = (array)$this->app->config->get('addons.route') ?: [];

            foreach ($custom_route as $k => $v){
                if(empty($v) || !$v) continue;
            }
        });
    }
}
