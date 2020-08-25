<?php

use GraphAware\Neo4j\Client\ClientBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class BaseSeeder extends Seeder
{
    /**
     * @var HTTP download context for general image
     */
    private $imageContext;

    /**
     * @var HTTP download context for face image
     */
    private $faceContext;

    /**
     * @var Neo4j PHP Client
     */
    protected $client = null;

    /**
     * Initialize the context and client
     */
    public function __construct()
    {
        ini_set('memory_limit', '-1'); // Allow to process big image

        $this->imageContext = stream_context_create([
            'ssl' => [
                'verify_peer' => FALSE,
                'verify_peer_name' => FALSE,
            ]
        ]);

        $headers = [
            'authority: thispersondoesnotexist.com',
            'pragma: no-cache',
            'cache-control: no-cache',
            'upgrade-insecure-requests: 1',
            'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.80 Safari/537.36',
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
            'referer: https://thispersondoesnotexist.com/',
            'accept-encoding: gzip, deflate, br',
            'accept-language: en-US,en;q=0.9',
        ];
        $this->faceContext = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n ", $headers) . "\r\n",
            ]
        ]);

        $host = config('database.connections.neo4j.host');
        $port = config('database.connections.neo4j.port');
        $username = config('database.connections.neo4j.username');
        $password = config('database.connections.neo4j.password');

        $this->client = ClientBuilder::create()
            ->addConnection('default', "http://$username:$password@$host:$port")
            ->build();
    }

    protected function downloadImage($url, $module, $id, $subdir)
    {
        try {
            $contents = file_get_contents($url, FALSE, $this->imageContext);
        } catch (Exception $e) {
            return FALSE;
        }

        if (empty($subdir))
            $path = "$module/$id";
        else
            $path = "$module/$id/$subdir";
        Storage::disk('local')->makeDirectory($path);
        $path .= '/' . uniqid() . '.jpg';

        $image = Image::make($contents);
        if ($image->width() > 600) {
            $image->resize(600, null, function ($constraint) {
                $constraint->aspectRatio();
            });
        }
        $image->save(storage_path("app/$path"), 100);

        return $path;
    }

    protected function downloadFace($url, $id)
    {
        try {
            $contents = file_get_contents($url, FALSE, $this->faceContext);
        } catch (Exception $e) {
            return FALSE;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);

        $path = "users/$id";
        Storage::disk('local')->makeDirectory($path);
        $path .= '/' . uniqid() . '.jpg';

        $image = Image::make($contents);
        if ($image->width() > 600) {
            $image->resize(600, null, function ($constraint) {
                $constraint->aspectRatio();
            });
        }
        $image->save(storage_path("app/$path"), 100);

        return $path;
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
        $record = $this->client->run(implode(' ', $query), $info)->getRecord();
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
}
