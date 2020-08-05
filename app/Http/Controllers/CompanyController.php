<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Http\Requests\CustomRequest;

class CompanyController extends Controller
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

        $records = $this->client->run(implode(' ', $query), [
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
        return $this->getNode($uuid);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'since' => 'date',
        ]);
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }

        $data = $request->only(['name', 'since']);
        $node = $this->createNode('Company', $data);
        return $node->values();
    }

    public function update(Request $request, $uuid)
    {
        $validator = Validator::make($request->all(), [
            'since' => 'date',
        ]);
        if ($validator->fails()) {
            return [
                'success' => FALSE,
                'errors' => $validator->messages(),
            ];
        }

        $data = $request->only(['name', 'since']);
        if (empty($data))
            $node = $this->getNode($uuid);
        else
            $node = $this->updaetNode($uuid, $data);
        return $node->values();
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
