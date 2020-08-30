<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompanyController extends BaseController
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $sort_by = $request->query('sort_by');
        $limit = $request->query('limit');

        $query = ['MATCH (c:Company)'];
        if (!empty($search))
            $query[] = 'WHERE c.name CONTAINS {search}';
        $query[] = 'RETURN c';
        if (!empty($limit))
            $query[] = 'LIMIT {limit}';

        $records = app('neo4j')->run(implode(' ', $query), [
            'search' => $search,
            'limit' => intval($limit),
        ])->getRecords();
        $result = [];
        foreach ($records as $record) {
            $company = $record->get('c');
            $result[] = $company->values();
        }
        return $result;
    }

    public function show($uuid)
    {
        $node = $this->getNode($uuid);
        if (!$node) {
            return [
                'success' => false,
                'error' => 'Not found that node',
            ];
        }
        return $node->values();
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'since' => 'date',
        ]);

        $data = $request->only(['name', 'since']);
        $node = $this->createNode('Company', $data);
        return $node->values();
    }

    public function update(Request $request, $uuid)
    {
        $this->validate($request, [
            'since' => 'date',
        ]);

        $data = $request->only(['name', 'since']);
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

    public function showDepartments($uuid)
    {
        return $this->getTreeOfNode($uuid, 'BELONG_TO');
    }

    public function showUsers($uuid)
    {
        // Get the departments of this company as list
        $query = [
            'MATCH (:Company{ uuid: {uuid} })<-[:BELONG_TO*]-(d:Department)',
            'RETURN DISTINCT d',
        ];
        $records = app('neo4j')->run(implode(' ', $query), [
            'uuid' => $uuid,
        ])->getRecords();
        $uuids = [];
        foreach ($records as $record) {
            $node = $record->get('d');
            $uuid = $node->value('uuid');
            $uuids[] = "'$uuid'";
        }

        // Get the users that works at these departments
        $query = [
            'MATCH (u:User)-[:WORK_AT]->(d:Department)',
            'WHERE d.uuid IN [' . implode(', ', $uuids) . ']',
            'RETURN u',
        ];
        $records = app('neo4j')->run(implode(' ', $query))->getRecords();
        $result = [];
        foreach ($records as $record) {
            $node = $record->get('u');
            $data = $node->values();
            if (isset($data['password']))
                unset($data['password']);
            $result[] = $data;
        }
        return $result;
    }
}
