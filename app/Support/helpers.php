<?php

use App\Support\ProjectAsset;

if (! function_exists('project_asset')) {
    function project_asset(?string $path = null): string
    {
        return ProjectAsset::url($path);
    }
}
