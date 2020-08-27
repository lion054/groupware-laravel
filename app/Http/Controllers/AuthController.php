<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Lcobucci\JWT\Claim\Validatable;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;

class AuthController extends BaseController
{
    public function loginWithEmailPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // get email and password from request
        $credentials = $request->only('email', 'password');

        // check user validaity
        $node = $this->getNode([
            'email' => $credentials['email']
        ]);
        if (!$node) {
            return [
                'success' => FALSE,
                'error' => 'Unregistered email',
            ];
        }
        $user = $node->values();
        if (isset($user['deleted_at'])) {
            return [
                'success' => FALSE,
                'error' => 'This account was deleted',
            ];
        }
        if (isset($user['password']))
            unset($user['password']);

        return [
            'user' => $user,
        ];
    }

    public function verify(Request $request)
    {
        $value = $request->bearerToken();
        if (empty($value)) {
            return response()->json([
                'success' => false,
                'error' => 'Token not found',
            ], 401);
        }

        $parser = new Parser();
        $token = $parser->parse($value);

        $signer = new Sha256();
        $secret = config('jwt.secret');
        if (!$token->verify($signer, $secret)) {
            return response()->json([
                'success' => false,
                'error' => 'Token not verified',
            ], 401);
        }

        $leeway = config('jwt.leeway');
        $data = new ValidationData(null, $leeway);
        $data->setIssuer(config('jwt.iss'));
        $data->setAudience(config('jwt.aud'));

        $claims = $this->getValidatableClaims($token);
        foreach ($claims as $claim) {
            if ($claim->validate($data))
                continue;
            if ($claim->getName() == 'exp') {
                return response()->json([
                    'success' => false,
                    'error' => 'Token expired',
                ], 401);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid token data',
                ], 401);
            }
        }

        $query = [
            'MATCH (u:User{ uuid: {uuid} })',
            'RETURN u',
        ];
        $result = app('neo4j')->run(implode(' ', $query), [
            'uuid' => $token->getClaim('sub'),
        ]);

        if ($result->size() == 0) {
            return response()->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $user = $result->getRecord()->get('u')->values();

        return [
            'user' => $user,
            'expires' => $token->getClaim('exp'),
        ];
    }

    public function refresh(Request $request)
    {
        return []; // token will be filled by "token.generate"
    }

    private function getValidatableClaims(Token $token)
    {
        foreach ($token->getClaims() as $claim) {
            if ($claim instanceof Validatable)
                yield $claim;
        }
    }
}
