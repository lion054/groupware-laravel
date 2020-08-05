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

    protected function getUuidToCreate($label)
    {
        while (TRUE) {
            $uuid = uniqid();
            if ($this->checkUnique($label, 'uuid', $uuid))
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
}
