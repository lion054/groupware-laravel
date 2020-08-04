<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use JWTAuth;
use Ramsey\Uuid\Uuid;

use App\Http\Controllers\Controller;
use App\User;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */
    public function loginWithEmailPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }

        // get email and password from request
        $credentials = $request->only('email', 'password');

        // check user validity
        $record = $this->client->run('MATCH (u:User{ email: {email} }) RETURN u', [
            'email' => $credentials['email'],
        ])->getRecord();
        if (!$record) {
            return [
                'success' => FALSE,
                'error' => 'Unregistered email',
            ];
        }
        $user = $record->get('u');
        if ($user->hasValue('deleted_at')) {
            return [
                'success' => FALSE,
                'error' => 'This account was deleted',
            ];
        }
        if (!Hash::check($credentials['password'], $user->value('password'))) {
            return [
                'success' => FALSE,
                'error' => 'Incorrect password',
            ];
        }

        $model = new User;
        foreach ($user->keys() as $key)
            $model->setAttribute($key, $user->value($key));

        // try to auth and get the token using api authentication
        $ttl = config('jwt.ttl') * 60; // in seconds
        auth('api')->factory()->setTTL($ttl);
        if (!$token = JWTAuth::fromUser($model)) {
            // if the credentials are wrong we send an unauthorized error in json format
            return [
                'success' => FALSE,
                'error' => 'Incorrect email/password',
            ];
        }
        return response()->json([
            'token' => $token,
            'type' => 'bearer', // you can ommit this
            'expires' => auth('api')->factory()->getTTL(), // time to expiration
            'user' => $model,
        ]);
    }
}
