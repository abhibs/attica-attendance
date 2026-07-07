<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $android = config('mobile_app_update.android', []);
        $downloadUrl = trim((string) ($android['download_url'] ?? ''));

        if ($downloadUrl !== '' && preg_match('/^https?:\/\//i', $downloadUrl) !== 1) {
            $downloadUrl = $this->resolveUrl($request, $downloadUrl);
        }

        $releaseNotes = collect($android['release_notes'] ?? [])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        return response()->json([
            'update' => [
                'platform' => 'android',
                'packageName' => trim((string) ($android['package_name'] ?? '')),
                'latestVersion' => trim((string) ($android['latest_version'] ?? '')),
                'latestBuildNumber' => (int) ($android['latest_build_number'] ?? 0),
                'minimumSupportedBuildNumber' => (int) ($android['minimum_supported_build_number'] ?? 0),
                'forceUpdate' => (bool) ($android['force_update'] ?? false),
                'downloadUrl' => $downloadUrl,
                'title' => trim((string) ($android['title'] ?? 'Update required')),
                'message' => trim((string) ($android['message'] ?? '')),
                'releaseNotes' => $releaseNotes,
            ],
        ]);
    }

    private function resolveUrl(Request $request, string $path): string
    {
        $trimmedPath = ltrim(trim($path), '/');

        if ($trimmedPath === '') {
            return '';
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/' . $trimmedPath;
    }
}
