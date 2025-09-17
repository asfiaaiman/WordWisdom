<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InsightApiController;
use App\Http\Controllers\Api\DefinitionApiController;
use App\Http\Controllers\Api\TrendingApiController;

Route::post('/insights/generate', [InsightApiController::class, 'generate'])->name('api.insights.generate');
Route::get('/definitions', [DefinitionApiController::class, 'show'])->name('api.definitions.show');
Route::get('/trending-topics', [TrendingApiController::class, 'index'])->name('api.trending.index');

Route::middleware('auth')->group(function () {
	Route::get('/insights', [InsightApiController::class, 'index'])->name('api.insights.index');
	Route::post('/insights', [InsightApiController::class, 'store'])->name('api.insights.store');
});


