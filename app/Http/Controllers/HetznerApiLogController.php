<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\HetznerApiLog;
use Illuminate\Support\Facades\Auth;

class HetznerApiLogController extends Controller
{
    public function index(Domain $domain)
    {
        $this->authorizeDomain($domain);
        $logs = $domain->hetznerApiLogs()->latest()->paginate(50);
        return response()->json($logs);
    }

    public function show(Domain $domain, HetznerApiLog $log)
    {
        $this->authorizeDomain($domain);
        abort_unless($log->domain_id === $domain->id, 404);
        return response()->json($log);
    }

    protected function authorizeDomain(Domain $domain): void
    {
        abort_unless($domain->user_id === Auth::id(), 403);
    }
}
