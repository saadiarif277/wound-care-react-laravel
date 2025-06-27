<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\Glide\Responses\SymfonyResponseFactory;
use League\Glide\ServerFactory;

class ImagesController extends Controller
{
    public function show(Request $request, $path)
    {
        $server = ServerFactory::create([
            'response' => new SymfonyResponseFactory($request),
            'source' => storage_path('app/public'),
            'cache' => storage_path('app/public'),
            'cache_path_prefix' => '.glide-cache',
        ]);

        return $server->getImageResponse($path, $request->all());
    }
}
