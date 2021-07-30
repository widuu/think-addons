<?php

namespace think\addons\middleware;

use think\facade\Event;

/**
 * Addons 中间件事件
 * Class Addons
 * @package think\addons\middleware
 */
class Addons
{

    /**
     * @param $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        Event::trigger('addons_middleware', $request);
        return $next($request);
    }
}