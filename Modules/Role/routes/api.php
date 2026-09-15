<?php

use Illuminate\Support\Facades\Route;
use Modules\Role\Http\Controllers\RoleController;

Route::middleware('api')->prefix('role')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
});