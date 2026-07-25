<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Career\MasterFeedback\MasterFeedbackController;
use App\Http\Controllers\Career\FeedbackAssignment\FeedbackAssignmentController;
use App\Http\Controllers\Career\Feedback\FeedbackController;
use App\Http\Controllers\Career\Feedback\FeedbackAdminController;

/*
|--------------------------------------------------------------------------
| WEB CAREER — FEEDBACK (branch: feat/feedback)
|--------------------------------------------------------------------------
| 1. Master Feedback Form + Pertanyaan (CRUD)          → admin
| 2. Assignment form ke program (specific/general)     → admin
| 3. Dashboard agregat + export                        → admin
| 4. Halaman isi feedback (token-based, tanpa login)   → publik
| 5. Cek status feedback per lamaran                   → kandidat (auth)
*/

// ── Master Feedback Form + Pertanyaan (admin) ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    $ctrl = MasterFeedbackController::class;

    Route::get('/karir/master-feedback', [$ctrl, 'index'])
        ->name('career.master-feedback');

    Route::prefix('api/v1/karir/master-feedback')
        ->name('career.api.master-feedback.')
        ->group(function () use ($ctrl) {
            Route::get('/', [$ctrl, 'list'])->name('list');
            Route::get('/{id}', [$ctrl, 'show'])->name('show');
            Route::post('/', [$ctrl, 'store'])->name('store');
            Route::put('/{id}', [$ctrl, 'update'])->name('update');
            Route::delete('/{id}', [$ctrl, 'destroy'])->name('destroy');
            Route::post('/{id}/pertanyaan', [$ctrl, 'storePertanyaan'])->name('pertanyaan.store');
            Route::put('/pertanyaan/{id}', [$ctrl, 'updatePertanyaan'])->name('pertanyaan.update');
            Route::delete('/pertanyaan/{id}', [$ctrl, 'destroyPertanyaan'])->name('pertanyaan.destroy');
        });
});

// ── Feedback Assignment (admin) ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])
    ->prefix('api/v1/karir/feedback-assignment')
    ->name('career.api.feedback.assignment.')
    ->group(function () {
        $assign = FeedbackAssignmentController::class;
        Route::get('/', [$assign, 'list'])->name('list');
        Route::post('/', [$assign, 'store'])->name('store');
        Route::put('/{id}', [$assign, 'update'])->name('update');
    });

// ── Dashboard Feedback (admin) ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    $dash = FeedbackAdminController::class;

    Route::get('/karir/feedback-dashboard', [$dash, 'dashboard'])
        ->name('career.feedback-dashboard');

    Route::prefix('api/v1/karir/feedback')
        ->name('career.api.feedback.')
        ->group(function () use ($dash) {
            Route::get('/chart', [$dash, 'chartData'])->name('chart');
            Route::get('/detail/{id}', [$dash, 'detail'])->name('detail');
        });

    // Export
    Route::prefix('api/v1/karir')
        ->name('career.api.')
        ->group(function () use ($dash) {
            Route::post('/feedback/export', [$dash, 'requestExport'])->name('feedback.export.request');
            Route::get('/export/poll', [$dash, 'pollExport'])->name('export.poll');
            Route::get('/export/download/{id}', [$dash, 'downloadExport'])->name('export.download');
            Route::delete('/export/{id}', [$dash, 'dismissExport'])->name('export.dismiss');
        });
});

// ── Halaman isi feedback (PUBLIK — token-based, tanpa login) ──
Route::get('/feedback/{hashids}/{signature}', [FeedbackController::class, 'show'])
    ->name('career.feedback.form');

Route::post('/feedback/{hashids}/{signature}', [FeedbackController::class, 'submit'])
    ->name('career.feedback.submit')
    ->middleware('throttle:3,10'); // max 3 submit per 10 menit

// ── Cek status feedback (kandidat auth) ──
Route::middleware('career.auth')
    ->prefix('api/v1/kandidat/feedback')
    ->name('career.api.kandidat.feedback.')
    ->group(function () {
        Route::get('/status/{lamaranId}', [FeedbackController::class, 'status'])
            ->name('status');
    });
