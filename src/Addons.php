<?php

namespace think;

use think\App;

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
     * Addons constructor.
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;
        // 初始化任务
        method_exists($this->_initialize()) && $this->_initialize();
    }

    
}