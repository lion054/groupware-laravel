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
            'relation.role' => 'required_with:related_to',
            'relation.took_at' => 'date',
            'relation.left_at' => 'date',
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

        $related_to = $request->input('related_to');
        if (!empty($related_to)) {
            $this->createRelation($user->value('uuid'), $related_to, 'WORKS_AT', [
                'role' => $request->input('relation.role'),
                'took_at' => $request->input('relation.took_at'),
                'left_at' => $request->input('relation.left_at'),
            ]);
        }

        return $user->values();
    }

    public function update(Request $request, $uuid)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'email',
            'password' => 'min:6',
            'role' => 'required_with:related_to',
            'took_at' => 'date',
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

        $data = [];
        if ($request->has('name'))
            $data['name'] = $request->input('name');
        if ($request->has('email'))
            $data['email'] = $request->input('email');
        if ($request->has('password'))
            $data['password'] = $request->input('password');
        if (empty($data))
            $user = $this->getNode($uuid);
        else
            $user = $this->updaetNode($uuid, $data);

        $related_to = $request->input('related_to');
        if (!empty($related_to)) {
            $record = $this->client->run('MATCH (u:User{ uuid: {uuid} })-->(d:Department) RETURN d', [
                'uuid' => $uuid,
            ])->getRecord();
            $newRelation = TRUE;
            if ($record) {
                $department = $record->get('d');
                if ($related_to == $department->value('uuid')) {
                    $this->client->run('MATCH (u:User{ uuid: {uuid} })-[r:WORKS_AT]->() DELETE r', [
                        'uuid' => $uuid,
                    ]);
                    $newRelation = FALSE;
                }
            }
            $role = $request->input('role');
            $took_at = $request->input('took_at');
            $left_at = $request->input('left_at');
            if ($newRelation) {
                $query = [
                    'MATCH (u:User),(d:Department)',
                    'WHERE u.uuid = {u_uuid} AND d.uuid = {d_uuid}',
                    'CREATE (u)-[r:WORKS_AT{',
                        'role: {role},',
                        'took_at: DATE({took_at}),',
                        'left_at: DATE({left_at})',
                    '}]->(d)',
                ];
                $this->client->run(implode(' ', $query), [
                    'u_uuid' => $uuid,
                    'd_uuid' => $related_to,
                    'role' => $role,
                    'took_at' => $took_at,
                    'left_at' => $left_at,
                ]);
            } else {
                $fields = ['r.role = {role}'];
                if (!empty($took_at))
                    $fields[] = 'r.took_at = DATE({took_at})';
                if (!empty($left_at))
                    $fields[] = 'r.left_at = DATE({left_at})';
                $this->client->run('MATCH (u:User{ uuid: {u_uuid} })-[r:WORKS_AT]->(d:Department{ uuid: {d_uuid} }) SET ' . implode(', ', $fields), [
                    'u_uuid' => $uuid,
                    'd_uuid' => $related_to,
                    'role' => $role,
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
