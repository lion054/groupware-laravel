<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $company_uuid = $request->query('company_uuid');
        $department_uuid = $request->query('department_uuid');
        $job_visible = $request->boolean('job_visible');
        $search = $request->query('search');
        $sort_by = $request->query('sort_by');
        $limit = $request->query('limit');

        $query = [];
        $conditions = [];
        if ($job_visible) {
            $query[] = 'MATCH p=(u:User)-[r:WORK_AT]->(:Department)-[:BELONG_TO*]->(n)';
            $conditions = [
                'NOT EXISTS (r.left_at)',
                'NOT (n)-[:BELONG_TO]->()', // "n" must be top-level
            ];
        } else
            $query[] = 'MATCH p=(u:User)';
        if (!empty($search))
            $conditions[] = 'u.name CONTAINS {search}';
        if (!empty($conditions))
            $query[] = 'WHERE ' . implode(' AND ', $conditions);
        $query[] = 'RETURN NODES(p)'; // "p" means path
        if (!empty($limit))
            $query[] = 'LIMIT {limit}';

        $records = $this->client->run(implode(' ', $query), [
            'search' => $search,
            'limit' => intval($limit),
        ])->getRecords();
        $result = [];

        foreach ($records as $record) {
            $nodes = $record->get('NODES(p)');
            $user = [];
            $department = [];
            $company = [];
            foreach ($nodes as $node) {
                $labels = $node->labels();
                $values = $node->values();
                if (in_array('User', $labels)) {
                    if (isset($values['password']))
                        unset($values['password']);
                    $user = $values;
                } else if (in_array('Department', $labels)) {
                    if (empty($department))
                        $department = $values;
                    else {
                        $values['child'] = $department;
                        $department = $values;
                    }
                } else if (in_array('Company', $labels)) {
                    if (empty($company))
                        $company = $values;
                    else {
                        $values['child'] = $company;
                        $company = $values;
                    }
                }
            }
            if ($job_visible) {
                $user['department'] = $department;
                $user['company'] = $company;
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
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6',
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

        $data = $request->only(['name', 'email', 'password']);
        $data['password'] = Hash::make($data['password']);
        $node = $this->createNode('User', $data);
        return $node->values();
    }

    public function update(Request $request, $uuid)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'email',
            'password' => 'min:6',
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
        $validator = Validator::make($request->all(), [
            'permanent' => 'in:1,0,true,false,on,off,yes,no',
        ]);
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }

        if ($request->boolean('permanent'))
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
            'deleted_at' => NULL,
        ]);
        return $node->values();
    }
}
