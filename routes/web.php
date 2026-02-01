<?php

use Illuminate\Support\Facades\Route;

Route::get("/", function () {
    return response()->json([
        "message" => "Comfaca API - Backend desacoplado",
        "version" => "1.0.0",
        "status" => "active"
    ]);
});
