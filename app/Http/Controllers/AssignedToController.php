<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AssignedToController extends Controller
{
    public function show($uuid)
    {
        return $this->getRelation($uuid);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department_uuid' => 'required',
            'company_uuid' => 'required',
        ]);
        $validator->setAttributeNames([
            'department_uuid' => 'Department UUID',
            'company_uuid' => 'Company UUID',
        ]);
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }

        $relation = $this->createRelation($request->input('user_uuid'), $request->input('department_uuid'), 'ASSIGNED_TO');
        return $relation->values();
    }

    public function destroy($uuid)
    {
        $this->deleteNode($uuid);
        return 204;
    }
}
