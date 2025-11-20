<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileManagerController extends Controller
{
    /**
     * Show file manager for domain
     */
    public function index(Domain $domain, Request $request)
    {
        $path = $request->query('path', '');
        $fullPath = $this->getFullPath($domain, $path);

        // Security check: ensure path is within document root
        if (!$this->isPathSafe($domain, $fullPath)) {
            return Redirect::back()->with('error', 'Access denied: Path is outside document root');
        }

        $items = [];
        if (File::isDirectory($fullPath)) {
            $files = File::files($fullPath);
            $directories = File::directories($fullPath);

            foreach ($directories as $dir) {
                $items[] = [
                    'name' => basename($dir),
                    'type' => 'directory',
                    'path' => $this->getRelativePath($domain, $dir),
                    'size' => null,
                    'modified' => File::lastModified($dir),
                    'permissions' => substr(sprintf('%o', fileperms($dir)), -4),
                ];
            }

            foreach ($files as $file) {
                $items[] = [
                    'name' => basename($file),
                    'type' => 'file',
                    'path' => $this->getRelativePath($domain, $file),
                    'size' => File::size($file),
                    'modified' => File::lastModified($file),
                    'permissions' => substr(sprintf('%o', fileperms($file)), -4),
                    'extension' => File::extension($file),
                ];
            }
        }

        return inertia('Domains/FileManager', [
            'domain' => $domain,
            'currentPath' => $path,
            'items' => $items,
            'breadcrumbs' => $this->getBreadcrumbs($path),
        ]);
    }

    /**
     * Download file
     */
    public function download(Domain $domain, Request $request)
    {
        $path = $request->query('path', '');
        $fullPath = $this->getFullPath($domain, $path);

        if (!$this->isPathSafe($domain, $fullPath) || !File::isFile($fullPath)) {
            abort(404);
        }

        return response()->download($fullPath);
    }

    /**
     * Upload files
     */
    public function upload(Domain $domain, Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:102400', // 100MB max
            'path' => 'nullable|string',
        ]);

        $path = $request->input('path', '');
        $fullPath = $this->getFullPath($domain, $path);

        if (!$this->isPathSafe($domain, $fullPath)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $uploadedFiles = [];
        foreach ($request->file('files') as $file) {
            $filename = $file->getClientOriginalName();
            $file->move($fullPath, $filename);
            $uploadedFiles[] = $filename;
        }

        return response()->json([
            'message' => count($uploadedFiles) . ' file(s) uploaded successfully',
            'files' => $uploadedFiles,
        ]);
    }

    /**
     * Create directory
     */
    public function createDirectory(Domain $domain, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'path' => 'nullable|string',
        ]);

        $path = $request->input('path', '');
        $dirName = $request->input('name');
        $fullPath = $this->getFullPath($domain, $path . '/' . $dirName);

        if (!$this->isPathSafe($domain, $fullPath)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (File::exists($fullPath)) {
            return response()->json(['error' => 'Directory already exists'], 422);
        }

        File::makeDirectory($fullPath, 0755, true);

        return response()->json(['message' => 'Directory created successfully']);
    }

    /**
     * Rename file or directory
     */
    public function rename(Domain $domain, Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'newName' => 'required|string|max:255',
        ]);

        $oldPath = $this->getFullPath($domain, $request->input('path'));
        $newPath = dirname($oldPath) . '/' . $request->input('newName');

        if (!$this->isPathSafe($domain, $oldPath) || !$this->isPathSafe($domain, $newPath)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (File::exists($newPath)) {
            return response()->json(['error' => 'File or directory already exists'], 422);
        }

        File::move($oldPath, $newPath);

        return response()->json(['message' => 'Renamed successfully']);
    }

    /**
     * Delete file or directory
     */
    public function delete(Domain $domain, Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $fullPath = $this->getFullPath($domain, $request->input('path'));

        if (!$this->isPathSafe($domain, $fullPath)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (File::isDirectory($fullPath)) {
            File::deleteDirectory($fullPath);
        } else {
            File::delete($fullPath);
        }

        return response()->json(['message' => 'Deleted successfully']);
    }

    /**
     * Get file content for editing
     */
    public function getContent(Domain $domain, Request $request)
    {
        $path = $request->query('path', '');
        $fullPath = $this->getFullPath($domain, $path);

        if (!$this->isPathSafe($domain, $fullPath) || !File::isFile($fullPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // Only allow text files
        $extension = File::extension($fullPath);
        $textExtensions = ['txt', 'php', 'js', 'css', 'html', 'json', 'xml', 'md', 'yaml', 'yml', 'ini', 'conf', 'log'];
        
        if (!in_array(strtolower($extension), $textExtensions)) {
            return response()->json(['error' => 'File type not editable'], 422);
        }

        $content = File::get($fullPath);

        return response()->json([
            'content' => $content,
            'path' => $path,
        ]);
    }

    /**
     * Save file content
     */
    public function saveContent(Domain $domain, Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'content' => 'required|string',
        ]);

        $fullPath = $this->getFullPath($domain, $request->input('path'));

        if (!$this->isPathSafe($domain, $fullPath)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        File::put($fullPath, $request->input('content'));

        return response()->json(['message' => 'File saved successfully']);
    }

    /**
     * Get full filesystem path
     */
    private function getFullPath(Domain $domain, string $relativePath): string
    {
        $relativePath = trim($relativePath, '/');
        return rtrim($domain->document_root, '/') . ($relativePath ? '/' . $relativePath : '');
    }

    /**
     * Get relative path from document root
     */
    private function getRelativePath(Domain $domain, string $fullPath): string
    {
        $documentRoot = rtrim($domain->document_root, '/');
        return trim(str_replace($documentRoot, '', $fullPath), '/');
    }

    /**
     * Check if path is within document root (security check)
     */
    private function isPathSafe(Domain $domain, string $fullPath): bool
    {
        $documentRoot = realpath($domain->document_root);
        $targetPath = realpath($fullPath) ?: $fullPath; // realpath returns false if doesn't exist
        
        return Str::startsWith($targetPath, $documentRoot);
    }

    /**
     * Get breadcrumb navigation
     */
    private function getBreadcrumbs(string $path): array
    {
        if (empty($path)) {
            return [['name' => 'Home', 'path' => '']];
        }

        $parts = explode('/', trim($path, '/'));
        $breadcrumbs = [['name' => 'Home', 'path' => '']];
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= ($currentPath ? '/' : '') . $part;
            $breadcrumbs[] = [
                'name' => $part,
                'path' => $currentPath,
            ];
        }

        return $breadcrumbs;
    }
}
