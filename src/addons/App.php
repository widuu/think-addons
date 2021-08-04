<?php

namespace think\addons;

use think\App as BaseApp;
use think\facade\Cache;
use think\facade\Config;

class App
{

    /**
     * @var \think\App
     */
    private $app;

    /**
     * 插件目录
     * @var string
     */
    public $addonPath;

    /**
     * 插件数据
     * @var array
     */
    public $addons;

    /**
     * 插件配置缓存标识
     * @var string
     */
    public $addonsConfigPrefix = 'addons_config_';

    /**
     * Addon constructor.
     * @param \think\App $app
     */
    public function __construct(BaseApp $app)
    {
        $this->app = $app;
        $this->addonPath = $this->getRootPath();
    }

    /**
     * 获取所有目录
     * @return array
     */
    public function getAddons(): array
    {
        $dirs = array_diff(scandir($this->addonPath), ['.', '..']);
        // 去除非插件目录
        foreach ($dirs as $k => $v) {
            $addonPath = $this->getRootPath() . $v . DIRECTORY_SEPARATOR;
            // 跳过非目录
            if (!is_dir($addonPath)) unset($dirs[$k]);
            // 跳过非插件
            if (!is_file($addonPath . ucfirst($v) . '.php')) unset($dirs[$k]);
            // 插件信息
            if (!is_file($addonPath . 'info.ini')) unset($dirs[$k]);
        }
        $this->addons = $dirs;
        $this->app->cache->set('addons_path', $this->addons);
        return  $this->addons;
    }

    /**
     * 获取当前应用名称
     * @return string
     */
    public function getAddonName(): string
    {
        return $this->app->request->route('addon') ?: '';
    }

    /**
     * 获取插件列表
     * @return array
     */
    public function getAddonList(): array
    {
        $list = [];
        $addons = $this->getAddons();
        foreach ($addons as $addon)
        {
            $addonPath = $this->getRootPath() . $addon . DIRECTORY_SEPARATOR;
            $currentAddon = [ 'addonName' => $addon, 'path' => $addonPath];
            $info = parse_ini_file($addonPath . 'info.ini', true, INI_SCANNER_TYPED) ?: [];
            $info['url'] = addons_url($addon)->build();
            $currentAddon['info'] = $info;
            array_push($list,  $currentAddon);
        }
        return $list;
    }

    /**
     * 自动加载
     * @return array|mixed
     * @throws \ReflectionException
     */
    public function autoload()
    {
        // 开启自动加载事件
        $addonsEvent     = Config::get('addons.autoload_addons_event', false);
        // 开启加载插件下自定义事件文件
        $addonsEventFile = Config::get('addons.autoload_addons_efile', false);
        // 开启自动服务
        $addonsService   = Config::get('addons.autoload_addons_service', false);
        // 开启自动发现路由
        $addonsRoute     = Config::get('addons.autoload_addons_route', false);
        // 开启自动命令行
        $addonsCommand   = Config::get('addons.autoload_addons_command', false);
        // 配置缓存名称
        $addonsCache     = Config::get('addons.addons_autoload_cache', 'addons_autoload_cache');
        // 开启任意配置
        if( $addonsEvent || $addonsService || $addonsCache || $addonsEventFile || $addonsCommand ){
            // 现在加载配置
            $addonSystemConfig = $this->loadAddonsSystemConfig($addonsCache);
            // 文件事件
            $eventFiles = $addonsEventFile ? $addonSystemConfig['event_files'] : [];
            // 自定义插件事件
            $eventConfig = $addonsEvent ? $addonSystemConfig['events'] : [];
            // 加载事件
            $this->loadEvents($eventConfig, $eventFiles);
            // 加载服务
            $addonsService && $this->loadAddonsServices($addonSystemConfig['services'] ?: []);
            // 加载自动命令行
            $addonsCommand && $this->loadCommands($addonSystemConfig['commands']);
            // 加载路由
            if($addonsRoute){
                $routes = Config::get('addons.route', []);
                if(count($addonSystemConfig['routes']) > 0){
                    $routes = array_merge($routes, $addonSystemConfig['routes']);
                }
                $routes && Config::set(['route' => $routes], 'addons');
            }
        }
    }

