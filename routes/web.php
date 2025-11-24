<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\SubdomainController;
use App\Http\Controllers\DnsRecordController;
use App\Http\Controllers\NginxConfigLogController;
use App\Http\Controllers\HetznerApiLogController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Inertia pages
    Route::get('/domains', function () { return Inertia::render('Domains/Index'); })->name('domains.index');
    Route::get('/domains/{domain}', function (\App\Models\Domain $domain) { return Inertia::render('Domains/Show', ['domainId' => $domain->id]); })->name('domains.show');

    // JSON CRUD endpoints Domains
    Route::get('/api/domains', [DomainController::class, 'index'])->name('api.domains.index');
    Route::post('/api/domains', [DomainController::class, 'store'])->name('api.domains.store');
    Route::get('/api/domains/{domain}', [DomainController::class, 'show'])->name('api.domains.show');
    Route::patch('/api/domains/{domain}', [DomainController::class, 'update'])->name('api.domains.update');
    Route::delete('/api/domains/{domain}', [DomainController::class, 'destroy'])->name('api.domains.destroy');
    Route::post('/api/domains/{domain}/verify', [DomainController::class, 'verify'])->name('api.domains.verify');
    Route::post('/api/domains/{domain}/request-wildcard', [DomainController::class, 'requestWildcard'])->name('api.domains.requestWildcard');

    // Subdomains
    Route::get('/api/domains/{domain}/subdomains', [SubdomainController::class, 'index'])->name('api.subdomains.index');
    Route::post('/api/domains/{domain}/subdomains', [SubdomainController::class, 'store'])->name('api.subdomains.store');
    Route::get('/api/domains/{domain}/subdomains/{subdomain}', [SubdomainController::class, 'show'])->name('api.subdomains.show');
    Route::patch('/api/domains/{domain}/subdomains/{subdomain}', [SubdomainController::class, 'update'])->name('api.subdomains.update');
    Route::delete('/api/domains/{domain}/subdomains/{subdomain}', [SubdomainController::class, 'destroy'])->name('api.subdomains.destroy');

    // DNS Records
    Route::get('/api/domains/{domain}/records', [DnsRecordController::class, 'index'])->name('api.records.index');
    Route::post('/api/domains/{domain}/records', [DnsRecordController::class, 'store'])->name('api.records.store');
    Route::delete('/api/domains/{domain}/records/{dnsRecord}', [DnsRecordController::class, 'destroy'])->name('api.records.destroy');

    // Nginx Logs
    Route::get('/api/domains/{domain}/nginx-logs', [NginxConfigLogController::class, 'index'])->name('api.nginxlogs.index');
    Route::get('/api/domains/{domain}/nginx-logs/{log}', [NginxConfigLogController::class, 'show'])->name('api.nginxlogs.show');

    // Hetzner API Logs
    Route::get('/api/domains/{domain}/hetzner-logs', [HetznerApiLogController::class, 'index'])->name('api.hetznerlogs.index');
    Route::get('/api/domains/{domain}/hetzner-logs/{log}', [HetznerApiLogController::class, 'show'])->name('api.hetznerlogs.show');
});

require __DIR__.'/auth.php';
