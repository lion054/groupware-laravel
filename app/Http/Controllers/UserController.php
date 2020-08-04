<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $company_uuid = $request->query('company_uuid');
        $department_uuid = $request->query('department_uuid');
        $search = $request->query('search');
        $sort_by = $request->query('sort_by');
        $limit = $request->query('limit');

        $query = ['MATCH (u:User)'];
        if (!empty($search))
            $query[] = 'WHERE u.name CONTAINS {search}';
        $query[] = 'RETURN u';
        if (!empty($limit))
            $query[] = 'LIMIT {limit}';

        $records = $this->client->run(implode(' ', $query), [
            'search' => $search,
            'limit' => intval($limit),
        ])->getRecords();
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

    public function show($uuid)
    {
        $record = $this->client->run('MATCH (u:User{ uuid: {uuid} }) RETURN u', [
            'uuid' => $uuid,
        ])->getRecord();
        $result = $record->get('u');
        return $result;
    }
}
