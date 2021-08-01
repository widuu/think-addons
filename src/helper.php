<?php

use think\helper\Str;
use think\facade\Route;
use think\Console;

Console::starting(function (Console $console){
   $console->addCommands([
       'addons:service' => \think\addons\command\SendService::class,
   ]);
});

if(!function_exists('get_addons_class'))
{
    /**
     * 获取插件类名
     * @param $name
     * @param null $class
     * @param string $type
     * @return string
     */
    function get_addons_class($name, $class = null, $type = '')
    {
        $app = app();
        // 原始命名空间
        $oldNamespace = $app->getNamespace();
        // 切换命名空间
        $app->setNamespace($app->addons->getNamespace());
        // 命名空间
        $layer = $type == 'controller' ? $name . '\\controller\\' : $name;
        // 解析class
        $class = $app->parseClass($layer, $class ?: $name);
        // 切换回去
        $app->setNamespace($oldNamespace);
        // 返回类
        return class_exists($class) ? $class : '';
    }
}

if(!function_exists('get_addons_instance'))
{
    /**
     * 获取插件实例
     * @param $name
     * @return false|mixed
     */
    function get_addons_instance($name)
    {
        $class = get_addons_class($name);
        return !empty($class) ? app()->make($class, [], false) : false;
    }
}

if(!function_exists('get_addons_config'))
{
    /**
     * 获取应用配置
     * @param $name
     * @return array
     */
    function get_addons_config($name): array
    {
        $instance = get_addons_instance($name);
        return $instance ? $instance->getConfig($name) : [];
    }
}

if(!function_exists('get_addons_info'))
{
    /**
     * 获取应用信息
     * @param $name
     * @return array
     */
    function get_addons_info($name): array
    {
        $instance = get_addons_instance($name);
        return $instance ? $instance->getInfo($name) : [];
    }
}

if(!function_exists('addons_url'))
{
    /**
     * 插件显示内容里生成访问插件的url
     * @param $url
     * @param array $param
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return bool|string
     */
    function addons_url($url = '', $param = [], $suffix = true, $domain = false)
    {
        $request = request();
        // 插件模块访问
        if($request->addons && empty($url)){
            // 其它应用访问 request 没有 addons 标识
            $addon = $request->addons;
            $controller = $request->controller();
            $controller = str_replace('/', '.', $controller);
            $action = $request->action();
        }else{
            $url = Str::studly($url);
            $url = parse_url($url);
            if (isset($url['scheme'])) {
                $addon = strtolower($url['scheme']);
                $controller = $url['host'];
                $action = trim($url['path'], '/');
            }else{
                $route = explode('/', $url['path']);
                if(!$request->addons){
                    $addon = strtolower(array_pop($route));
                    $controller = array_pop($route) ?: config('route.default_controller');
                    $action = array_pop($route) ?: config('route.default_action');
                }else{
                    // 跨插件跳转，指定模块
                    if(count($route) > 2){
                        $addon = strtolower(array_pop($route));
                        $controller = array_pop($route);
                        $action = array_pop($route);
                    }else{
                        $addon = $request->route('addon');
                        $action = array_pop($route);
                        $controller = array_pop($route) ?: $request->controller();
                    }
                }
            }
        }

        $controller = Str::snake((string)$controller);

        /* 解析URL带的参数 */
        if (isset($url['query'])) {
            parse_str($url['query'], $query);
            $param = array_merge($query, $param);
        }
        dump($controller);
        // namespace
        $namespace = app()->addons->getNamespace();
        // 返回 URL
        return Route::buildUrl("@{$namespace}/{$addon}/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);
    }
}
