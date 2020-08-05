<?php

use GraphAware\Neo4j\Client\ClientBuilder;
use Illuminate\Database\Seeder;

class NeoSeeder extends Seeder
{
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
            $record = $this->client->run('MATCH (n{ uuid: {uuid} }) RETURN COUNT(*)', [
                'uuid' => $uuid,
            ])->getRecord();
            if ($record->values()[0] == 0)
                return $uuid;
        }
    }

    private function makeUuidForRelation($fromUuid, $toUuid, $type)
    {
        $records = $this->client->run('MATCH (from{ uuid: {from_uuid} })-[r:' . $type . ']->(to{ uuid: {to_uuid} }) RETURN r', [
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
        $query = ['MATCH (n:' . $label . '{ ' . $field . ': {value} })'];
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
        $record = $this->client->run('CREATE (n:' . $label . ') SET n += {info} RETURN n', [
            'info' => $info
        ])->getRecord();
        return $record->get('n');
    }

    protected function createRelation($fromUuid, $toUuid, $type, $data = NULL)
    {
        $fields = [];
        if ($data) {
            foreach (array_keys($data) as $key)
                $fields[] = $key . ': {' . $key . '}';
        }
        $fields[] = 'uuid: {uuid}';
        $query = [
            'MATCH (from{ uuid: {from_uuid} }),(to{ uuid: {to_uuid} })',
            'CREATE (from)-[r:' . $type . '{',
                implode(', ', $fields),
            '}]->(to)',
        ];
        $info = [];
        if ($data) {
            foreach (array_keys($data) as $key)
                $info[$key] = $data[$key];
        }
        $info['from_uuid'] = $fromUuid;
        $info['to_uuid'] = $toUuid;
        $info['uuid'] = $this->makeUuidForRelation($fromUuid, $toUuid, $type);
        $this->client->run(implode(' ', $query), $info);
    }
}
