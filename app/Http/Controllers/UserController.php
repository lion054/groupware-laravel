<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'position' => 'required_with:department_uuid',
            'taked_at' => 'date',
            'left_at' => 'date',
        ]);
        $validator->after(function ($validator) use ($request) {
            if (!$validator->errors()->has('email')) {
                if (!$this->checkUnique('User', 'email', $request->input('email')))
                    $validator->errors()->add('email', 'This email was registered already');
            }
        });
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }

        $user = $this->createNode('User', [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ]);

        $department_uuid = $request->input('department_uuid');
        if (!empty($department_uuid)) {
            $this->createRelation($user->value('uuid'), $department_uuid, 'WORKING_AT', [
                'position' => $request->input('position'),
                'took_at' => $request->input('took_at'),
                'left_at' => $request->input('left_at'),
            ]);
        }

        return $user->values();
    }

    public function update(Request $request, $uuid)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'email',
            'password' => 'min:6',
            'position' => 'required_with:department_uuid',
            'taked_at' => 'date',
            'left_at' => 'date',
        ]);
        $validator->after(function ($validator) use ($request, $uuid) {
            if (!$validator->errors()->has('email')) {
                if (!$this->checkUnique('User', 'email', $request->input('email'), $uuid))
                    $validator->errors()->add('email', 'This email was registered already');
            }
        });
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }

        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');
        $fields = [];
        if (!empty($name))
            $fields[] = 'u.name = {name}';
        if (!empty($email))
            $fields[] = 'u.email = {email}';
        if (!empty($name))
            $fields[] = 'u.password = {password}';
        if (!empty($fields)) {
            $record = $this->client->run('MATCH (u:User{ uuid: {uuid} }) SET ' . implode(', ', $fields) . ' RETURN u', [
                'uuid' => $uuid,
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
            ])->getRecord();
        } else {
            $record = $this->client->run('MATCH (u:User{ uuid: {uuid} }) RETURN u', [
                'uuid' => $uuid,
            ])->getRecord();
        }
        $user = $record->get('u');

        $department_uuid = $request->input('department_uuid');
        if (!empty($department_uuid)) {
            $record = $this->client->run('MATCH (u:User{ uuid: {uuid} })-->(d:Department) RETURN d', [
                'uuid' => $uuid,
            ])->getRecord();
            $newRelation = TRUE;
            if ($record) {
                $department = $record->get('d');
                if ($department_uuid == $department->value('uuid')) {
                    $this->client->run('MATCH (u:User{ uuid: {uuid} })-[r:WORKING_AT]->() DELETE r', [
                        'uuid' => $uuid,
                    ]);
                    $newRelation = FALSE;
                }
            }
            $position = $request->input('position');
            $took_at = $request->input('took_at');
            $left_at = $request->input('left_at');
            if ($newRelation) {
                $query = [
                    'MATCH (u:User),(d:Department)',
                    'WHERE u.uuid = {u_uuid} AND d.uuid = {d_uuid}',
                    'CREATE (u)-[r:WORKING_AT{',
                        'position: {position},',
                        'took_at: DATE({took_at}),',
                        'left_at: DATE({left_at})',
                    '}]->(d)',
                ];
                $this->client->run(implode(' ', $query), [
                    'u_uuid' => $uuid,
                    'd_uuid' => $department_uuid,
                    'position' => $position,
                    'took_at' => $took_at,
                    'left_at' => $left_at,
                ]);
            } else {
                $query = [
                    'MATCH (u:User{ uuid: {u_uuid} })-[r:WORKING_AT]->(d:Department{ uuid: {d_uuid} })'
                ];
                $fields = ['r.position = {position}'];
                if (!empty($took_at))
                    $fields[] = 'r.took_at = DATE({took_at})';
                if (!empty($left_at))
                    $fields[] = 'r.left_at = DATE({left_at})';
                $this->client->run('MATCH (u:User{ uuid: {u_uuid} })-[r:WORKING_AT]->(d:Department{ uuid: {d_uuid} }) SET ' . implode(', ', $fields), [
                    'u_uuid' => $uuid,
                    'd_uuid' => $department_uuid,
                    'position' => $position,
                    'took_at' => $took_at,
                    'left_at' => $left_at,
                ]);
            }
        }

        return $user->values();
    }

    public function delete(Request $request, $uuid)
    {
        if ($request->boolean('permanent')) {
        } else {
        }
    }
}
