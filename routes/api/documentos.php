<?php

use App\Http\Controllers\Api\SolicitudDocumentosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    Route::prefix('documentos')->group(function () {
        Route::get('{documento_id}/download/{solicitud_id}', [SolicitudDocumentosController::class, 'downloadDocumentoById']);
        Route::get('{documento_id}/preview/{solicitud_id}', [SolicitudDocumentosController::class, 'previewDocumentoById']);
    });
});
