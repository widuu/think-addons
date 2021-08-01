<?php

namespace think\addons;


use think\addons\command\SendService;
use think\Cache;

use think\Request;
use think\Route;
use think\event\AppInit;
use think\event\HttpRun;
use think\event\RouteLoaded;
use think\Service as BaseService;
use think\addons\middleware\Addons;

class Service extends BaseService
{

    /**
     * 绑定
     * @var string[]
     */
    public $bind = [
        'addons' => App::class,
    ];


    public function register(): void
    {
        // 监听 AppInit 服务
        $this->app->event->listen(AppInit::class, function (App $app){
        });
    }

    /**
     * 注册插件服务
     */
    public function boot()
    {
        // 注册命令行
        $this->commands([]);
        // 注册路由
        $this->app->event->listen(RouteLoaded::class, function (Route $route){
            // 路由调度器
            $dispatch = \think\addons\dispatch\Controller::class;
            // 路由前缀
            $namesapce = $this->app->addons->getNamespace();
            // 注册路由
            $route->rule($namesapce . '/:addon/:controller/:action', $dispatch)->middleware(Addons::class);
            // 获取自定义路由
            $custom_route = (array)$this->app->config->get('addons.route', []);
            // 循环注册路由
            foreach ($custom_route as $k => $v){
                if(!$v) continue;
                if(is_array($v)){
                    $domain = $v['domain'];
                    $rules  = $v['rules'];
                    $route->domain($domain, function () use ($rules, $route, $dispatch){
                        foreach ($rules as $k => $v){
                            list($addon, $controller, $action) = explode('/', $v);
                            $route->rule($k, $dispatch)->name($k)->completeMatch(true)->append([
                                'addon'      => $addon,
                                'controller' => $controller,
                                'action'     => $action,
                                'indomain'   => 1
                            ]);
                        }
                    });
                }else{
                    list($addon, $controller, $action) = explode('/', $v);
                    $route->rule($k, $dispatch)->name($k)->completeMatch(true)->append([
                        'addon'      => $addon,
                        'controller' => $controller,
                        'action'     => $action,
                    ]);
                }
            }
        });
    }
}
