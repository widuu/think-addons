<?php

use think\facade\Route;
use think\helper\Str;

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
        if(empty($url)){
            $addon = $request->route('addon');
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
            } else {
                $route = explode('/', $url['path']);
                // 大于 2级分割
                if(count($route) > 2){
                    $addon = array_pop($route);
                    $controller = array_pop($route);
                    $action = array_pop($route);
                }else{
                    $addon = $request->route('addon');
                    $action = array_pop($route);
                    $controller = array_pop($route) ?: $request->controller();
                }
            }
            $controller = Str::snake((string)$controller);

            /* 解析URL带的参数 */
            if (isset($url['query'])) {
                parse_str($url['query'], $query);
                $param = array_merge($query, $param);
            }
        }
        // namespace
        $namespace = app()->addon->getNamespace();
        // 返回 URL
        return Route::buildUrl("@{$namespace}/{$addon}/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);
    }
}