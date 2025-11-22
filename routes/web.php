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
    Route::post('/domains/{domain}/suspend', [App\Http\Controllers\DomainController::class, 'suspend'])->name('domains.suspend');
    Route::post('/domains/{domain}/resume', [App\Http\Controllers\DomainController::class, 'resume'])->name('domains.resume');
    
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
    
    // Database management
    Route::get('/domains/{domain}/databases', [App\Http\Controllers\DatabaseController::class, 'index'])->name('databases.index');
    Route::post('/domains/{domain}/databases', [App\Http\Controllers\DatabaseController::class, 'store'])->name('databases.store');
    Route::get('/domains/{domain}/databases/{database}', [App\Http\Controllers\DatabaseController::class, 'show'])->name('databases.show');
    Route::delete('/domains/{domain}/databases/{database}', [App\Http\Controllers\DatabaseController::class, 'destroy'])->name('databases.destroy');
    Route::post('/domains/{domain}/databases/{database}/suspend', [App\Http\Controllers\DatabaseController::class, 'suspend'])->name('databases.suspend');
    Route::post('/domains/{domain}/databases/{database}/resume', [App\Http\Controllers\DatabaseController::class, 'resume'])->name('databases.resume');
    Route::get('/domains/{domain}/databases/{database}/phpmyadmin', [App\Http\Controllers\DatabaseController::class, 'openPhpMyAdmin'])->name('databases.phpmyadmin');
    
    // Mail management
    Route::get('/mail', [App\Http\Controllers\MailController::class, 'index'])->name('mail.index');
    Route::post('/mail/mailboxes', [App\Http\Controllers\MailController::class, 'store'])->name('mail.mailboxes.store');
    Route::put('/mail/mailboxes/{mailbox}', [App\Http\Controllers\MailController::class, 'update'])->name('mail.mailboxes.update');
    Route::delete('/mail/mailboxes/{mailbox}', [App\Http\Controllers\MailController::class, 'destroy'])->name('mail.mailboxes.destroy');
    Route::post('/mail/mailboxes/{mailbox}/size', [App\Http\Controllers\MailController::class, 'calculateSize'])->name('mail.mailboxes.size');
    Route::post('/mail/aliases', [App\Http\Controllers\MailController::class, 'storeAlias'])->name('mail.aliases.store');
    Route::delete('/mail/aliases/{alias}', [App\Http\Controllers\MailController::class, 'destroyAlias'])->name('mail.aliases.destroy');
    Route::get('/mail/domains/{domain}/dns', [App\Http\Controllers\MailController::class, 'dnsRecords'])->name('mail.dns');
    Route::get('/mail/settings', [App\Http\Controllers\MailController::class, 'settings'])->name('mail.settings');
    Route::post('/mail/settings', [App\Http\Controllers\MailController::class, 'updateSettings'])->name('mail.settings.update');
    
    // Settings
    Route::get('/settings', [App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/profile', [App\Http\Controllers\SettingsController::class, 'updateProfile'])->name('settings.profile');
    
    // Monitoring
    Route::get('/monitoring', [App\Http\Controllers\MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/monitoring/stats', [App\Http\Controllers\MonitoringController::class, 'stats'])->name('monitoring.stats');
    Route::get('/monitoring/history', [App\Http\Controllers\MonitoringController::class, 'history'])->name('monitoring.history');
});
