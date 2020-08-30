<?php

namespace App\Http\Middleware;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Hmac\Sha384;
use Lcobucci\JWT\Signer\Key;

use Closure;

class GenerateAccessToken extends GenerateToken
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
            $content['expires'] = config('jwt.ttl') * 60; // in seconds
            $content['access_token'] = $this->makeToken('access', time(), $content['user']['uuid']);
            $content['type'] = 'bearer';
            $response->setContent($content);
        }

        return $response;
    }
}
