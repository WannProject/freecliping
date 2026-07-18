<?php

use App\Http\Controllers\ClipController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

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

require __DIR__.'/auth.php';

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
