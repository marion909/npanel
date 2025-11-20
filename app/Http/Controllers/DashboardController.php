<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display dashboard with domains list
     */
    public function index(): Response
    {
        $domains = Auth::user()->domains()
            ->with(['subdomains', 'sslCertificate', 'phpFpmPool'])
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'domains' => $domains,
        ]);
    }
}
