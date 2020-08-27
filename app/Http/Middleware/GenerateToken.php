<?php

namespace App\Http\Middleware;

use GraphAware\Neo4j\Client\ClientBuilder;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;

use Closure;

class GenerateToken
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
            $now = time();
            $ttl = config('jwt.ttl');

            $builder = new Builder();
            $builder->issuedBy(config('jwt.iss')); // iss -> Issuer Claim
            $builder->relatedTo($content['user']['uuid']); // sub -> Subject Claim
            $builder->permittedFor(config('jwt.aud')); // aud -> Audience Claim
            $builder->expiresAt($now + $ttl * 60); // exp -> Expiration Time Claim
            $builder->canOnlyBeUsedAfter($now); // nbf -> Not Before Claim
            $builder->issuedAt($now); // iat -> Issued At Claim
            $builder->identifiedBy($content['user']['uuid'] . '-default', true); // jti -> JWT ID Claim

            $signer = new Sha256();
            $secret = config('jwt.secret');

            $token = $builder->getToken($signer, new Key($secret));

            $content['expires'] = $ttl * 60;
            $content['token'] = $token->__toString();
            $content['type'] = 'bearer';
            $response->setContent($content);
        }

        return $response;
    }
}
