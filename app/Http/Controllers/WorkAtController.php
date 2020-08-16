<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WorkAtController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_uuid' => 'required',
        ]);
        $validator->setAttributeNames([
            'user_uuid' => 'User UUID',
        ]);
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }
        $user_uuid = $request->query('user_uuid');
        $search = $request->query('search');
        $sort_by = $request->query('sort_by');
        $limit = $request->query('limit');

        $query = [
            "MATCH (from:User{ uuid: {from_uuid} })-[r:WORK_AT]->(to:Department)",
            'RETURN r',
        ];
        $records = $this->client->run(implode(' ', $query), [
            'from_uuid' => $user_uuid,
        ])->getRecords();
        $result = [];
        foreach ($records as $record)
            $result[] = $record->get('r');
        return $result;
    }

    public function show($uuid)
    {
        return $this->getRelation($uuid);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_uuid' => 'required',
            'department_uuid' => 'required',
            'role' => 'required',
            'took_at' => 'date',
            'left_at' => 'date',
        ]);
        $validator->setAttributeNames([
            'user_uuid' => 'User UUID',
            'department_uuid' => 'Department UUID',
            'took_at' => 'Took At',
            'left_at' => 'Left At',
        ]);
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }

        $data = $request->only(['role', 'took_at', 'left_at']);
        $relation = $this->createRelation($request->input('user_uuid'), $request->input('department_uuid'), 'WORK_AT', $data);
        return $relation->values();
    }

    public function update(Request $request, $uuid)
    {
        $validator = Validator::make($request->all(), [
            'took_at' => 'date',
            'left_at' => 'date',
        ]);
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }

        $data = $request->only(['role', 'took_at', 'left_at']);
        if (empty($data))
            $node = $this->getRelation($uuid);
        else
            $node = $this->updaetRelation($uuid, $data);
        return $node->values();
    }

    public function destroy($uuid)
    {
        $this->deleteNode($uuid);
        return 204;
    }
}
