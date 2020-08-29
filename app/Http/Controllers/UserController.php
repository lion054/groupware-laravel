<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends BaseController
{
    public function index(Request $request)
    {
        $company_uuid = $request->query('company_uuid');
        $department_uuid = $request->query('department_uuid');
        $career_visible = $request->boolean('career_visible');
        $search = $request->query('search');
        $sort_by = $request->query('sort_by');
        $limit = $request->query('limit');

        $query = ['MATCH (u:User)'];
        if (!empty($search))
            $query[] = 'WHERE u.name CONTAINS {search}';
        if ($career_visible) {
            $query[] = 'OPTIONAL MATCH (u)-[w:WORK_AT]->(d:Department)';
            $query[] = 'WITH u, w.role AS role, d';
            $query[] = 'OPTIONAL MATCH p=(d)-[:BELONG_TO*]->(c:Company)';
            $query[] = 'WHERE NOT (c)-[:BELONG_TO]->()'; // "n" must be top-level
        }
        $fields = ['u'];
        if ($career_visible)
            $fields[] = 'COLLECT({ role: role, hierarchy: NODES(p) }) AS career'; // "p" means path
        $query[] = 'RETURN ' . implode(', ', $fields);
        if (!empty($limit))
            $query[] = 'LIMIT {limit}';

        $records = app('neo4j')->run(implode(' ', $query), [
            'search' => $search,
            'limit' => intval($limit),
        ])->getRecords();
        $result = [];

        foreach ($records as $record) {
            $user = $record->get('u')->values();
            if (isset($user['password']))
                unset($user['password']);
            if ($record->hasValue('career')) {
                $user['career'] = [];
                foreach ($record->get('career') as $item) {
                    if (!$item['hierarchy'])
                        continue;
                    $department = [];
                    $company = [];
                    foreach ($item['hierarchy'] as $node) {
                        $values = $node->values();
                        if ($node->hasLabel('Department')) {
                            if (empty($department))
                                $department = $values;
                            else {
                                $values['department'] = $department;
                                $department = $values;
                            }
                        } else if ($node->hasLabel('Company')) {
                            if (empty($company))
                                $company = $values;
                            else {
                                $values['company'] = $company;
                                $company = $values;
                            }
                        }
                    }
                    $user['career'][] = [
                        'role' => $item['role'],
                        'department' => $department,
                        'company' => $company,
                    ];
                }
            }
            $result[] = $user;
        }
        return $result;
    }

    public function show($uuid)
    {
        $node = $this->getNode($uuid);
        if (!$node) {
            return [
                'success' => FALSE,
                'error' => 'Not found that node',
            ];
        }
        $data = $node->values();
        if (isset($data['password']))
            unset($data['password']);
        return $data;
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6',
        ], [], function ($validator) use ($request) {
            if (!$validator->errors()->has('email')) {
                if (!$this->checkUnique('User', 'email', $request->input('email')))
                    $validator->errors()->add('email', 'This email was registered already');
            }
        });

        $data = $request->only(['name', 'email', 'password']);
        $data['password'] = Hash::make($data['password']);
        $node = $this->createNode('User', $data);
        return $node->values();
    }

    public function update(Request $request, $uuid)
    {
        $this->validate($request, [
            'email' => 'email',
            'password' => 'min:6',
        ], [], function ($validator) use ($request, $uuid) {
            if (!$validator->errors()->has('email')) {
                if (!$this->checkUnique('User', 'email', $request->input('email'), $uuid))
                    $validator->errors()->add('email', 'This email was registered already');
            }
        });

        $data = $request->only(['name', 'email', 'password']);
        if (empty($data))
            $node = $this->getNode($uuid);
        else
            $node = $this->updaetNode($uuid, $data);
        $data = $node->values();
        if (isset($data['password']))
            unset($data['password']);
        return $data;
    }

    public function destroy(CustomRequest $request, $uuid)
    {
        $this->validate($request, [
            'forever' => 'boolean',
        ]);

        if ($request->boolean('forever'))
            $this->deleteNode($uuid);
        else {
            $this->updaetNode($uuid, [
                'deleted_at' => date(DateTimeInterface::RFC3339_EXTENDED),
            ]);
        }

        return 204;
    }

    public function restore($uuid)
    {
        $node = $this->updaetNode($uuid, [
            'deleted_at' => null,
        ]);
        return $node->values();
    }
}
