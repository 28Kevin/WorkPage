<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingAccountController;
use App\Http\Controllers\Api\BillingConceptController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\FoodHandlerRecordController;
use App\Http\Controllers\Api\GalleryImageController;
use App\Http\Controllers\Api\MedicalExamController;
use App\Http\Controllers\Api\PublicVerificationController;
use App\Http\Controllers\Api\TrainingCertificateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modulo de consulta publica (acceso externo)
|--------------------------------------------------------------------------
*/
Route::prefix('public')->name('api.public.')->middleware('throttle:30,1')->group(function () {
    Route::get('/exams/search', [PublicVerificationController::class, 'search'])->name('search');
    Route::get('/verify/{code}', [PublicVerificationController::class, 'verify'])->name('verify');
    Route::get('/certificates/{code}', [PublicVerificationController::class, 'certificate'])->name('certificate');
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

    /*
    |----------------------------------------------------------------------
    | Paquete de manipulacion de alimentos (segundo tipo de documento)
    |----------------------------------------------------------------------
    |
    | Las rutas con segmento fijo van antes de /{record}: si no, 'draft' se
    | tomaria como el identificador de un documento.
    |
    */
    Route::get('/food-handler/next-order-number', [FoodHandlerRecordController::class, 'nextOrderNumber'])
        ->name('api.food-handler.next-order');
    Route::get('/food-handler/draft', [FoodHandlerRecordController::class, 'draft'])
        ->name('api.food-handler.draft');

    Route::get('/food-handler', [FoodHandlerRecordController::class, 'index'])->name('api.food-handler.index');
    Route::post('/food-handler', [FoodHandlerRecordController::class, 'store'])->name('api.food-handler.store');
    Route::get('/food-handler/{record}', [FoodHandlerRecordController::class, 'show'])->name('api.food-handler.show');
    Route::put('/food-handler/{record}', [FoodHandlerRecordController::class, 'update'])->name('api.food-handler.update');
    Route::delete('/food-handler/{record}', [FoodHandlerRecordController::class, 'destroy'])->name('api.food-handler.destroy');
    Route::get('/food-handler/{record}/pdf', [FoodHandlerRecordController::class, 'pdf'])->name('api.food-handler.pdf');

    /*
    |----------------------------------------------------------------------
    | Certificados de capacitacion (tercer tipo de documento)
    |----------------------------------------------------------------------
    */
    Route::get('/certificates/next-order-number', [TrainingCertificateController::class, 'nextOrderNumber'])
        ->name('api.certificates.next-order');

    Route::get('/certificates', [TrainingCertificateController::class, 'index'])->name('api.certificates.index');
    Route::post('/certificates', [TrainingCertificateController::class, 'store'])->name('api.certificates.store');
    Route::get('/certificates/{certificate}', [TrainingCertificateController::class, 'show'])->name('api.certificates.show');
    Route::put('/certificates/{certificate}', [TrainingCertificateController::class, 'update'])->name('api.certificates.update');
    Route::delete('/certificates/{certificate}', [TrainingCertificateController::class, 'destroy'])->name('api.certificates.destroy');
    Route::get('/certificates/{certificate}/pdf', [TrainingCertificateController::class, 'pdf'])->name('api.certificates.pdf');

    /*
    |----------------------------------------------------------------------
    | Cuentas de cobro para empresas
    |----------------------------------------------------------------------
    |
    | Las rutas con segmento fijo van antes de /{account}: si no, 'form' se
    | tomaria como el identificador de una cuenta.
    |
    */
    Route::get('/billing/form', [BillingAccountController::class, 'form'])->name('api.billing.form');
    Route::get('/billing/amount', [BillingAccountController::class, 'amount'])->name('api.billing.amount');

    Route::get('/billing/concepts', [BillingConceptController::class, 'index'])->name('api.billing.concepts.index');
    Route::post('/billing/concepts', [BillingConceptController::class, 'store'])->name('api.billing.concepts.store');
    Route::patch('/billing/concepts/{concept}', [BillingConceptController::class, 'update'])->name('api.billing.concepts.update');
    Route::delete('/billing/concepts/{concept}', [BillingConceptController::class, 'destroy'])->name('api.billing.concepts.destroy');

    Route::get('/billing', [BillingAccountController::class, 'index'])->name('api.billing.index');
    Route::post('/billing', [BillingAccountController::class, 'store'])->name('api.billing.store');
    Route::get('/billing/{account}', [BillingAccountController::class, 'show'])->name('api.billing.show');
    Route::put('/billing/{account}', [BillingAccountController::class, 'update'])->name('api.billing.update');
    Route::delete('/billing/{account}', [BillingAccountController::class, 'destroy'])->name('api.billing.destroy');
    Route::get('/billing/{account}/pdf', [BillingAccountController::class, 'pdf'])->name('api.billing.pdf');
});
