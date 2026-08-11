<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RiskBoardController;
use App\Http\Controllers\Api\TeacherController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware(['throttle:api', 'jwt.auth'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/teachers', [TeacherController::class, 'index']);
    Route::get('/risk-board', [RiskBoardController::class, 'index']);
});
