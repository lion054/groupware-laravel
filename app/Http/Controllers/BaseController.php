<?php

namespace App\Http\Controllers;

use GraphAware\Neo4j\Client\ClientBuilder;
use Laravel\Lumen\Routing\Controller;
use Lcobucci\JWT\Parser;
use Ramsey\Uuid\Uuid;

abstract class BaseController extends Controller
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

    protected function getCurrentUser($request)
    {
        $header = $request->header('Authorization');
        if (empty($header))
            return false;

        $parser = new Parser();
        $token = $parser->parse($header);

        $data = new ValidationData();
        $data->setIssuer(config('jwt.iss'));
        $data->setAudience(config('jwt.aud'));

        if (!$token->validate($data))
            return false;

        if (!$token->isExpired())
            return false;

        $query = [
            'MATCH (u:User{ uuid: {uuid} })',
            'RETURN u',
        ];
        $result = $this->client->run(implode(' ', $query), [
            'uuid' => $token->getClaim('sub'),
        ]);

        if ($result->size() == 0)
            return false;

        return $result->getRecord()->get('u');
    }

    /**
     * Validate the given request with the given rules.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return array
     *
     * @throws ValidationException
     */
    protected function validateWithHook(Request $request, array $rules, array $messages = [], array $customAttributes = [], $callback = null)
    {
        $validator = $this->getValidationFactory()->make($request->all(), $rules, $messages, $customAttributes);

        if ($callback)
            $validator->after($callback);

        if ($validator->fails()) {
            $this->throwValidationException($request, $validator);
        }

        return $this->extractInputFromRules($request, $rules);
    }

    protected function checkUnique($label, $field, $value, $excludingUuid = false)
    {
        $query = ["MATCH (n:$label{ $field: {value} })"];
        if ($excludingUuid)
            $query[] = 'WHERE n.uuid <> {uuid}';
        $query[] = 'RETURN COUNT(*)';
        $result = $this->client->run(implode(' ', $query), [
            'value' => $value,
            'uuid' => $excludingUuid,
        ]);
        return $result->size() == 0;
    }

    protected function createNode($label, $data)
    {
        $info = [];
        foreach ($data as $key => $value)
            $info[$key] = $value;
        $info['uuid'] = Uuid::uuid4();
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
        $info['uuid'] = Uuid::uuid4();
        $record = $this->client->run(implode(' ', $query), [
            'from_uuid' => $fromUuid,
            'to_uuid' => $toUuid,
            'info' => $info,
        ])->getRecord();
        return $record->get('r');
    }

    protected function getNode($params)
    {
        if (empty($params))
            return false;
        if (!is_array($params))
            return false;
        $data = [];
        foreach (array_keys($params) as $key)
            $data[] = $key . ': {' . $key . '}';
        $query = [
            'MATCH (n{ ' . implode(', ', $data) . ' })',
            'RETURN n',
        ];
        $result = $this->client->run(implode(' ', $query), $params);
        if ($result->size() == 0)
            return false;
        return $result->getRecord()->get('n');
    }

    protected function getRelation($params)
    {
        if (empty($params))
            return false;
        if (!is_array($params))
            return false;
        $data = [];
        foreach (array_keys($params) as $key)
            $data[] = $key . ': {' . $key . '}';
        $query = [
            'MATCH ()-[r{ ' . implode(', ', $data) . ' }]-()',
            'RETURN r',
        ];
        $result = $this->client->run(implode(' ', $query), $params);
        if ($result->size() == 0)
            return false;
        return $result->getRecord()->get('r');
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
            'MATCH p=({ uuid: {uuid} })' . $left . "[:$type*]" . $right . '(n)',
            'WHERE NOT (n)' . $left . "[:$type]" . $right . '()', // "n" must be top-level
            'RETURN NODES(p)', // "p" means path
        ];
        $result = $this->client->run(implode(' ', $query), [
            'uuid' => $uuid,
        ]);
        if ($result->size() == 0)
            return false;
        $nodes = $result->getRecord()->get('NODES(p)');
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
                $validData[] = "n.$key = {" . $key . '}';
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
        $result = $this->client->run(implode(' ', $query), $info);
        if ($result->size() == 0)
            return false;
        return $result->getRecord()->get('n');
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
        $result = $this->client->run(implode(' ', $query), $info);
        if ($result->size() == 0)
            return false;
        return $result->getRecord()->get('r');
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
