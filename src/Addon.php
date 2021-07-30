<?php

namespace think\addons;

use think\App;

class Addon
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
     * Addon constructor.
     * @param App $app
     */
    public function __construct(App $app)
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
        return $this->app->request->route('addon');
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