<?php

use App\Http\Controllers\LocalWorkerJobController;
use Illuminate\Support\Facades\Route;

Route::patch('local-worker/jobs/{localWorkerJob}', [LocalWorkerJobController::class, 'update'])
    ->name('local-worker-jobs.update');
