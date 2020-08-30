<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

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

        $content = $this->getResizedImageContent($filePath, $mime, $width, $height);
        return Response::create($content, 200, [
            'Content-Type' => $mime,
        ]);
    }
}