    /**
     * 加载事件
     * @param array $systemCommands
     */
    public function loadCommands(array $systemCommands)
    {
        $commands = Config::get('addons.addons_commands', []);
        if(count($systemCommands) > 0) {
            $commands = array_merge($commands, $systemCommands);
        }
        foreach ($commands as $k => $command){
            // 去除非字符串命令行
            if(!is_string($k)) unset($commands[$k]);
            // 去除类不存在
            if(!class_exists($command)) unset($commands[$k]);
        }
        $commands && Config::set(['addons_commands' => $commands], 'addons');
    }

    /**
     * 加载全局服务
     * @param $services
     * @return bool
     */
    public function loadAddonsServices($services)
    {
        if(!is_array($services)) return true;
        $this->app->bind($services);
    }

    /**
     * 加载事件服务
     * @param array $events
     * @param array $eventFiles
     */
    public function loadEvents(array $events, array $eventFiles)
    {
        // 文件事件加载器，及时更新加载
        $this->loadAddonsEventFiles($eventFiles);
        // 监听事件
        $listen = Config::get('addons.addons_event');
        // 插件初始化事件
        $addonsInitEvent = isset($listen['AddonsInit']) && is_array($listen['AddonsInit']) ? $listen['AddonsInit'] : [];
        // 防止监听事件
        unset($listen['AddonsInit']);
        // 循环事件
        foreach ($events as $addon => $event){
            foreach ($event as $k => $v){
                // 事件不存在初始化
                !isset($listen[$k]) && $listen[$k] = [];
                // 不存在事件添加事件
                !in_array($v, $listen[$k]) && $listen[$k] = array_merge($listen[$k], $v);
            }
        }

        // 转换非数组事件
        $listen = array_map(function ($v){
           return is_string($v) ? (array)$v : $v;
        }, $listen);

        // 加载全局事件
        $this->app->event->listenEvents($listen);

        // 执行初始化任务
        foreach ($addonsInitEvent as $k => $v) {
            $this->app->event->trigger('AddonsInit', $v);
        }
    }

    /**
     * 加载文件事件服务
     * @param $files
     * @return bool
     */
    public function loadAddonsEventFiles($files){
        if(!is_array($files) || !$files) return [];
        $addonPath = $this->getRootPath();
        foreach ($files as $file){
            $event = include $addonPath . $file;
            if(is_array($event) && $event){
                $this->app->loadEvent($event);
            }
        }
        return $this;
    }

    /**
     * 加载所有插件配置
     * @return array|mixed
     * @throws \ReflectionException
     */
    public function loadAddonsSystemConfig($cacheName)
    {
        // 所有配置
        $addonsConfig   = [];
        // 非调试模式并且有缓存
        if(!$this->app->isDebug() && Cache::has($cacheName)){
            $addonsConfig = Cache::get($cacheName, []);
        }else{
            $addons = $this->app->addons->getAddons();
            $addonPath = $this->app->addons->getRootPath();
            // 初始化参数
            $routes = $events = $event_files = $commands = [];
            // 全局配置参数
            $services = Config::get('addons.addons_service', []);
            foreach ($addons as $addon){
                // 服务文件
                if(is_file($addonPath . $addon . DIRECTORY_SEPARATOR . 'provider.php')){
                    try {
                        $service = include $addonPath . $addon . DIRECTORY_SEPARATOR . 'provider.php';
                        if(is_array($service) && count($service) > 0){
                            $services = array_merge($services, $service);
                        }
                    }catch (\Exception $e){
                        continue;
                    }
                }
                // 应用插件事件文件
                if(is_file($addonPath . $addon . DIRECTORY_SEPARATOR . 'event.php')){
                    $event_files[$addon] = $addon . DIRECTORY_SEPARATOR . 'event.php';
                }
                // 系统配置
                $config = $this->getConfig($addon);
                // 自动路由
                $routes = array_merge($routes, $config['route'] ?: []);
                // 自动命令行
                $commands = array_merge($commands, $config['commands'] ?: []);
                // 解析插件事件
                $addonsEvents = $this->parseAddonEvent($addon);
                if($addonsEvents) $events[$addon] = $addonsEvents;
            }
            // config 配置信息
            $addonsConfig['services']    = $services;
            $addonsConfig['routes']      = $routes;
            // 事件
            $addonsConfig['events']      = $events;
            $addonsConfig['event_files']  = $event_files;
            // 命令行
            $addonsConfig['commands']    = $commands;
            // cache
            Cache::set($cacheName, $addonsConfig);
        }
        return $addonsConfig;
    }

