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

    protected function createRelation($fromUuid, $toUuid, $type, $data = NULL)
    {
        $query = [
            'MATCH (from{ uuid: {from_uuid} }),(to{ uuid: {to_uuid} })',
            "CREATE (from)-[r:$type]->(to)",
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
        return $record->get('c');
    }
}
