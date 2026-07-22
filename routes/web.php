<?php

use App\Http\Controllers\ClipController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome', [
    'maxClipLength' => config('freekliping.max_clip_length'),
]))->name('home');
Route::inertia('/privacy', 'privacy')->name('privacy');
Route::inertia('/terms', 'terms')->name('terms');

Route::prefix('clips')
    ->name('clips.')
    ->middleware('throttle:clips')
    ->group(function () {
        Route::post('metadata', [ClipController::class, 'metadata'])->name('metadata');
        Route::post('/', [ClipController::class, 'store'])->name('store');
        Route::get('{clip}', [ClipController::class, 'show'])->name('show');
        Route::patch('{clip}/filename', [ClipController::class, 'updateFilename'])->name('filename.update');
        Route::get('{clip}/download', [ClipController::class, 'download'])->middleware('signed')->name('download');
    });
