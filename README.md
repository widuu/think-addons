# think-addons

> tp6 (Thinkphp) 插件扩展包，更友好兼容 `Thinkphp` 原生框架。

>插件路由分发器 `think\addons\dispatch\Controller` 继承 `think\route\Dispatch`

>插件支持 `event`/`middleware`/`provider` 配置，没有将事件封装成 `hook` 函数，使用原生 `\think\facade\Event` 操作，让开发者能够快速上手，如果你习惯使用 `hook` 函数，只需要将下边的方法添加到 `app\common.php` 中即可

```
    /**
     * 处理插件钩子
     * @param string $event 钩子名称
     * @param array|null $params 传入参数
     * @param bool $once 是否只返回一个结果
     * @return mixed
     */
    function hook($event, $params = null, bool $once = false)
    {
        $result = Event::trigger($event, $params, $once);

        return join('', $result);
    }
```

> 更细化的配置，能够根据配置来进行按需加载

### 安装

```
composer require widuu/think-addons
```

### 全局配置

> `config\addons.php` 文件

```
return [
    // 插件的 namespace 也是插件目录
    'app_namespace'           => 'addons',
    // 生产环境下，开启自动加载时的缓存名称，方便更新
    'addons_autoload_cache'   => 'addons_autoload_cache',
    // 自动解析加载插件事件，此为全局事件，可以在全局访问
    'autoload_addons_event'   => true,
    // 全局加载插件下的 event.php
    'autoload_addons_efile'   => false,
    // 自动解析服务注册，全局服务，可以在全局访问
    'autoload_addons_service' => false,
    // 自动注册路由
    'autoload_addons_route'   => true,
    // 自动注册命令行
    'autoload_addons_command' => true,
    // 监听事件
    'addons_event'            => [],
    // 注册服务
    'addons_service'          => [],
    // 注册命令行
    'addons_commands'         => [],
    // 注册路由
    'route'                   => [],
];
```
> 当运行到某个插件时譬如 `test` 插件，会自动加载插件下的 (公共函数) `common.php` （服务注册） `provider.php` (事件) `event.php` 和 `Test.php` 中的事件。 

> 但是 `provider.php`/`event.php`/`Test.php` 文件中的事件和服务并不全局调用，假如你想在任何地方调用，可以开启上方的自动加载或者将这些配置到对应的配置数组中

> 注：生产环境中（非 `Debug`）模式，会在缓存读取，所以有新的插件记住清除缓存，缓存名称在配置中自定义的。

### 生成插件


> 使用如下命令行可以自动生成插件

```
php think addon:build 模块名称
```

> 目录如下

```html
www  WEB部署目录（或者子目录）
├─addons           插件目录
│  ├─test            test 插件目录
│  │  ├─controller         控制器目录
│  │  ├─model              模型目录
│  │  ├─ ...               更多类库目录
│  │  │
│  │  ├─Test.php           插件类继承 think\Addons
│  │  ├─info.ini           插件信息
│  │  ├─config.php         插件配置
│  │  ├─common.php         公共函数文件
│  │  ├─middleware.php     中间件文件
│  │  ├─provider.php       服务注册文件
│  │  └─event.php          事件定义文件
├─app           应用目录
├─config                配置目录
│  ├─addons.php         插件配置
│  └─ ...               更多配置
├─view                  视图目录
├─route                 路由定义目录 
├─public                WEB目录（对外访问目录）
├─extend                扩展类库目录
├─runtime               应用的运行时目录（可写，可定制）
├─vendor                Composer类库目录
├─.example.env          环境变量示例文件
├─composer.json         composer 定义文件
├─LICENSE.txt           授权说明文件
├─README.md             README 文件
├─think                 命令行入口文件
```

> 我们插件实现类中可以通过注释修改事件名称，以 `Test.php` 插件为类

```php
<?php


namespace addons\test;


use think\Addons;

/**
 * Class Test
 * @package addons\test
 */
class Test extends Addons
{

    /**
     * @Event("AppInit")
     */
    public function init()
    {
        echo "监听 AppInit 事件";
    }

    /**
     * @Event(["AppInit", "HttpRun"])
     */
    public function run()
    {
        echo "监听 AppInit 和 HttpRun 事件";
    }

    /**
     * 没有使用注释，直接监听方法名，监听 AddonsInit 事件
     */
    public function AddonsInit()
    {
        echo "监听 AddonsInit 事件";
    }

    protected function install()
    {
        // TODO: Implement install() method.
    }

    protected function uninstall()
    {
        // TODO: Implement uninstall() method.
    }

    protected function enable()
    {
        // TODO: Implement enable() method.
    }

    protected function disable()
    {
        // TODO: Implement disable() method.
    }
}
```

### 插件公共方法

```
    /**
     * 获取插件类名
     * @param $name
     * @param null $class
     * @param string $type
     * @return string
     */
    function get_addons_class($name, $class = null, $type = '')

    /**
     * 获取插件实例
     * @param $name
     * @return false|mixed
     */
    function get_addons_instance($name)

    /**
     * 获取应用信息
     * @param $name
     * @return array
     */
    function get_addons_info($name): array

    /**
     * 插件显示内容里生成访问插件的url
     * @param $url  string 插件名/控制器/方法
     * @param array $param
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return bool|string
     */
    function addons_url($url = '', $param = [], $suffix = true, $domain = false)
```

> `addons_url` 中的 `$url` 在插件内可以 `controller/action` 或者 `action`，如果全局使用，`addon/controller/action`

> 其它使用方法请参考 thinkphp 手册即可