<?php

namespace App\Services;

class PhpFpmService
{
    protected string $socketsPath;

    protected array $supportedVersions = ['7.4','8.0','8.1','8.2','8.3'];

    public function __construct()
    {
        $this->socketsPath = env('PHP_FPM_SOCKETS_PATH', '/run/php');
    }

    public function normalizeVersion(string $version): string
    {
        if (in_array($version, $this->supportedVersions, true)) {
            return $version;
        }
        return '8.2';
    }

    public function socketPath(string $version): string
    {
        $version = $this->normalizeVersion($version);
        return $this->socketsPath . '/php' . $version . '-fpm.sock';
    }
}
