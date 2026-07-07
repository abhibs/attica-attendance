<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FrontendWebController extends Controller
{
    public function index(Request $request): Response
    {
        $frontendIndexFile = $this->frontendWebBuildPath().DIRECTORY_SEPARATOR.'index.html';

        if (! is_file($frontendIndexFile)) {
            return response(
                'Frontend web build not found. Run `flutter build web` inside the `frontend` directory first.',
                503
            );
        }

        $frontendIndexHtml = file_get_contents($frontendIndexFile);
        abort_unless($frontendIndexHtml !== false, 500);

        $baseHref = trim(str_replace('\\', '/', $request->getBaseUrl()));
        $baseHref = $baseHref === '' ? '/' : rtrim($baseHref, '/').'/';
        $escapedBaseHref = htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8');

        $frontendIndexHtml = preg_replace(
            '/<base href="[^"]*">/',
            '<base href="'.$escapedBaseHref.'">',
            $frontendIndexHtml,
            1
        ) ?? $frontendIndexHtml;

        return response($frontendIndexHtml, 200, $this->noCacheHtmlHeaders());
    }

    public function asset(string $path): BinaryFileResponse
    {
        return $this->file('assets/'.$path);
    }

    public function icon(string $path): BinaryFileResponse
    {
        return $this->file('icons/'.$path);
    }

    public function canvaskit(string $path): BinaryFileResponse
    {
        return $this->file('canvaskit/'.$path);
    }

    public function rootFile(string $file): BinaryFileResponse
    {
        return $this->file($file);
    }

    private function file(string $relativePath): BinaryFileResponse
    {
        $frontendWebBuildPath = $this->frontendWebBuildPath();
        $sanitizedRelativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        $absolutePath = realpath($frontendWebBuildPath.DIRECTORY_SEPARATOR.$sanitizedRelativePath);

        abort_unless(
            $absolutePath !== false
            && str_starts_with($absolutePath, $frontendWebBuildPath)
            && is_file($absolutePath),
            404
        );

        return response()->file($absolutePath, $this->noCacheFileHeaders());
    }

    private function frontendWebBuildPath(): string
    {
        $projectRootPath = dirname(base_path());
        $frontendWebBuildPathCandidates = [
            $projectRootPath.DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'web',
            $projectRootPath.DIRECTORY_SEPARATOR.'Frontend'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'web',
            base_path('frontend'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'web'),
            base_path('Frontend'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'web'),
            base_path('build'.DIRECTORY_SEPARATOR.'web'),
            public_path('build'.DIRECTORY_SEPARATOR.'web'),
            base_path(),
            public_path(),
        ];

        foreach (array_unique($frontendWebBuildPathCandidates) as $frontendWebBuildPathCandidate) {
            $resolvedCandidatePath = realpath($frontendWebBuildPathCandidate);

            if ($resolvedCandidatePath === false || ! is_dir($resolvedCandidatePath)) {
                continue;
            }

            $hasFrontendIndex = is_file($resolvedCandidatePath.DIRECTORY_SEPARATOR.'index.html');
            $hasFlutterRuntime = is_file($resolvedCandidatePath.DIRECTORY_SEPARATOR.'flutter_bootstrap.js')
                || is_file($resolvedCandidatePath.DIRECTORY_SEPARATOR.'main.dart.js');

            if ($hasFrontendIndex && $hasFlutterRuntime) {
                return $resolvedCandidatePath;
            }
        }

        return $frontendWebBuildPathCandidates[0];
    }

    private function noCacheHtmlHeaders(): array
    {
        return [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }

    private function noCacheFileHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
