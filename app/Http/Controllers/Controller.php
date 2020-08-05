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

    private function makeUuidForRelation($fromUuid, $toUuid, $type)
    {
        $query = [
            "MATCH (from{ uuid: {from_uuid} })-[r:$type]->(to{ uuid: {to_uuid} })",
            'RETURN r',
        ];
        $records = $this->client->run(implode(' ', $query), [
            'from_uuid' => $fromUuid,
            'to_uuid' => $toUuid,
        ])->getRecords();
        $uuids = [];
        foreach ($records as $record) {
            $relation = $record->get('r');
            $uuids[] = $relation->value('uuid');
        }
        while (TRUE) {
            $uuid = uniqid();
            if (!in_array($uuid, $uuids))
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
        foreach (array_keys($data) as $key)
            $info[$key] = $data[$key];
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

    protected function createRelation($fromUuid, $toUuid, $type, $data = NULL)
    {
        $query = [
            'MATCH (from{ uuid: {from_uuid} }),(to{ uuid: {to_uuid} })',
            "CREATE (from)-[r:$type]->(to)",
            'SET r += {info}',
        ];
        $info = [];
        if ($data) {
            foreach (array_keys($data) as $key)
                $info[$key] = $data[$key];
        }
        $info['uuid'] = $this->makeUuidForRelation($fromUuid, $toUuid, $type);
        $this->client->run(implode(' ', $query), [
            'from_uuid' => $fromUuid,
            'to_uuid' => $toUuid,
            'info' => $info,
        ]);
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

    protected function getRelations($fromUuid, $toUuid, $type)
    {
        $query = [
            "MATCH (from{ uuid: {from_uuid} })-[r:$type]->(to{ uuid: {to_uuid} })",
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
        $fields = [];
        $info = [];
        foreach (array_keys($data) as $key) {
            $fields[] = "$key = {$key}";
            $info[$key] = $data[$key];
        }
        $info['uuid'] = $uuid;
        $query = [
            'MATCH (n{ uuid: {uuid} })',
            'SET ' . implode(', ', $fields),
            'RETURN n',
        ];
        $record = $this->client->run(implode(' ', $query), $info)->getRecord();
        return $record->get('n');
    }

    protected function updateRelation($fromUuid, $toUuid, $type, $uuid, $data)
    {
        $fields = [];
        $info = [];
        foreach (array_keys($data) as $key) {
            $fields[] = "r.$key = {$key}";
            $info[$key] = $data[$key];
        }
        $info['from_uuid'] = $fromUuid;
        $info['to_uuid'] = $toUuid;
        $info['uuid'] = $uuid;
        $query = [
            "MATCH (from{ uuid: {from_uuid} })-[r:$type{ uuid: {uuid} }]->(to{ uuid: {to_uuid} })",
            'SET ' . implode(', ', $fields),
            'RETURN r',
        ];
        $record = $this->client->run(implode(' ', $query), $info)->getRecord();
        return $record->get('r');
    }
}
