<?php

namespace think;

use think\App;
use think\facade\Config;
use think\facade\View;
use think\facade\Cache;

/**
 * Class Addons
 * @package think
 */
abstract class Addons
{
    /**
     * @var \think\App
     */
    private $app;

    /**
     * @var \think\Request
     */
    private $request;

    /**
     * 插件名称
     * @var string
     */
    private $addonName;

    /**
     * 插件配置前缀
     * @var string
     */
    private $configPrefix;

    /**
     * 视图
     * @var \think\View
     */
    protected $view;

    /**
     * Addons constructor.
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app        = $app;
        $this->request    = $this->app->request;
        $this->addonName  = $this->getName();
        // 配置前缀
        $this->configPrefix = $this->app->addons->addonsConfigPrefix;
        // 视图，路由调度器已经配置
        $this->view       = app('view');
        // 初始化操作
        $this->initialize();
    }

    /**
     * 初始化操作
     */
    protected function initialize()
    {}

    /**
     * 获取配置
     * @param string $name
     * @param false $force
     * @return array
     */
    final public function getConfig($name = '') : array
    {
        $name = $name ?: $this->addonName;
        // 是否是插件调用
        $isAddon = request()->addons ?: false;
        // 插件调用直接返回配置或者非强制更新,一般用于插件内部调用
        if($isAddon){
            return Config::get($this->configPrefix . $name, []);
        }
        // 其它应用调用
        $configName = $this->configPrefix . $name;
        // 防止整个流程中多次加载
        if(!Config::has($configName)){
            $configPath = $this->app->addons->getRootPath() . $name . DIRECTORY_SEPARATOR . 'config.php';
            if(!is_file($configPath)) return [];
            Config::load($configPath, $configName);
        }
        return Config::get($configName, []);
    }

    /**
     * 获取插件信息
     * @param string $name
     * @param false $force 强制更新
     * @return array
     */
    public function getInfo($name = '', $force = false): array
    {
        $name = $name ?: $this->addonName;
        // 插件信息
        $infoFile = $this->getPath() . 'info.ini';
        if(!is_file($infoFile)) return [];
        // 其它应用调用
        $cacheName = 'addons_info_' . $name;
        // 非强制更新，并且缓存存在的情况下
        if(!$force && Cache::has($cacheName)){
            return Cache::get($cacheName, []);
        }
        // 解析信息
        $info = parse_ini_file($infoFile, true, INI_SCANNER_TYPED) ?: [];
        $info['url'] = addons_url()->build();
        Cache::set($cacheName, $info);
        // 返回插件信息
        return $info;
    }

    /**
     * 获取插件名称
     * @return mixed|string|null
     */
    final public function getName()
    {
        $namespace = explode('\\', get_class($this));
        return strtolower(array_pop($namespace));
    }

    /**
     * 插件路径
     * @return string
     */
    final public function getPath(): string
    {
        return $this->app->addons->getRootPath() . $this->addonName . DIRECTORY_SEPARATOR;
    }

    /**
     * 插件安装
     * @return mixed
     */
//    abstract public function install();

    /**
     * 插件卸载
     * @return mixed
     */
//    abstract public function uninstall();
    
}