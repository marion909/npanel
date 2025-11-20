<?php

use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\SubdomainController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Domain routes
    Route::apiResource('domains', DomainController::class);
    Route::post('domains/{domain}/suspend', [DomainController::class, 'suspend'])->name('domains.suspend');
    Route::post('domains/{domain}/resume', [DomainController::class, 'resume'])->name('domains.resume');
    Route::post('domains/{domain}/ssl', [DomainController::class, 'issueSSL'])->name('domains.ssl');

    // Subdomain routes (nested under domains)
    Route::apiResource('domains.subdomains', SubdomainController::class);
});
