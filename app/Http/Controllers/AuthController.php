<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
