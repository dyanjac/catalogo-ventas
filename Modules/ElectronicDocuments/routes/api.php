<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/electronicdocuments', function () {
    return response()->json(['module' => 'ElectronicDocuments']);
});

