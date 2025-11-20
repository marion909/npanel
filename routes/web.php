<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Guest routes (login/register)
Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return redirect('/login');
    });
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Domain management
    Route::post('/domains', [App\Http\Controllers\DomainController::class, 'store'])->name('domains.store');
    Route::get('/domains/{domain}', [App\Http\Controllers\DomainController::class, 'show'])->name('domains.show');
    Route::get('/domains/{domain}/edit', [App\Http\Controllers\DomainController::class, 'edit'])->name('domains.edit');
    Route::put('/domains/{domain}', [App\Http\Controllers\DomainController::class, 'update'])->name('domains.update');
    Route::delete('/domains/{domain}', [App\Http\Controllers\DomainController::class, 'destroy'])->name('domains.destroy');
    Route::post('/domains/{domain}/ssl', [App\Http\Controllers\DomainController::class, 'issueSSL'])->name('domains.ssl');
    
    // Subdomain management
    Route::post('/domains/{domain}/subdomains', [App\Http\Controllers\SubdomainController::class, 'store'])->name('subdomains.store');
    Route::put('/domains/{domain}/subdomains/{subdomain}', [App\Http\Controllers\SubdomainController::class, 'update'])->name('subdomains.update');
    Route::delete('/domains/{domain}/subdomains/{subdomain}', [App\Http\Controllers\SubdomainController::class, 'destroy'])->name('subdomains.destroy');
    
    // File Manager
    Route::get('/domains/{domain}/files', [App\Http\Controllers\FileManagerController::class, 'index'])->name('domains.files');
    Route::get('/domains/{domain}/files/download', [App\Http\Controllers\FileManagerController::class, 'download'])->name('domains.files.download');
    Route::post('/domains/{domain}/files/upload', [App\Http\Controllers\FileManagerController::class, 'upload'])->name('domains.files.upload');
    Route::post('/domains/{domain}/files/directory', [App\Http\Controllers\FileManagerController::class, 'createDirectory'])->name('domains.files.directory');
    Route::post('/domains/{domain}/files/rename', [App\Http\Controllers\FileManagerController::class, 'rename'])->name('domains.files.rename');
    Route::delete('/domains/{domain}/files/delete', [App\Http\Controllers\FileManagerController::class, 'delete'])->name('domains.files.delete');
    Route::get('/domains/{domain}/files/content', [App\Http\Controllers\FileManagerController::class, 'getContent'])->name('domains.files.content');
    Route::post('/domains/{domain}/files/save', [App\Http\Controllers\FileManagerController::class, 'saveContent'])->name('domains.files.save');
});
