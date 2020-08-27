<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class MediaController extends BaseController
{
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
        $type = exif_imagetype($filePath);
        $mimeType = image_type_to_mime_type($type);

        // Perform the image scaling
        $width = $request->query('width');
        $height = $request->query('height');
        if (empty($width))
            $width = 64;
        else
            $width = intval($width);

        list($originalWidth, $originalHeight) = getimagesize($filePath);
        $thumbWidth = $width;
        if (empty($height)) {
            $ratio = $width / $originalWidth;
            $thumbHeight = ceil($originalHeight * $ratio);
        } else {
            $thumbHeight = $height;
        }

        $img = Image::make($filePath)->resize($thumbWidth, $thumbHeight);
        return Response::make($img->encode('jpg'))->header('Content-Type', 'image/jpeg');
    }
}
