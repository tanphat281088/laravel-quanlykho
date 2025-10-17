<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('debug/options', function () {
    return response()->json([
        'db'        => env('DB_DATABASE'),
        'channels'  => DB::table('channels')->select('id','name','active')->get(),
        'pipelines' => DB::table('pipelines')->select('id','name','sort_order','active')->get(),
        'staff_cnt' => DB::table('users')->count(),
    ]);
});
