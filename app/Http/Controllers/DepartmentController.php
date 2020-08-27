<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DepartmentController extends BaseController
{
    public function index(Request $request)
    {
        $company_uuid = $request->query('company_uuid');
        $search = $request->query('search');
        $sort_by = $request->query('sort_by');
        $limit = $request->query('limit');

        $query = ['MATCH (d:Department)'];
        if (!empty($search))
            $query[] = 'WHERE d.name CONTAINS {search}';
        $query[] = 'RETURN d';
        if (!empty($limit))
            $query[] = 'LIMIT {limit}';

        $records = $this->client->run(implode(' ', $query), [
            'search' => $search,
            'limit' => intval($limit),
        ])->getRecords();
        $result = [];
        foreach ($records as $record) {
            $department = $record->get('d');
            $result[] = $department->values();
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
        $result = $node->values();
        $result['path'] = $this->getPathOfNode($uuid, 'BELONG_TO');
        return $result;
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'capacity' => 'numeric',
        ]);

        $data = $request->only(['name', 'capacity']);
        $node = $this->createNode('Department', $data);
        return $node->values();
    }

    public function update(Request $request, $uuid)
    {
        $this->validate($request, [
            'capacity' => 'numeric',
        ]);

        $data = $request->only(['name', 'capacity']);
        if (empty($data))
            $node = $this->getNode($uuid);
        else
            $node = $this->updaetNode($uuid, $data);
        return $node->values();
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

    public function showUsers($uuid)
    {
        $query = [
            'MATCH (d:Department{ uuid: {uuid} })<-[r:WORK_AT]-(u:User)',
            'RETURN u',
        ];
        $records = $this->client->run(implode(' ', $query), [
            'uuid' => $uuid,
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
}
