<?php

namespace App\Http\Controllers;

use GraphAware\Neo4j\Client\ClientBuilder;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @var Neo4j PHP Client
     */
    protected $client = null;

    /**
     * Initialize the client
     */
    public function __construct()
    {
        $host = config('database.connections.neo4j.host');
        $port = config('database.connections.neo4j.port');
        $username = config('database.connections.neo4j.username');
        $password = config('database.connections.neo4j.password');

        $this->client = ClientBuilder::create()
            ->addConnection('default', "http://$username:$password@$host:$port")
            ->build();
    }

    private function makeUuidForNode()
    {
        while (TRUE) {
            $uuid = uniqid();
            $query = [
                'MATCH (n{ uuid: {uuid} })',
                'RETURN COUNT(*)',
            ];
            $record = $this->client->run(implode(' ', $query), [
                'uuid' => $uuid,
            ])->getRecord();
            if ($record->values()[0] == 0)
                return $uuid;
        }
    }

    private function makeUuidForRelation()
    {
        while (TRUE) {
            $uuid = uniqid();
            $query = [
                'MATCH ()-[r{ uuid: {uuid} }]->()',
                'RETURN COUNT(r)',
            ];
            $record = $this->client->run(implode(' ', $query), [
                'uuid' => $uuid,
            ])->getRecord();
            if ($record->values()[0] == 0)
                return $uuid;
        }
    }

    protected function checkUnique($label, $field, $value, $excludingUuid = FALSE)
    {
        $query = ["MATCH (n:$label{ $field: {value} })"];
        if ($excludingUuid)
            $query[] = 'WHERE n.uuid <> {uuid}';
        $query[] = 'RETURN COUNT(*)';
        $record = $this->client->run(implode(' ', $query), [
            'value' => $value,
            'uuid' => $excludingUuid,
        ])->getRecord();
        return $record->values()[0] == 0;
    }

    protected function createNode($label, $data)
    {
        $info = [];
        foreach ($data as $key => $value)
            $info[$key] = $value;
        $info['uuid'] = $this->makeUuidForNode();
        $query = [
            "CREATE (n:$label)",
            'SET n += {info}',
            'RETURN n',
        ];
        $record = $this->client->run(implode(' ', $query), [
            'info' => $info
        ])->getRecord();
        return $record->get('n');
    }

    protected function createRelation($fromUuid, $toUuid, $type, $data = NULL, $direction = 'OUTGOING')
    {
        $left = '-';
        $right = '->';
        switch ($direction) {
            case 'INCOMING':
                $left = '<-';
                $right = '-';
            break;
            case 'OUTGOING':
                $left = '-';
                $right = '->';
            break;
            case 'BOTH':
                $left = '-';
                $right = '-';
            break;
        }
        $query = [
            'MATCH (from{ uuid: {from_uuid} }),(to{ uuid: {to_uuid} })',
            'CREATE (from)' . $left . "[r:$type]" . $right . '(to)',
            'SET r += {info}',
            'RETURN r',
        ];
        $info = [];
        if ($data) {
            foreach ($data as $key => $value)
                $info[$key] = $value;
        }
        $info['uuid'] = $this->makeUuidForRelation();
        $record = $this->client->run(implode(' ', $query), [
            'from_uuid' => $fromUuid,
            'to_uuid' => $toUuid,
            'info' => $info,
        ])->getRecord();
        return $record->get('r');
    }

    protected function getNode($uuid)
    {
        $query = [
            'MATCH (n{ uuid: {uuid} })',
            'RETURN n',
        ];
        $record = $this->client->run(implode(' ', $query), [
            'uuid' => $uuid,
        ])->getRecord();
        return $record->get('n');
    }

    protected function getRelation($uuid)
    {
        $query = [
            'MATCH ()-[r{ uuid: {uuid} }]-()',
            'RETURN r',
        ];
        $record = $this->client->run(implode(' ', $query), [
            'uuid' => $uuid,
        ])->getRecord();
        return $record->get('r');
    }

    protected function getPathOfNode($uuid, $type, $direction = 'OUTGOING')
    {
        $left = '-';
        $right = '->';
        switch ($direction) {
            case 'INCOMING':
                $left = '<-';
                $right = '-';
            break;
            case 'OUTGOING':
                $left = '-';
                $right = '->';
            break;
            case 'BOTH':
                $left = '-';
                $right = '-';
            break;
        }
        $query = [
            'MATCH path=({ uuid: {uuid} })' . $left . "[:$type*]" . $right . '(n)',
            'WHERE NOT (n)' . $left . "[:$type]" . $right . '()', // "n" must be top-level
            'RETURN NODES(path)',
        ];
        $record = $this->client->run(implode(' ', $query), [
            'uuid' => $uuid,
        ])->getRecord();
        $nodes = $record->get('NODES(path)');
        $result = [];
        foreach ($nodes as $node) {
            $result[] = [
                'labels' => $node->labels(),
                'values' => $node->values(),
            ];
        }
        return $result;
    }

    protected function getTreeOfNode($uuid, $type, $direction = 'INCOMING')
    {
        $left = '-';
        $right = '->';
        switch ($direction) {
            case 'INCOMING':
                $left = '<-';
                $right = '-';
            break;
            case 'OUTGOING':
                $left = '-';
                $right = '->';
            break;
            case 'BOTH':
                $left = '-';
                $right = '-';
            break;
        }
        $query = [
            'MATCH ({ uuid: {uuid} })' . $left . "[:$type]" . $right . '(n)',
            'RETURN n',
        ];
        $records = $this->client->run(implode(' ', $query), [
            'uuid' => $uuid,
        ])->getRecords();
        if (empty($records))
            return [];
        $result = [];
        foreach ($records as $record) {
            $node = $record->get('n');
            $item = [
                'labels' => $node->labels(),
                'values' => $node->values(),
            ];
            $subitems = $this->getTreeOfNode($item['values']['uuid'], $type);
            if (!empty($subitems))
                $item['children'] = $subitems;
            $result[] = $item;
        }
        return $result;
    }

    protected function getRelations($fromUuid, $toUuid, $type, $direction = 'OUTGOING')
    {
        $left = '-';
        $right = '->';
        switch ($direction) {
            case 'INCOMING':
                $left = '<-';
                $right = '-';
            break;
            case 'OUTGOING':
                $left = '-';
                $right = '->';
            break;
            case 'BOTH':
                $left = '-';
                $right = '-';
            break;
        }
        $query = [
            'MATCH (from{ uuid: {from_uuid} })' . $left . "[r:$type]" . $right . '(to{ uuid: {to_uuid} })',
            'RETURN r',
        ];
        $records = $this->client->run(implode(' ', $query), [
            'from_uuid' => $fromUuid,
            'to_uuid' => $toUuid,
        ])->getRecords();
        $result = [];
        foreach ($records as $record)
            $result[] = $record->get('r');
        return $result;
    }

    protected function updateNode($uuid, $data)
    {
        $validData = [];
        $invalidKeys = [];
        $info = [];
        foreach ($data as $key => $value) {
            if (empty($value))
                $invalidKeys[] = "n.$key";
            else {
                $validData[] = "n.$key = {$key}";
                $info[$key] = $value;
            }
        }
        $info['uuid'] = $uuid;
        $query = [
            'MATCH (n{ uuid: {uuid} })',
            'SET ' . implode(', ', $validData),
        ];
        if (!empty($invalidKeys))
            $query[] = 'REMOVE ' . implode(', ', $invalidKeys);
        $query[] = 'RETURN n';
        $record = $this->client->run(implode(' ', $query), $info)->getRecord();
        return $record->get('n');
    }

    protected function updateRelation($uuid, $data)
    {
        $validData = [];
        $invalidKeys = [];
        $info = [];
        foreach ($data as $key => $value) {
            if (empty($value))
                $invalidKeys[] = "r.$key";
            else {
                $validData[] = "r.$key = {$key}";
                $info[$key] = $value;
            }
        }
        $info['uuid'] = $uuid;
        $query = [
            'MATCH ()-[r{ uuid: {uuid} }]-()',
            'SET ' . implode(', ', $validData),
        ];
        if (!empty($emptyData))
            $query[] = 'REMOVE ' . implode(', ', $invalidKeys);
        $query[] = 'RETURN r';
        $record = $this->client->run(implode(' ', $query), $info)->getRecord();
        return $record->get('r');
    }

    protected function deleteNode($uuid)
    {
        $query = [
            'MATCH (n{ uuid: {uuid} })',
            'DETACH DELETE n',
        ];
        $this->client->run(implode(' ', $query), [
            'uuid' => $uuid,
        ]);
    }

    protected function deleteRelation($uuid)
    {
        $query = [
            'MATCH ()-[r{ uuid: {uuid} }]-()',
            'DELETE r',
        ];
        $this->client->run(implode(' ', $query), [
            'uuid' => $uuid,
        ]);
    }
}
