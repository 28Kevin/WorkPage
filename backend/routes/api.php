<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\MedicalExamController;
use App\Http\Controllers\Api\PublicVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modulo de consulta publica (acceso externo)
|--------------------------------------------------------------------------
*/
Route::prefix('public')->name('api.public.')->middleware('throttle:30,1')->group(function () {
    Route::get('/exams/search', [PublicVerificationController::class, 'search'])->name('search');
    Route::get('/verify/{code}', [PublicVerificationController::class, 'verify'])->name('verify');
});

/*
|--------------------------------------------------------------------------
| Autenticacion del modulo administrativo
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('api.auth.login');

/*
|--------------------------------------------------------------------------
| Modulo administrativo (acceso restringido)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    Route::get('/catalogs', [CatalogController::class, 'index'])->name('api.catalogs');

    Route::get('/tools/ideal-weight', [MedicalExamController::class, 'idealWeight'])->name('api.tools.ideal-weight');
    Route::get('/exams/next-order-number', [MedicalExamController::class, 'nextOrderNumber'])->name('api.exams.next-order');

    Route::get('/exams', [MedicalExamController::class, 'index'])->name('api.exams.index');
    Route::post('/exams', [MedicalExamController::class, 'store'])->name('api.exams.store');
    Route::get('/exams/{exam}', [MedicalExamController::class, 'show'])->name('api.exams.show');
    Route::get('/exams/{exam}/pdf', [MedicalExamController::class, 'pdf'])->name('api.exams.pdf');
});
