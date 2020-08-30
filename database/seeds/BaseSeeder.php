<?php

use GraphAware\Neo4j\Client\ClientBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Ramsey\Uuid\Uuid;

class BaseSeeder extends Seeder
{
    /**
     * @var HTTP download context for general image
     */
    protected $imageContext;

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
                'verify_peer' => false,
                'verify_peer_name' => false,
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

    protected function downloadImage($url, $context, $module, $id, $subdir)
    {
        try {
            $contents = file_get_contents($url, false, $context);
        } catch (Exception $e) {
            return false;
        }

        $core = imagecreatefromstring($contents);
        $originalWidth = imagesx($core);
        $originalHeight = imagesy($core);

        // get the content type
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        if (empty($subdir))
            $path = "$module/$id";
        else
            $path = "$module/$id/$subdir";
        Storage::disk('local')->makeDirectory($path);

        if ($originalWidth > 600) {
            $newWidth = 600;
            $ratio = $newWidth / $originalWidth;
            $newHeight = ceil($originalHeight * $ratio);

            $canvas = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresized($canvas, $core, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        }
        imagedestroy($core);

        // define core
        switch (strtolower($contentType)) {
            case 'image/png':
            case 'image/x-png':
                $path .= '/' . uniqid() . '.png';
                if ($originalWidth > 600) {
                    $result = @imagepng($canvas, storage_path("app/$path"));
                    imagedestroy($canvas);
                } else {
                    $result = Storage::disk('local')->put($path, $contents);
                }
                break;

            case 'image/jpg':
            case 'image/jpeg':
            case 'image/pjpeg':
                $path .= '/' . uniqid() . '.jpg';
                if ($originalWidth > 600) {
                    $result = @imagejpeg($canvas, storage_path("app/$path"));
                    imagedestroy($canvas);
                } else {
                    $result = Storage::disk('local')->put($path, $contents);
                }
                break;

            case 'image/gif':
                $path .= '/' . uniqid() . '.gif';
                if ($originalWidth > 600) {
                    $result = @imagegif($canvas, storage_path("app/$path"));
                    imagedestroy($canvas);
                } else {
                    $result = Storage::disk('local')->put($path, $contents);
                }
                break;

            case 'image/webp':
            case 'image/x-webp':
                if (!function_exists('imagewebp')) {
                    throw new NotSupportedException(
                        "Unsupported image type. GD/PHP installation does not support WebP format."
                    );
                }
                $path .= '/' . uniqid() . '.webp';
                if ($originalWidth > 600) {
                    $result = @imagewebp($canvas, storage_path("app/$path"));
                    imagedestroy($canvas);
                } else {
                    $result = Storage::disk('local')->put($path, $contents);
                }
                break;

            default:
                throw new NotSupportedException(
                    "Unsupported image type. GD driver is only able to decode JPG, PNG, GIF or WebP files."
                );
        }

        if (empty($result)) {
            throw new FileIOException(
                "Unable to write image into file ({$filePath})."
            );
        }

        return $path;
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
}
