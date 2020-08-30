<?php

namespace App\Http\Middleware;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Hmac\Sha384;
use Lcobucci\JWT\Signer\Key;

use Closure;

abstract class GenerateToken
{
    protected function makeToken($category, $now, $user_id)
    {
        $ttl = 0;
        switch ($category) {
            case 'access':
                $ttl = config('jwt.ttl');
                break;
            case 'refresh':
                $ttl = 14 * 24 * 60; // 2 weeks
                break;
        }
        $builder = new Builder();
        $builder->issuedBy(config('jwt.iss')); // iss -> Issuer Claim
        $builder->relatedTo($user_id); // sub -> Subject Claim
        $builder->permittedFor(config('jwt.aud')); // aud -> Audience Claim
        $builder->expiresAt($now + $ttl * 60); // exp -> Expiration Time Claim
        $builder->canOnlyBeUsedAfter($now); // nbf -> Not Before Claim
        $builder->issuedAt($now); // iat -> Issued At Claim
        $builder->identifiedBy(uniqid(), true); // jti -> JWT ID Claim
        $builder->withClaim('cat', $category);

        $signer = new Sha256();
        $token = $builder->getToken($signer, new Key(config('jwt.secret')));
        return $token->__toString();
    }
}
