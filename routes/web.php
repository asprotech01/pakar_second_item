<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceCatalogController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/devices/autocomplete', [DashboardController::class, 'autocomplete'])->name('devices.autocomplete');
    Route::get('/riwayat', [DashboardController::class, 'history'])->name('assessments.history');
    Route::get('/devices', [DeviceCatalogController::class, 'index'])->name('devices.index');
    Route::post('/devices/search', [DeviceCatalogController::class, 'search'])->middleware('throttle:30,1')->name('devices.search');
    Route::post('/devices/select', [DeviceCatalogController::class, 'select'])->middleware('throttle:30,1')->name('devices.select');
    Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
    Route::get('/assessments/{assessmentSession}', [AssessmentController::class, 'show'])->name('assessments.show');
    Route::post('/assessments/{assessmentSession}/evaluate', [AssessmentController::class, 'evaluate'])->name('assessments.evaluate');
    Route::get('/assessments/{assessmentSession}/result', [AssessmentController::class, 'result'])->name('assessments.result');
});

Route::middleware(['auth', EnsureUserIsAdmin::class])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/knowledge-base', [KnowledgeBaseController::class, 'index'])->name('admin.knowledge-base.index');
    Route::post('/knowledge-base/device-categories', [KnowledgeBaseController::class, 'storeDeviceCategory'])->name('admin.knowledge-base.device-categories.store');
    Route::put('/knowledge-base/device-categories/{deviceCategory}', [KnowledgeBaseController::class, 'updateDeviceCategory'])->name('admin.knowledge-base.device-categories.update');
    Route::delete('/knowledge-base/device-categories/{deviceCategory}', [KnowledgeBaseController::class, 'destroyDeviceCategory'])->name('admin.knowledge-base.device-categories.destroy');
    Route::post('/knowledge-base/inspection-categories', [KnowledgeBaseController::class, 'storeInspectionCategory'])->name('admin.knowledge-base.inspection-categories.store');
    Route::put('/knowledge-base/inspection-categories/{inspectionCategory}', [KnowledgeBaseController::class, 'updateInspectionCategory'])->name('admin.knowledge-base.inspection-categories.update');
    Route::delete('/knowledge-base/inspection-categories/{inspectionCategory}', [KnowledgeBaseController::class, 'destroyInspectionCategory'])->name('admin.knowledge-base.inspection-categories.destroy');
    Route::post('/knowledge-base/device-types', [KnowledgeBaseController::class, 'storeDeviceType'])->name('admin.knowledge-base.device-types.store');
    Route::put('/knowledge-base/device-types/{deviceType}', [KnowledgeBaseController::class, 'updateDeviceType'])->name('admin.knowledge-base.device-types.update');
    Route::delete('/knowledge-base/device-types/{deviceType}', [KnowledgeBaseController::class, 'destroyDeviceType'])->name('admin.knowledge-base.device-types.destroy');
    Route::post('/knowledge-base/questions', [KnowledgeBaseController::class, 'storeQuestion'])->name('admin.knowledge-base.questions.store');
    Route::put('/knowledge-base/questions/{question}', [KnowledgeBaseController::class, 'updateQuestion'])->name('admin.knowledge-base.questions.update');
    Route::delete('/knowledge-base/questions/{question}', [KnowledgeBaseController::class, 'destroyQuestion'])->name('admin.knowledge-base.questions.destroy');
    Route::post('/knowledge-base/question-options', [KnowledgeBaseController::class, 'storeQuestionOption'])->name('admin.knowledge-base.question-options.store');
    Route::put('/knowledge-base/question-options/{questionOption}', [KnowledgeBaseController::class, 'updateQuestionOption'])->name('admin.knowledge-base.question-options.update');
    Route::delete('/knowledge-base/question-options/{questionOption}', [KnowledgeBaseController::class, 'destroyQuestionOption'])->name('admin.knowledge-base.question-options.destroy');
    Route::post('/knowledge-base/facts', [KnowledgeBaseController::class, 'storeFact'])->name('admin.knowledge-base.facts.store');
    Route::put('/knowledge-base/facts/{fact}', [KnowledgeBaseController::class, 'updateFact'])->name('admin.knowledge-base.facts.update');
    Route::delete('/knowledge-base/facts/{fact}', [KnowledgeBaseController::class, 'destroyFact'])->name('admin.knowledge-base.facts.destroy');
    Route::post('/knowledge-base/rules', [KnowledgeBaseController::class, 'storeRule'])->name('admin.knowledge-base.rules.store');
    Route::put('/knowledge-base/rules/{rule}', [KnowledgeBaseController::class, 'updateRule'])->name('admin.knowledge-base.rules.update');
    Route::delete('/knowledge-base/rules/{rule}', [KnowledgeBaseController::class, 'destroyRule'])->name('admin.knowledge-base.rules.destroy');
    Route::post('/knowledge-base/experts', [KnowledgeBaseController::class, 'storeExpert'])->name('admin.knowledge-base.experts.store');
    Route::put('/knowledge-base/experts/{expert}', [KnowledgeBaseController::class, 'updateExpert'])->name('admin.knowledge-base.experts.update');
    Route::delete('/knowledge-base/experts/{expert}', [KnowledgeBaseController::class, 'destroyExpert'])->name('admin.knowledge-base.experts.destroy');
    Route::post('/knowledge-base/rules/{rule}/validate', [KnowledgeBaseController::class, 'storeExpertValidation'])->name('admin.knowledge-base.rules.validate');
});
