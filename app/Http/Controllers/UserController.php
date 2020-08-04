<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $records = $this->client->run('MATCH (u:User) RETURN u')->getRecords();
        $result = [];
        foreach ($records as $record) {
            $user = $record->get('u');
            $data = $user->values();
            if (isset($data['password']))
                unset($data['password']);
            $result[] = $data;
        }
        return $result;
    }
}
