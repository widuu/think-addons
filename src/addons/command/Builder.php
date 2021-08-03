<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\addons\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Config;

class Builder extends Command
{
    /**
     * 插件基础目录
     * @var string
     */
    protected $basePath;

    /**
     * 命名空间
     * @var string
     */
    protected $namespace;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('build')
            ->addArgument('addons', Argument::REQUIRED, 'addons name .')
            ->setDescription('Build Addons Dirs');
    }

    protected function execute(Input $input, Output $output)
    {
        // 命名空间
        $this->namespace = Config::get('addons.app_namespace', 'addons');
        // 插件根目录
        $this->basePath  = $this->app->getRootPath() . $this->namespace . DIRECTORY_SEPARATOR;
        // 插件名称
        $addon           = strtolower($input->getArgument('addons'));
        // 创建插件
        $res = $this->buildAddons($addon, $output);
        // 返回成功
        $res && $output->writeln("<info>Successed</info>");
    }

    /**
     * 创建插件
     * @access protected
     * @param  string $addon  插件名
     * @return void
     */
    protected function buildAddons(string $addon, Output $output)
    {
        if (!is_dir($this->basePath . $addon)) {
            // 创建应用目录
            mkdir($this->basePath . $addon);
        }else{
            $output->error("Addon directory already exists");
            return false;
        }

        $appPath   = $this->basePath . $addon . DIRECTORY_SEPARATOR;
        $namespace = $this->namespace . '\\' . $addon;
        // 创建配置文件和公共文件
        $this->buildCommon($addon);

        $dirs = ['controller', 'view', 'model'];

        // 生成子目录
        foreach ($dirs as $dir) {
            $this->checkDirBuild($appPath . $dir);
        }

        $this->buildHello($addon);
    }

    /**
     * 创建应用的欢迎页面
     * @access protected
     * @param  string $addon 目录
     * @param  string $namespace 类库命名空间
     * @return void
     */
    protected function buildHello(string $addon): void
    {
        $suffix   = $this->app->config->get('route.controller_suffix') ? 'Controller' : '';
        $filename = $this->basePath . $addon . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . 'Index' . $suffix . '.php';

        if (!is_file($filename)) {
            $content = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'controller.stub');
            $content = str_replace(['{%name%}', '{%app%}', '{%layer%}', '{%suffix%}'], [$addon, $this->namespace, $addon .'\\controller', $suffix], $content);
            $this->checkDirBuild(dirname($filename));

            file_put_contents($filename, $content);
        }
    }

    /**
     * 创建应用的公共文件
     * @access protected
     * @param  string $addon 目录
     * @return void
     */
    protected function buildCommon(string $addon): void
    {
        $appPath = $this->basePath . $addon . DIRECTORY_SEPARATOR;

        if (!is_file($appPath . 'common.php')) {
            file_put_contents($appPath . 'common.php', "<?php" . PHP_EOL . "// 这是系统自动生成的公共文件" . PHP_EOL);
        }

        if(!is_file($appPath . 'config.php')){
            $config = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'config.stub');
            file_put_contents($appPath . 'config.php', $config);
        }

        if(!is_file($appPath . 'info.ini')) @touch($appPath . 'info.ini');

        $class = ucfirst(strtolower($addon));
        // 创建插件类
        if(!is_file($appPath . $class . '.php')){
            $content = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'addons.stub');
            $content = str_replace(['{%namespace%}', '{%addon%}', '{%class%}'], [$this->namespace, $addon, $class], $content);
            file_put_contents($appPath . $class . '.php', $content);
        }

        foreach (['event', 'middleware', 'common', 'provider'] as $name) {
            if (!is_file($appPath . $name . '.php')) {
                file_put_contents($appPath . $name . '.php', "<?php" . PHP_EOL . "// 这是系统自动生成的{$name}定义文件" . PHP_EOL . "return [" . PHP_EOL . PHP_EOL . "];" . PHP_EOL);
            }
        }
    }

    /**
     * 创建目录
     * @access protected
     * @param  string $dirname 目录名称
     * @return void
     */
    protected function checkDirBuild(string $dirname): void
    {
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
    }
}
