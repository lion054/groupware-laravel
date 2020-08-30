<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

use App\Exceptions\FileIOException;
use App\Exceptions\NotSupportedException;

class MediaController extends BaseController
{
    public function __construct()
    {
        ini_set('memory_limit', '-1'); // Allow to process big image
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'image' => 'required|image',
        ]);

        $image = $request->file('image');
        $uuid = $request->input('uuid');
        if (empty($uuid))
            $name = $image->getClientOriginalName();
        else
            $name = uniqid() . '.' . $image->getClientOriginalExtension();
        Storage::disk('local')->putFileAs('temp', $image, $name);

        return ['path' => "temp/$name"];
    }

    public function show(Request $request, $key)
    {
        // imagecreatefromjpg could fail on too large image.
        // Check whether memory_limit=-1 in php.ini

        $path = base64_decode($key);
        $filePath = storage_path("app/$path");

        // Perform the image scaling
        $width = $request->query('width');
        $height = $request->query('height');
        if (empty($width))
            $width = 64;
        else
            $width = intval($width);

        // get mime type of file
        $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $filePath);

        $content = $this->getResizedImageFromLocal($filePath, $mime, $width, $height);
        return Response::create($content, 200, [
            'Content-Type' => $mime,
        ]);
    }

    private function getResizedImageFromLocal($filePath, $mime, $width, $height)
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
