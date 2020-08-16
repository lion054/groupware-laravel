<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WorkAtController extends Controller
{
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
