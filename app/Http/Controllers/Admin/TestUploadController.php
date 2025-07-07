<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestUploadController extends Controller
{
    public function test(Request $request)
    {
        $debug = [
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'has_file' => $request->hasFile('pdf_file'),
            'all_files' => $request->allFiles(),
            'all_input' => $request->all(),
            'files_array' => $_FILES,
            'server' => [
                'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
                'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? null,
                'CONTENT_LENGTH' => $_SERVER['CONTENT_LENGTH'] ?? null,
            ],
            'php_input' => substr(file_get_contents('php://input'), 0, 1000), // First 1000 chars
        ];
        
        Log::info('Test Upload Debug', $debug);
        
        return response()->json([
            'success' => false,
            'debug' => $debug,
            'message' => 'Debug test complete'
        ]);
    }
}