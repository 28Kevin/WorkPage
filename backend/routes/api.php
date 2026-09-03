<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\GalleryImageController;
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
| Formulario publico de contacto
|--------------------------------------------------------------------------
|
| Fuera del grupo anterior a proposito: dos middlewares 'throttle' sobre la
| misma peticion comparten contador y se sumarian entre si.
|
*/
Route::post('/public/contact', [ContactMessageController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('api.public.contact');

/*
|--------------------------------------------------------------------------
| Identidad visual: la SPA la pide antes de pintar, con o sin sesion abierta
|--------------------------------------------------------------------------
*/
Route::get('/branding', [BrandingController::class, 'show'])->name('api.branding.show');
Route::get('/gallery', [GalleryImageController::class, 'index'])->name('api.gallery.index');
Route::get('/gallery/{image}/file', [GalleryImageController::class, 'file'])->name('api.gallery.file');

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

    Route::put('/branding', [BrandingController::class, 'update'])->name('api.branding.update');

    Route::post('/backups', [BackupController::class, 'store'])
        ->middleware('throttle:3,10')
        ->name('api.backups.store');

    Route::get('/gallery/all', [GalleryImageController::class, 'all'])->name('api.gallery.all');
    Route::post('/gallery', [GalleryImageController::class, 'store'])->name('api.gallery.store');
    Route::patch('/gallery/{image}', [GalleryImageController::class, 'update'])->name('api.gallery.update');
    Route::delete('/gallery/{image}', [GalleryImageController::class, 'destroy'])->name('api.gallery.destroy');

    Route::get('/contact-messages', [ContactMessageController::class, 'index'])->name('api.messages.index');
    Route::patch('/contact-messages/{message}', [ContactMessageController::class, 'toggle'])->name('api.messages.toggle');
    Route::delete('/contact-messages/{message}', [ContactMessageController::class, 'destroy'])->name('api.messages.destroy');

    Route::get('/tools/ideal-weight', [MedicalExamController::class, 'idealWeight'])->name('api.tools.ideal-weight');
    Route::get('/exams/next-order-number', [MedicalExamController::class, 'nextOrderNumber'])->name('api.exams.next-order');
    Route::get('/exams/draft', [MedicalExamController::class, 'draft'])->name('api.exams.draft');

    Route::get('/exams', [MedicalExamController::class, 'index'])->name('api.exams.index');
    Route::post('/exams', [MedicalExamController::class, 'store'])->name('api.exams.store');
    Route::get('/exams/{exam}', [MedicalExamController::class, 'show'])->name('api.exams.show');
    Route::put('/exams/{exam}', [MedicalExamController::class, 'update'])->name('api.exams.update');
    Route::delete('/exams/{exam}', [MedicalExamController::class, 'destroy'])->name('api.exams.destroy');
    Route::get('/exams/{exam}/pdf', [MedicalExamController::class, 'pdf'])->name('api.exams.pdf');
});
