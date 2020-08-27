<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WorkAtController extends BaseController
{
    public function show($uuid)
    {
        return $this->getRelation($uuid);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'user_uuid' => 'required',
            'department_uuid' => 'required',
            'role' => 'required',
            'took_at' => 'date',
            'left_at' => 'date',
        ], [
            'user_uuid' => 'User UUID',
            'department_uuid' => 'Department UUID',
            'took_at' => 'Took At',
            'left_at' => 'Left At',
        ]);

        $data = $request->only(['role', 'took_at', 'left_at']);
        $relation = $this->createRelation($request->input('user_uuid'), $request->input('department_uuid'), 'WORK_AT', $data);
        return $relation->values();
    }

    public function update(Request $request, $uuid)
    {
        $this->validate($request, [
            'took_at' => 'date',
            'left_at' => 'date',
        ]);

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
