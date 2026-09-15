<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;

Route::middleware('api')->prefix('user')->group(function () {
    Route::get('/', [UserController::class, 'index']);
});