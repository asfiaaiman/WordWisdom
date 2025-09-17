<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\InsightController;
use App\Http\Controllers\ImageController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

Route::get('/insights/create', [InsightController::class, 'create'])->name('insights.create');
Route::post('/insights', [InsightController::class, 'store'])->name('insights.store');
Route::get('/insights', [InsightController::class, 'index'])->name('insights.index');

Route::post('/generate-image', [ImageController::class, 'generate'])->name('image.generate');
