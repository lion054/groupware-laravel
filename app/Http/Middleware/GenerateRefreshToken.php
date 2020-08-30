<?php

namespace App\Http\Middleware;

use Closure;

class GenerateRefreshToken extends GenerateToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $response = $next($request);
        $content = $response-> getOriginalContent();

        if (isset($content['user'])) {
            $content['refresh_token'] = $this->makeToken('refresh', time(), $content['user']['uuid']);
            $response->setContent($content);
        }

        return $response;
    }
}
