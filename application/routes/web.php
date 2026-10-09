<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\RiskTreatmentController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/risks/{risk}/treatment', [RiskTreatmentController::class, 'show'])->name('risks.treatment');
    Route::post('/risks/{risk}/actions', [RiskTreatmentController::class, 'store'])->name('risk-actions.store');
    Route::put('/risk-actions/{action}', [RiskTreatmentController::class, 'update'])->name('risk-actions.update');
    Route::post('/risk-actions/{action}/submit', [RiskTreatmentController::class, 'submit'])->name('risk-actions.submit');
    Route::post('/risk-actions/{action}/decision', [RiskTreatmentController::class, 'decide'])->name('risk-actions.decide');
    Route::post('/risks/{risk}/residual', [RiskTreatmentController::class, 'residual'])->name('risks.residual');
    Route::get('/risk-evidence/{evidence}', [RiskTreatmentController::class, 'download'])->name('risk-evidence.download');
    Route::resource('risks', RiskController::class)->except('destroy');
    Route::post('/risks/{risk}/transition', [RiskController::class, 'transition'])->name('risks.transition');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'newVersion'])->name('documents.versions.store');
    Route::put('/versions/{version}', [DocumentController::class, 'update'])->name('versions.update');
    Route::post('/versions/{version}/transition', [DocumentController::class, 'transition'])->name('versions.transition');
    Route::get('/versions/{version}/download', [DocumentController::class, 'download'])->name('versions.download');
    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminController::class, 'index'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
        Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::get('/departments', [AdminController::class, 'departments'])->name('departments');
        Route::post('/departments', [AdminController::class, 'storeDepartment'])->name('departments.store');
    });
});
