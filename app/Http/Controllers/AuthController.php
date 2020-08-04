<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

use App\User;

class AuthController extends Controller
{
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

        // check user validaity
        $user = User::withTrashed()->where('email', $credentials['email'])->first(); // The trashed one is called
        if (empty($user)) {
            return [
                'success' => FALSE,
                'error' => 'Unregistered email',
            ];
        }
        if ($user->trashed()) {
            return [
                'success' => FALSE,
                'error' => 'This account was deleted',
            ];
        }

        // try to auth and get the token using api authentication
        $ttl = config('jwt.ttl') * 60; // in seconds
        auth('api')->factory()->setTTL($ttl);
        if (!$token = auth('api')->attempt($credentials)) {
            // if the credentials are wrong we send an unauthorized error in json format
            return [
                'success' => FALSE,
                'error' => 'Incorrect password',
            ];
        }
        $team = $user->team;
        return response()->json([
            'token' => $token,
            'type' => 'bearer', // you can ommit this
            'expires' => auth('api')->factory()->getTTL(), // time to expiration
            'user' => $user,
        ]);
    }

    public function verify(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return [
                'success' => FALSE,
                'error' => 'Token expired',
            ];
        } catch (TokenInvalidException $e) {
            return [
                'success' => FALSE,
                'error' => 'Token invalid',
            ];
        } catch (TokenBlacklistedException $e) {
            return [
                'success' => FALSE,
                'error' => 'Token blacklisted',
            ];
        } catch (JWTException $e) {
            return [
                'success' => FALSE,
                'error' => 'Token not provided',
            ];
        }
        if ($user)
            return $user;
        return [
            'success' => FALSE,
            'error' => 'Invalid user', // It was just deleted
        ];
    }
}
