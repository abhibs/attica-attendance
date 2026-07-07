<?php

namespace App\Support;

class ProjectAsset
{
    public static function url(?string $path = null): string
    {
        static $prefix = null;

        $rawPath = str_replace('\\', '/', (string) $path);

        if ($rawPath === '') {
            return url('/');
        }

        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $rawPath) === 1 || str_starts_with($rawPath, 'data:')) {
            return $rawPath;
        }

        $normalizedPath = ltrim($rawPath, '/');

        if (str_starts_with($normalizedPath, 'public/')) {
            $normalizedPath = substr($normalizedPath, 7);
        }

        if ($prefix === null) {
            $documentRoot = realpath((string) request()->server('DOCUMENT_ROOT', ''));
            $publicDirectory = realpath(public_path());
            $prefix = '';

            if ($documentRoot !== false && $publicDirectory !== false && $documentRoot !== $publicDirectory) {
                $prefix = 'public/';
            }
        }

        return url($prefix . $normalizedPath);
    }
}
