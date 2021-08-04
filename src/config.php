<?php
/**
 * 插件中的事件和服务，只能在当前插件中使用，如果全局使用，有两种方法
 * 一 addons.php 中 addons_event 添加全局事件，这样可以在全局访问事件
 * 二 开启 autoload_addons_event 会自动绑定插件事件，譬如 test 插件中的 Test.php 的 HttpRun 事件就会自动绑定
 * 三 开启加载全局会加载各个插件下的 event.php
 *
 * 全局服务也同理
 * 一 addons.php 中 addons_service 注册
 * 二 开启 autoload_addons_service 会自动注册，插件目录下的 provider.php 中的服务
 *
 * 自动注册路由开启后，会将插件下 config.php 中的 `route` 中的路由自动注册起来
 *
 * 开启自动解析非 debug 模式下为了执行效率，会生成 cache 来缓存，使用中，当差价更新后及时更新缓存
 */
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