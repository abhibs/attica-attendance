<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class RecruitmentImageService
{
    public function storeDataUri(?string $dataUri, string $directory, string $prefix): ?string
    {
        $trimmed = trim((string) $dataUri);

        if ($trimmed === '') {
            return null;
        }

        if (! preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,(.+)$/', $trimmed, $matches)) {
            throw new \InvalidArgumentException('Invalid image capture payload.');
        }

        $extension = strtolower($matches[1]);
        $extension = match ($extension) {
            'jpeg', 'jpg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            default => throw new \InvalidArgumentException('Unsupported image format.'),
        };

        $binary = base64_decode($matches[2], true);

        if ($binary === false) {
            throw new \InvalidArgumentException('Unable to decode captured image.');
        }

        $relativeDirectory = trim($directory, '/');
        $absoluteDirectory = public_path('storage/'.$relativeDirectory);
        File::ensureDirectoryExists($absoluteDirectory);

        $filename = sprintf(
            '%s_%s_%s.%s',
            $prefix,
            now(config('app.timezone', 'Asia/Kolkata'))->format('YmdHis'),
            Str::lower(Str::random(8)),
            $extension
        );

        $absolutePath = $absoluteDirectory.DIRECTORY_SEPARATOR.$filename;
        file_put_contents($absolutePath, $binary);

        return 'storage/'.$relativeDirectory.'/'.$filename;
    }

    public function storeUploadedFile(UploadedFile $file, string $directory, string $prefix): string
    {
        $relativeDirectory = trim($directory, '/');
        $absoluteDirectory = public_path('storage/'.$relativeDirectory);
        File::ensureDirectoryExists($absoluteDirectory);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = sprintf(
            '%s_%s_%s.%s',
            $prefix,
            now(config('app.timezone', 'Asia/Kolkata'))->format('YmdHis'),
            Str::lower(Str::random(8)),
            $extension
        );

        $file->move($absoluteDirectory, $filename);

        return 'storage/'.$relativeDirectory.'/'.$filename;
    }
}
