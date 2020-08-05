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

    protected function getUuidToCreate()
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
        $info['uuid'] = $this->getUuidToCreate();
        $record = $this->client->run('CREATE (n:' . $label . ') SET n += {info} RETURN n', [
            'info' => $info
        ])->getRecord();
        return $record->get('n');
    }

    protected function createRelation($oneUuid, $otherUuid, $type, $data = NULL)
    {
        $fields = [];
        if ($data) {
            foreach (array_keys($data) as $key)
                $fields[] = $key . ': {' . $key . '}';
        }
        $query = [
            'MATCH (one),(other)',
            'WHERE one.uuid = {one_uuid} AND other.uuid = {other_uuid}',
            'CREATE (one)-[r:' . $type . '{',
                implode(', ', $fields),
            '}]->(other)',
        ];
        $info = [];
        if ($data) {
            foreach (array_keys($data) as $key)
                $info[$key] = $data[$key];
        }
        $info['one_uuid'] = $oneUuid;
        $info['other_uuid'] = $otherUuid;
        $this->client->run(implode(' ', $query), $info);
    }
}
