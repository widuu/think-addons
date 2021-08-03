<?php

namespace think\addons\dispatch;

use think\App;
use think\exception\ClassNotFoundException;
use think\exception\HttpException;
use think\facade\Lang;
use think\helper\Str;
use think\route\Dispatch;
use ReflectionClass;

class Controller extends Dispatch
{
    /**
     * 控制器名
     * @var string
     */
    private $controller;

    /**
     * 操作名
     * @var string
     */
    private $actionName;

    /**
     * 插件名称
     * @var string
     */
    private $addonName;

    /**
     * 命名空间
     * @var string
     */
    private $namespace;

    /**
     * 当前应用目录
     * @var string
     */
    private $addonPath;

    /**
     * @param App $app
     */
    public function init(App $app)
    {
        parent::init($app);
        // 插件目录
        $this->addonPath = $app->addons->getAddonPath();
        if(!is_dir($this->addonPath)) throw new HttpException(404, 'addons not exists:' . $this->addonName);
        // 命名空间
        $this->namespace = $app->addons->getNamespace();
        // 插件名称
        $this->request->addons = $this->addonName = strip_tags($this->request->route('addon'));
        // 操作名
        $controller = strip_tags($this->request->route('controller'));
        // 解析 controller
        if (strpos($controller, '.')) {
            $pos              = strrpos($controller, '.');
            $this->controller = substr($controller, 0, $pos) . '.' . Str::studly(substr($controller, $pos + 1));
        } else {
            $this->controller = Str::studly($controller);
        }
        // 操作方法
        $this->actionName = strip_tags($this->request->route('action'));

        // 加载应用
        $this->load();

        // 更改视图
        $this->app->setNamespace($this->namespace)->request->setController($this->controller)->setAction($this->actionName);
        $config = $this->app->config->get('view');
        $config['view_path'] = $this->addonPath . 'view' . DIRECTORY_SEPARATOR;
        $this->app->config->set($config, 'view');

        // log 日志目录
        $this->app->setRuntimePath($app->getRuntimePath() . $this->namespace . DIRECTORY_SEPARATOR . $this->addonName .DIRECTORY_SEPARATOR);
    }

    /**
     * 插件路由调度器
     * @return mixed
     * @throws \ReflectionException
     */
    public function exec()
    {
        // 运行事件
        $this->app->event->trigger('AddonsRun');

        try {
            // 实例化控制器
            $instance = $this->controller($this->addonName, $this->controller);
        } catch (ClassNotFoundException $e) {
            throw new HttpException(404, 'controller not exists:' . $e->getClass());
        }

        // 注册控制器中间件
        $this->registerControllerMiddleware($instance);

        // 获取当前操作名
        $suffix  = $this->app->route->config('action_suffix');
        $action = $this->actionName . $suffix;

        if (is_callable([$instance, $action])) {
            $vars = $this->request->param();
            try {
                $reflect = new \ReflectionMethod($instance, $action);
                // 严格获取当前操作方法名
                $actionName = $reflect->getName();
                if ($suffix) {
                    $actionName = substr($actionName, 0, -strlen($suffix));
                }

                $this->request->setAction($actionName);
            } catch (ReflectionException $e) {
                $reflect = new ReflectionMethod($instance, '__call');
                $vars    = [$action, $vars];
                $this->request->setAction($action);
            }
        } else {
            // 操作不存在
            throw new HttpException(404, 'method not exists:' . get_class($instance) . '->' . $action . '()');
        }

        $data = $this->app->invokeReflectMethod($instance, $reflect, $vars);

        // 结束事件
        $this->app->event->trigger('AddonsEnd');

        return $data;
    }

    /**
     * 使用反射机制注册控制器中间件
     * @access public
     * @param object $controller 控制器实例
     * @return void
     */
    protected function registerControllerMiddleware($controller): void
    {
        $class = new ReflectionClass($controller);

        if ($class->hasProperty('middleware')) {
            $reflectionProperty = $class->getProperty('middleware');
            $reflectionProperty->setAccessible(true);

            $middlewares = $reflectionProperty->getValue($controller);

            foreach ($middlewares as $key => $val) {
                if (!is_int($key)) {
                    if (isset($val['only']) && !in_array($this->request->action(true), array_map(function ($item) {
                            return strtolower($item);
                        }, is_string($val['only']) ? explode(",", $val['only']) : $val['only']))) {
                        continue;
                    } elseif (isset($val['except']) && in_array($this->request->action(true), array_map(function ($item) {
                            return strtolower($item);
                        }, is_string($val['except']) ? explode(',', $val['except']) : $val['except']))) {
                        continue;
                    } else {
                        $val = $key;
                    }
                }

                if (is_string($val) && strpos($val, ':')) {
                    $val = explode(':', $val);
                    if (count($val) > 1) {
                        $val = [$val[0], array_slice($val, 1)];
                    }
                }

                $this->app->middleware->controller($val);
            }
        }
    }

    /**
     * 解析设置controller
     * @param string $addon
     * @param string $name
     * @return mixed
     */
    public function controller(string $addon, string $name)
    {
        $controllerLayer = $addon .'\\controller';
        $emptyController = 'Error';

        $class = $this->app->parseClass($controllerLayer, $name);

        if (class_exists($class)) {
            return $this->app->make($class, [], true);
        } elseif ($emptyController && class_exists($emptyClass = $this->app->parseClass($controllerLayer, $emptyController))) {
            return $this->app->make($emptyClass, [], true);
        }

        throw new ClassNotFoundException('class not exists:' . $class, $class);
    }

    /**
     * 加载应用
     */
    public function load()
    {
        // 配置前缀
        $configPrefix = $this->app->addons->addonsConfigPrefix;
        // 加载配置文件
        is_file($this->addonPath . 'config.php') && $this->app->config->load($this->addonPath . 'config.php', $configPrefix . $this->addonName);
        // 加载自定义函数
        is_file($this->addonPath . 'common.php')  && include_once $this->addonPath . 'common.php';
        // 加载容器
        if(!$this->app->config->get('addons.autoload_addons_service', false)) {
            is_file($this->addonPath . 'provider.php') && $this->app->bind(include $this->addonPath . 'provider.php');
        }
        // 加载事件
        if(!$this->app->config->get('addons.autoload_addons_efile', false)){
            is_file($this->addonPath . 'event.php') &&  $this->app->loadEvent(include $this->addonPath . 'event.php');
        }
        // 中间件
        is_file($this->addonPath . 'middleware.php') &&  $this->app->middleware->import(include $this->addonPath. 'middleware.php', 'route');
        // 加载语言包
        $this->loadLangPack($this->app->lang->defaultLangSet());
    }

    /**
     * 加载插件语言包
     * @param $langset
     */
    public function loadLangPack($langset)
    {
        if (empty($langset)) {
            return;
        }

        // 加载系统语言包
        $files = glob($this->addonPath . 'lang' . DIRECTORY_SEPARATOR . $langset . '.*');
        Lang::load($files);

        // 加载扩展（自定义）语言包
        $list = $this->app->config->get('lang.extend_list', []);

        if (isset($list[$langset])) {
            Lang::load($list[$langset]);
        }
    }
}