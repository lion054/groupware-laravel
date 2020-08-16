<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BelongToController extends Controller
{
    public function show($uuid)
    {
        return $this->getRelation($uuid);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department_uuid' => 'required',
            'parent_uuid' => 'required',
        ]);
        $validator->setAttributeNames([
            'department_uuid' => 'Department UUID',
            'parent_uuid' => 'Parent UUID',
        ]);
        $validator->after(function ($validator) use ($request) {
            if (!$validator->errors()->has('department_uuid')) {
                $department = $this->getNode($request->input('department_uuid'));
                if ($department->label() != 'Department')
                    $validator->errors()->add('department_uuid', 'The label from Department UUID must be Department');
            }
            if (!$validator->errors()->has('parent_uuid')) {
                $parent = $this->getNode($request->input('parent_uuid'));
                if ($parent->label() != 'Department' && $parent->label() != 'Company')
                    $validator->errors()->add('parent_uuid', 'The label from Parent UUID must be Department or Company');
            }
        });
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }

        $department_uuid = $request->input('department_uuid');
        $parent_uuid = $request->input('parent_uuid');
        $nodes = $this->getPathOfNode($parent_uuid, 'BELONG_TO');
        foreach ($nodes as $node) {
            if ($node['values']['uuid'] == $department_uuid) {
                return [
                    'success' => FALSE,
                    'error' => 'Recursive relation among graph is not allowed',
                ];
            }
        }

        $relation = $this->createRelation($department_uuid, $parent_uuid, 'BELONG_TO');
        return $relation->values();
    }

    public function destroy($uuid)
    {
        $this->deleteNode($uuid);
        return 204;
    }
}