    /**
     * 解析插件中事件
     * @param string $name
     * @return array
     * @throws \ReflectionException
     */
    public function parseAddonEvent(string $name)
    {
        $class = get_addons_class($name);
        // 类不存在返回
        if(!$class) return [];
        $baseMethods   = get_class_methods('\\think\\Addons');
        $addonsMethods = get_class_methods($class);
        // 差异方法
        $methods = array_diff($addonsMethods, $baseMethods);
        // 事件
        $events = [];
        foreach ($methods as $method){
            // 事件数组
            $event = [];
            // 反射类
            $reflection = new \ReflectionMethod($class, $method);
            // 判断是否可以访问
            if($reflection->isPublic()){
                // 注释参数,监听多个事件或者重新命名
                $params = $this->parseNameAnnotate($reflection, 'Event');
                if(is_array($params) && count($params) > 0){
                    foreach ($params as $param){
                        $event = array_merge($event, (array)$param);
                    }
                }else{
                    // 没有重命名的事件
                    array_push($event, $method);
                }
                // 重组
                foreach ($event as $e){
                    if(isset($events[$e])){
                        array_push($events[$e], [$class, $method]);
                    }else{
                        $events[$e][] = [$class, $method];
                    }
                }
            }
        }
        // 返回事件
        return $events ?: [];
    }

    /**
     * 获取注解
     * @param \Reflector $reflection
     * @return array
     */
    public function parseAnnotate(\Reflector $reflection): array
    {
        $document = $reflection->getDocComment();
        $document = substr($document, 3, -2);
        $annotations = [];
        if(preg_match_all('/@(?<name>[A-Za-z_-]+)[\s\t]*\((?<args>(?:(?!\)).)*)\)\r?/s', $document, $matches) !== false){
            foreach ($matches[1] as $k => $v){
                $annotations[$v] = isset($matches['args'][$k]) ? json_decode($matches['args'][$k], true) : '';
            }
        }
        return $annotations;
    }

    /**
     * 获取指定类型的注解
     * @param \Reflector $reflection
     * @param $name
     * @return array
     */
    public function parseNameAnnotate(\Reflector $reflection, $name)
    {
        $annotations = [];
        $document = $reflection->getDocComment();
        $document = substr($document, 3, -2);
        if (preg_match_all('/@' . $name . '(?:\s*(?:\(\s*)?(.*?)(?:\s*\))?)??\s*(?:\n|\*\/)/', $document, $matches)) {
            foreach ($matches[1] as $k => $v){
                $value =  isset($matches[1][$k]) ? json_decode($matches[1][$k], true) : '';
                if(!in_array($value, $annotations)) $annotations[] = $value;
            }
        }
        return $annotations;
    }

    /**
     * 获取配置
     * @param $name
     * @return array
     */
    public function getConfig($name): array
    {
        $configName = $this->app->addons->addonsConfigPrefix . $name;
        // 存在直接返回
        if(Config::has($configName)) return Config::get($configName, []);
        // 配置文件
        $configPath = $this->app->addons->getRootPath() . $name . DIRECTORY_SEPARATOR . 'config.php';
        if(!is_file($configPath)) return [];
        // 加载配置
        Config::load($configPath, $configName);
        return  Config::get($configName, []);
    }

    /**
     * 当前插件访问路径
     * @return string
     */
    public function getAddonPath(): string
    {
        $rootPath  = $this->getRootPath();
        $addonName = $this->getAddonName();
        return  $rootPath . $addonName . DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     */
    public function getRootPath()
    {
        $addonsPath = $this->app->getRootPath() . $this->getNamespace() . DIRECTORY_SEPARATOR;
        if(!is_dir($addonsPath))  @mkdir($addons_path, 0755, true);
        return $addonsPath;
    }

    /**
     * 获取插件 namespace
     * @return string
     */
    public function getNamespace(): string
    {
        return Config::get('addons.addon_namespace') ?: 'addons';
    }
}