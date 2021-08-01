<?php

namespace think\addons;

use think\App as BaseApp;

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
        $this->addons = array_diff(scandir($this->addonPath), ['.', '..']);
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
            // 跳过非目录
            if(!is_dir($addonPath)) continue;
            // 跳过非插件
            if(!is_file($addonPath . ucfirst($addon) . '.php')) continue;
            $currentAddon = [ 'addonName' => $addon, 'path' => $addonPath];
            // 插件信息
            if(is_file($addonPath . 'info.ini')){
                $info = parse_ini_file($addonPath . 'info.ini', true, INI_SCANNER_TYPED) ?: [];
                $info['url'] = addons_url($addon)->build();
            }else{
                $info = [];
            }
            $currentAddon['info'] = $info;
            array_push($list,  $currentAddon);
        }
        return $list;
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
        return $this->app->config->get('addons.addon_namespace') ?: 'addons';
    }
}