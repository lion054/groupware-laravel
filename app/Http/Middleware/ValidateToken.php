<?php

namespace App\Http\Middleware;

use Lcobucci\JWT\Claim\Validatable;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;

use Closure;

use App\Exceptions\InvalidTokenDataException;
use App\Exceptions\TokenExpiredException;
use App\Exceptions\TokenNotFoundException;
use App\Exceptions\TokenNotVerifiedException;
use App\Exceptions\UserNotFoundException;

abstract class ValidateToken
{
    protected function getTokenCategory($request)
    {
        $value = $request->bearerToken();
        if (empty($value))
            throw new TokenNotFoundException();

        $parser = new Parser();
        $token = $parser->parse($value);

        $signer = new Sha256();
        $secret = config('jwt.secret');
        if (!$token->verify($signer, $secret))
            throw new TokenNotVerifiedException();

        $leeway = config('jwt.leeway');
        $data = new ValidationData(null, $leeway);
        $data->setIssuer(config('jwt.iss'));
        $data->setAudience(config('jwt.aud'));

        $claims = $this->getValidatableClaims($token);
        foreach ($claims as $claim) {
            if ($claim->validate($data))
                continue;
            if ($claim->getName() == 'exp')
                throw new TokenExpiredException();
            else
                throw new InvalidTokenDataException();
        }

        $query = [
            'MATCH (u:User{ uuid: {uuid} })',
            'RETURN u',
        ];
        $result = app('neo4j')->run(implode(' ', $query), [
            'uuid' => $token->getClaim('sub'),
        ]);

        if ($result->size() == 0)
            throw new UserNotFoundException();

        $user = $result->getRecord()->get('u')->values();
        if (isset($user['password']))
            unset($user['password']);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $token->getClaim('cat');
    }

    private function getValidatableClaims(Token $token)
    {
        foreach ($token->getClaims() as $claim) {
            if ($claim instanceof Validatable)
                yield $claim;
        }
    }
}
