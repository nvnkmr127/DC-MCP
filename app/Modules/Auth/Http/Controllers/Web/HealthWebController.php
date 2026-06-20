<?php

namespace App\Modules\Auth\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthWebController extends Controller
{
    public function index()
    {
        $db = true;
        try { DB::connection()->getPdo(); } catch (\Exception $e) { $db = false; }
        
        $redis = true;
        try { Redis::ping(); } catch (\Exception $e) { $redis = false; }
        
        $s3 = true;
        // Mock S3 check
        
        return Inertia::render('Settings/Health', [
            'status' => [
                'database' => $db,
                'redis' => $redis,
                's3' => $s3,
                'mcp' => true,
            ]
        ]);
    }
}
