<?php

use Hybridcore\ServerCast\Http\Controllers\Admin\BroadcastController;
use Illuminate\Support\Facades\Route;

// Prefixed with /admin and wrapped in web + auth + admin by the core.

Route::middleware('perm:servercast.view')->group(function (): void {
    Route::get('/servercast', [BroadcastController::class, 'index'])
        ->name('admin.servercast.index');
});

Route::middleware('perm:servercast.send')->group(function (): void {
    // Sending is rate-limited: a broadcast fans out to every server, so this
    // guards against an accidental or hostile flood of in-game spam.
    Route::post('/servercast', [BroadcastController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('admin.servercast.store');

    Route::delete('/servercast/{broadcast}', [BroadcastController::class, 'destroy'])
        ->name('admin.servercast.destroy');
});
