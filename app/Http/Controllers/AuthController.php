<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Lcobucci\JWT\Parser;

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
                'success' => false,
                'error' => 'Unregistered email',
            ];
        }
        $user = $node->values();
        if (isset($user['deleted_at'])) {
            return [
                'success' => false,
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
        $parser = new Parser();
        $token = $parser->parse($value);

        return [
            'user' => $request->user(),
            'expires' => $token->getClaim('exp') - time(), // remaining time
        ];
    }

    public function refresh(Request $request)
    {
        // token will be filled by "token.generate"
        return [
            'user' => $request->user(),
        ];
    }
}
