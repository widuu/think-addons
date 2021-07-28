<?php

namespace think\addons;

use think\Service as BaseService;

class Service extends BaseService
{
    public function boot()
    {
        $this->app->event->listen('HttpRun', function () {
            $this->app->middleware->add(Addons::class);
        });

        $this->commands([
            'build' => command\Build::class,
            'clear' => command\Clear::class,
        ]);
    }
}
