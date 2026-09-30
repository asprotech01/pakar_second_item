<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/riwayat', [DashboardController::class, 'history'])->name('assessments.history');
Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
Route::get('/assessments/{assessmentSession}', [AssessmentController::class, 'show'])->name('assessments.show');
Route::post('/assessments/{assessmentSession}/evaluate', [AssessmentController::class, 'evaluate'])->name('assessments.evaluate');
Route::get('/assessments/{assessmentSession}/result', [AssessmentController::class, 'result'])->name('assessments.result');
