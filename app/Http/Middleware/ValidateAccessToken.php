<?php

namespace App\Http\Middleware;

use Closure;

use App\Exceptions\InvalidTokenCategoryException;

class ValidateAccessToken extends ValidateToken
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
        if ($this->getTokenCategory($request) != 'access')
            throw new InvalidTokenCategoryException();

        return $next($request);
    }
}
