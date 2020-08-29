<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller;
use Lcobucci\JWT\Parser;
use Ramsey\Uuid\Uuid;

use App\Exceptions\FileIOException;
use App\Exceptions\NotSupportedException;

abstract class BaseController extends Controller
{
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
        $result = app('neo4j')->run(implode(' ', $query), [
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
        $result = app('neo4j')->run(implode(' ', $query), [
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
        $record = app('neo4j')->run(implode(' ', $query), [
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
        $record = app('neo4j')->run(implode(' ', $query), [
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
        $result = app('neo4j')->run(implode(' ', $query), $params);
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
        $result = app('neo4j')->run(implode(' ', $query), $params);
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
        $result = app('neo4j')->run(implode(' ', $query), [
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
        $records = app('neo4j')->run(implode(' ', $query), [
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
        $records = app('neo4j')->run(implode(' ', $query), [
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
        $result = app('neo4j')->run(implode(' ', $query), $info);
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
        $result = app('neo4j')->run(implode(' ', $query), $info);
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
        app('neo4j')->run(implode(' ', $query), [
            'uuid' => $uuid,
        ]);
    }

    protected function deleteRelation($uuid)
    {
        $query = [
            'MATCH ()-[r{ uuid: {uuid} }]-()',
            'DELETE r',
        ];
        app('neo4j')->run(implode(' ', $query), [
            'uuid' => $uuid,
        ]);
    }

    protected function getResizedImageContent($filePath, $mime, $width, $height)
    {
        list($originalWidth, $originalHeight) = getimagesize($filePath);
        if (empty($height)) {
            $ratio = $width / $originalWidth;
            $height = ceil($originalHeight * $ratio);
        }

        // define core
        switch (strtolower($mime)) {
            case 'image/png':
            case 'image/x-png':
                $core = @imagecreatefrompng($filePath);
                break;

            case 'image/jpg':
            case 'image/jpeg':
            case 'image/pjpeg':
                $core = @imagecreatefromjpeg($filePath);
                if (!$core) {
                    $core= @imagecreatefromstring(file_get_contents($filePath));
                }
                break;

            case 'image/gif':
                $core = @imagecreatefromgif($filePath);
                break;

            case 'image/webp':
            case 'image/x-webp':
                if (!function_exists('imagecreatefromwebp')) {
                    throw new NotSupportedException(
                        "Unsupported image type. GD/PHP installation does not support WebP format."
                    );
                }
                $core = @imagecreatefromwebp($filePath);
                break;

            default:
                throw new NotSupportedException(
                    "Unsupported image type. GD driver is only able to decode JPG, PNG, GIF or WebP files."
                );
        }

        if (empty($core)) {
            throw new FileIOException(
                "Unable to decode image from file ({$filePath})."
            );
        }

        // new canvas
        $canvas = imagecreatetruecolor($width, $height);

        // fill with transparent color
        imagealphablending($canvas, false);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        imagecolortransparent($canvas, $transparent);
        imagealphablending($canvas, true);

        // copy original
        imagecopyresampled($canvas, $core, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);
        imagedestroy($core);

        ob_start();
        switch (strtolower($mime)) {
            case 'image/png':
            case 'image/x-png':
                @imagepng($canvas);
                break;

            case 'image/jpg':
            case 'image/jpeg':
            case 'image/pjpeg':
                @imagejpeg($canvas);
                break;

            case 'image/gif':
                @imagegif($canvas);
                break;

            case 'image/webp':
            case 'image/x-webp':
                @imagewebp($canvas);
                break;
        }
        imagedestroy($canvas);
        $buffer = ob_get_contents();
        ob_end_clean();
        return $buffer;
    }
}
