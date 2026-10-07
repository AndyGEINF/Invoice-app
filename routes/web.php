<?php

use App\Domain\Documents\Enums\DocumentType;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Documents\DocumentFormController;
use App\Http\Controllers\Documents\DocumentIndexController;
use App\Http\Controllers\Documents\DocumentPreviewController;
use App\Http\Controllers\Documents\DocumentShowController;
use App\Http\Controllers\Documents\InternalNotesController;
use App\Http\Controllers\Documents\IssueInvoiceController;
use App\Http\Controllers\Issuer\IssuerSettingsController;
use Illuminate\Support\Facades\Route;

// La aplicación no tiene login: ninguna ruta usa middleware de autenticación
// (constitución v2, principio V). El acceso se restringe en el despliegue.

Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)->name('dashboard');

// Datos del emisor. El formulario lleva el logotipo, así que se envía como
// multipart con POST + `_method=PUT` (Inertia lo hace solo).
Route::get('/settings/issuer', [IssuerSettingsController::class, 'edit'])->name('settings.issuer');
Route::put('/settings/issuer', [IssuerSettingsController::class, 'update'])->name('settings.issuer.update');

/*
| Documentos: un único recurso con el tipo en la URL (/invoices, /credit-notes).
| `{type}` llega al controlador como DocumentType y `{document}` solo encuentra
| documentos de ese tipo (bindings en AppServiceProvider).
*/

$fiscalTypes = [
    DocumentType::Invoice->routeSegment(),
    DocumentType::CreditNote->routeSegment(),
];

// Las rectificativas no se crean desde cero: nacen de la factura que corrigen.
$creatableTypes = [DocumentType::Invoice->routeSegment()];

Route::name('documents.')->group(function () use ($fiscalTypes, $creatableTypes): void {
    Route::get('/{type}', DocumentIndexController::class)->whereIn('type', $fiscalTypes)->name('index');
    Route::get('/{type}/create', [DocumentFormController::class, 'create'])->whereIn('type', $creatableTypes)->name('create');
    Route::post('/{type}', [DocumentFormController::class, 'store'])->whereIn('type', $creatableTypes)->name('store');

    Route::prefix('/{type}/{document}')
        ->whereIn('type', $fiscalTypes)
        ->whereUuid('document')
        ->group(function (): void {
            Route::get('/', DocumentShowController::class)->name('show');
            Route::get('/edit', [DocumentFormController::class, 'edit'])->name('edit');
            Route::get('/preview', DocumentPreviewController::class)->name('preview');
            Route::put('/', [DocumentFormController::class, 'update'])->name('update');
            Route::delete('/', [DocumentFormController::class, 'destroy'])->name('destroy');
            Route::post('/issue', IssueInvoiceController::class)->name('issue');
            Route::patch('/internal-notes', InternalNotesController::class)->name('internal-notes');
        });
});
