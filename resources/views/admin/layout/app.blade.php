<!doctype html>
@php
    $adminUser = Auth::guard('admin')->user();
    $themePreference = in_array(
        $adminUser?->theme_preference,
        [
            'blue-theme',
            'light',
            'dark',
            'semi-dark',
            'bodered-theme',
            'emerald-theme',
            'violet-theme',
            'sunset-theme',
            'copper-theme',
            'ocean-theme',
            'mulberry-theme',
        ],
        true,
    )
        ? $adminUser->theme_preference
        : 'light';
    $cardStyle = in_array($adminUser?->card_style, ['rounded', 'sharp'], true) ? $adminUser->card_style : 'rounded';
    $tableDensity = in_array($adminUser?->table_density, ['comfortable', 'compact'], true)
        ? $adminUser->table_density
        : 'comfortable';
    $sidebarCollapsed = (bool) ($adminUser?->sidebar_collapsed ?? false);
    $themePalettes = [
        'blue-theme' => [
            'theme_primary_color' => '#0D6EFD',
            'theme_background_color' => '#0F172A',
            'theme_surface_color' => '#17213A',
            'theme_sidebar_background_color' => '#111C33',
            'theme_text_color' => '#F8FAFC',
            'theme_muted_text_color' => '#B9C4D4',
            'theme_border_color' => '#31405E',
        ],
        'light' => [
            'theme_primary_color' => '#A63D2F',
            'theme_highlight_color' => '#C8A24A',
            'theme_background_color' => '#FCF7F2',
            'theme_surface_color' => '#FFFFFF',
            'theme_sidebar_background_color' => '#FFF9F3',
            'theme_text_color' => '#35231D',
            'theme_muted_text_color' => '#8B6B61',
            'theme_border_color' => '#E9D8CC',
        ],
        'dark' => [
            'theme_primary_color' => '#6EA8FE',
            'theme_highlight_color' => '#E0B96D',
            'theme_background_color' => '#10151C',
            'theme_surface_color' => '#1B2431',
            'theme_sidebar_background_color' => '#161F2B',
            'theme_text_color' => '#EAF1FF',
            'theme_muted_text_color' => '#9DAAC0',
            'theme_border_color' => '#344154',
        ],
        'semi-dark' => [
            'theme_primary_color' => '#0D6EFD',
            'theme_highlight_color' => '#F0C36A',
            'theme_background_color' => '#F3F6FB',
            'theme_surface_color' => '#FFFFFF',
            'theme_sidebar_background_color' => '#17213A',
            'theme_text_color' => '#172033',
            'theme_muted_text_color' => '#667085',
            'theme_border_color' => '#D7DEEA',
        ],
        'bodered-theme' => [
            'theme_primary_color' => '#A63D2F',
            'theme_highlight_color' => '#C8A24A',
            'theme_background_color' => '#FFF9F5',
            'theme_surface_color' => '#FFFFFF',
            'theme_sidebar_background_color' => '#FFFCF8',
            'theme_text_color' => '#35231D',
            'theme_muted_text_color' => '#8B6B61',
            'theme_border_color' => '#E4D2C4',
        ],
        'emerald-theme' => [
            'theme_primary_color' => '#047857',
            'theme_highlight_color' => '#22C55E',
            'theme_background_color' => '#ECFDF5',
            'theme_surface_color' => '#FFFFFF',
            'theme_sidebar_background_color' => '#052E2B',
            'theme_text_color' => '#10231D',
            'theme_muted_text_color' => '#577064',
            'theme_border_color' => '#BFE7D1',
        ],
        'violet-theme' => [
            'theme_primary_color' => '#7C3AED',
            'theme_highlight_color' => '#F472B6',
            'theme_background_color' => '#F5F3FF',
            'theme_surface_color' => '#FFFFFF',
            'theme_sidebar_background_color' => '#1E1B4B',
            'theme_text_color' => '#241A3E',
            'theme_muted_text_color' => '#6D5F85',
            'theme_border_color' => '#DDD6FE',
        ],
        'sunset-theme' => [
            'theme_primary_color' => '#E11D48',
            'theme_highlight_color' => '#F59E0B',
            'theme_background_color' => '#FFF1F2',
            'theme_surface_color' => '#FFFFFF',
            'theme_sidebar_background_color' => '#3B1827',
            'theme_text_color' => '#331A25',
            'theme_muted_text_color' => '#7F5D66',
            'theme_border_color' => '#FED7E2',
        ],
        'copper-theme' => [
            'theme_primary_color' => '#B45309',
            'theme_highlight_color' => '#E9A23B',
            'theme_background_color' => '#FFF8F1',
            'theme_surface_color' => '#FFFFFF',
            'theme_sidebar_background_color' => '#3A2315',
            'theme_text_color' => '#342016',
            'theme_muted_text_color' => '#836252',
            'theme_border_color' => '#EBCFB8',
        ],
        'ocean-theme' => [
            'theme_primary_color' => '#0F766E',
            'theme_highlight_color' => '#38BDF8',
            'theme_background_color' => '#F1FAFC',
            'theme_surface_color' => '#FFFFFF',
            'theme_sidebar_background_color' => '#0B2F3A',
            'theme_text_color' => '#18313A',
            'theme_muted_text_color' => '#5B7680',
            'theme_border_color' => '#C4E5EC',
        ],
        'mulberry-theme' => [
            'theme_primary_color' => '#9D174D',
            'theme_highlight_color' => '#F59EB2',
            'theme_background_color' => '#FFF6FA',
            'theme_surface_color' => '#FFFFFF',
            'theme_sidebar_background_color' => '#341326',
            'theme_text_color' => '#351C2A',
            'theme_muted_text_color' => '#7D6070',
            'theme_border_color' => '#F2CCDB',
        ],
    ];
    $activePalette = $themePalettes[$themePreference] ?? $themePalettes['light'];
    $normalizeColor = static function (?string $value): ?string {
        $color = strtoupper(trim((string) $value));

        return preg_match('/^#[0-9A-F]{6}$/', $color) === 1 ? $color : null;
    };
    $hexToRgbArray = static function (string $hex): array {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    };
    $hexToRgb = static function (string $hex) use ($hexToRgbArray): string {
        return implode(', ', $hexToRgbArray($hex));
    };
    $contrastTextColor = static function (string $hex) use ($hexToRgbArray): string {
        [$red, $green, $blue] = $hexToRgbArray($hex);
        $brightness = ($red * 299 + $green * 587 + $blue * 114) / 1000;

        return $brightness >= 160 ? '#2D1A14' : '#FFF9F4';
    };
    $contrastRatio = static function (string $foreground, string $background) use ($hexToRgbArray): float {
        $toLuminance = static function (string $hex) use ($hexToRgbArray): float {
            $channels = array_map(static function (int $channel): float {
                $value = $channel / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            }, $hexToRgbArray($hex));

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };

        $lighter = max($toLuminance($foreground), $toLuminance($background));
        $darker = min($toLuminance($foreground), $toLuminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    };
    $ensureReadableTextColor = static function (
        string $preferred,
        array $backgrounds,
        string $fallbackDark = '#2D1A14',
        string $fallbackLight = '#FFF9F4',
        float $minimumContrast = 4.5,
    ) use ($contrastRatio): string {
        $hasReadableContrast = true;

        foreach ($backgrounds as $background) {
            if ($contrastRatio($preferred, $background) < $minimumContrast) {
                $hasReadableContrast = false;
                break;
            }
        }

        if ($hasReadableContrast) {
            return $preferred;
        }

        $candidates = [$fallbackDark, $fallbackLight];
        $bestCandidate = $fallbackDark;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $score = null;

            foreach ($backgrounds as $background) {
                $ratio = $contrastRatio($candidate, $background);
                $score = $score === null ? $ratio : min($score, $ratio);
            }

            if ($score > $bestScore) {
                $bestCandidate = $candidate;
                $bestScore = $score;
            }
        }

        return $bestCandidate;
    };
    $resolvedColors = [];

    foreach ($activePalette as $field => $fallback) {
        $resolvedColors[$field] = $normalizeColor($adminUser?->{$field}) ?? $fallback;
    }
    $resolvedTextColor = $ensureReadableTextColor($resolvedColors['theme_text_color'], [
        $resolvedColors['theme_background_color'],
        $resolvedColors['theme_surface_color'],
    ]);
    $resolvedMutedTextColor = $ensureReadableTextColor(
        $resolvedColors['theme_muted_text_color'],
        [$resolvedColors['theme_background_color'], $resolvedColors['theme_surface_color']],
        $resolvedTextColor,
        $resolvedTextColor,
        3.2,
    );
    $resolvedSidebarTextColor = $ensureReadableTextColor($resolvedColors['theme_text_color'], [
        $resolvedColors['theme_sidebar_background_color'],
    ]);
    $resolvedSidebarMutedTextColor = $ensureReadableTextColor(
        $resolvedColors['theme_muted_text_color'],
        [$resolvedColors['theme_sidebar_background_color']],
        $resolvedSidebarTextColor,
        $resolvedSidebarTextColor,
        3.2,
    );
@endphp
<html lang="en" data-bs-theme="{{ $themePreference }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attica Pagar Admin</title>
    <!--favicon-->
    <link rel="icon" href="{{ \App\Support\ProjectAsset::url('admin/assets/images/attica_favicon.png') }}?v=1"
        type="image/png">
    <!-- loader-->
    <link href="{{ \App\Support\ProjectAsset::url('admin/assets/css/pace.min.css') }}" rel="stylesheet">
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/js/pace.min.js') }}"></script>

    <!--plugins-->
    <link
        href="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}"
        rel="stylesheet">
    <link rel="stylesheet" type="text/css"
        href="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/metismenu/metisMenu.min.css') }}">
    <link rel="stylesheet" type="text/css"
        href="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/metismenu/mm-vertical.css') }}">
    <link rel="stylesheet" type="text/css"
        href="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/simplebar/css/simplebar.css') }}">
    <!--bootstrap css-->
    <link href="{{ \App\Support\ProjectAsset::url('admin/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons+Outlined" rel="stylesheet">
    <!--main css-->
    <link href="{{ \App\Support\ProjectAsset::url('admin/assets/css/bootstrap-extended.css') }}" rel="stylesheet">
    <link href="{{ \App\Support\ProjectAsset::url('admin/sass/main.css') }}" rel="stylesheet">
    <link href="{{ \App\Support\ProjectAsset::url('admin/sass/dark-theme.css') }}" rel="stylesheet">
    <link href="{{ \App\Support\ProjectAsset::url('admin/sass/blue-theme.css') }}" rel="stylesheet">
    <link href="{{ \App\Support\ProjectAsset::url('admin/sass/semi-dark.css') }}" rel="stylesheet">
    <link href="{{ \App\Support\ProjectAsset::url('admin/sass/bordered-theme.css') }}" rel="stylesheet">
    <link href="{{ \App\Support\ProjectAsset::url('admin/sass/responsive.css') }}" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.boxicons.com/animations.min.css" rel="stylesheet">
    <link
        href="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/datatable/css/dataTables.bootstrap5.min.css') }}"
        rel="stylesheet" />
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet" />
    {{-- thumbs up and down font awesome start --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css"
        integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    {{-- thumbs up and down font awesome end --}}
    <style>
        body.admin-layout {
            min-height: 100vh;
            background: var(--admin-background-color);
            color: var(--admin-text-color);
            overflow-x: hidden;
        }

        body.admin-layout .main-wrapper {
            min-height: calc(100vh - 70px);
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            padding-bottom: 0;
        }

        body.admin-layout .admin-page-content {
            flex: 1 1 auto;
            min-height: 0;
        }

        body.admin-layout .page-footer {
            position: static;
            left: auto;
            right: auto;
            bottom: auto;
            width: auto;
            margin-top: auto;
            padding: 0.75rem 1.5rem;
            background: var(--admin-surface-color);
            color: var(--admin-text-color);
            border-top: 1px solid var(--admin-border-color);
            flex-shrink: 0;
        }

        body.admin-layout,
        body.admin-layout .main-content,
        body.admin-layout .main-wrapper,
        body.admin-layout .card,
        body.admin-layout .dropdown-menu,
        body.admin-layout .modal-content,
        body.admin-layout .list-group-item,
        body.admin-layout .search-popup .card,
        body.admin-layout .table,
        body.admin-layout .table th,
        body.admin-layout .table td,
        body.admin-layout .table thead th,
        body.admin-layout .breadcrumb-title,
        body.admin-layout .breadcrumb-item,
        body.admin-layout .breadcrumb-item a,
        body.admin-layout .form-label,
        body.admin-layout .form-check-label,
        body.admin-layout .dataTables_wrapper,
        body.admin-layout .dataTables_wrapper .dataTables_info,
        body.admin-layout .dataTables_wrapper .dataTables_length label,
        body.admin-layout .dataTables_wrapper .dataTables_filter label,
        body.admin-layout .dataTables_wrapper .dataTables_paginate,
        body.admin-layout .page-link,
        body.admin-layout .btn-light,
        body.admin-layout .text-body,
        body.admin-layout .text-dark,
        body.admin-layout .card-title,
        body.admin-layout h1,
        body.admin-layout h2,
        body.admin-layout h3,
        body.admin-layout h4,
        body.admin-layout h5,
        body.admin-layout h6,
        body.admin-layout p,
        body.admin-layout span,
        body.admin-layout li,
        body.admin-layout div,
        body.admin-layout label {
            color: var(--admin-text-color);
        }

        body.admin-layout .text-muted,
        body.admin-layout small,
        body.admin-layout .form-text,
        body.admin-layout .text-secondary,
        body.admin-layout .dataTables_wrapper .dataTables_info,
        body.admin-layout .page-breadcrumb .breadcrumb-item.active {
            color: var(--admin-muted-text-color) !important;
        }

        body.admin-layout .card,
        body.admin-layout .modal-content,
        body.admin-layout .dropdown-menu,
        body.admin-layout .top-header .navbar,
        body.admin-layout .search-popup,
        body.admin-layout .search-popup .card,
        body.admin-layout .dataTables_wrapper .dataTables_paginate .paginate_button,
        body.admin-layout .page-link,
        body.admin-layout .list-group-item {
            background-color: var(--admin-surface-color) !important;
            border-color: var(--admin-border-color) !important;
        }

        body.admin-layout .sidebar-wrapper,
        body.admin-layout .sidebar-wrapper .sidebar-header,
        body.admin-layout .sidebar-wrapper .sidebar-nav,
        body.admin-layout .sidebar-wrapper .simplebar-content,
        body.admin-layout .sidebar-wrapper .sidebar-bottom {
            background-color: var(--admin-sidebar-background-color) !important;
        }

        body.admin-layout .sidebar-wrapper,
        body.admin-layout .sidebar-wrapper a,
        body.admin-layout .sidebar-wrapper button,
        body.admin-layout .sidebar-wrapper div,
        body.admin-layout .sidebar-wrapper i,
        body.admin-layout .sidebar-wrapper label,
        body.admin-layout .sidebar-wrapper li,
        body.admin-layout .sidebar-wrapper p,
        body.admin-layout .sidebar-wrapper span,
        body.admin-layout .sidebar-wrapper .material-icons-outlined,
        body.admin-layout .sidebar-wrapper .menu-title,
        body.admin-layout .sidebar-wrapper .logo-name h5,
        body.admin-layout .menu-label {
            color: var(--admin-sidebar-text-color) !important;
        }

        body.admin-layout .sidebar-wrapper .sidebar-header {
            background: linear-gradient(135deg, rgba(var(--admin-primary-color-rgb), 0.16) 0%, rgba(200, 162, 74, 0.14) 100%) !important;
            border-bottom: 1px solid rgba(var(--admin-primary-color-rgb), 0.16) !important;
            box-shadow: 0 0.45rem 1.2rem rgba(var(--admin-primary-color-rgb), 0.08);
        }

        body.admin-layout .sidebar-wrapper,
        body.admin-layout .sidebar-wrapper .sidebar-header,
        body.admin-layout .sidebar-wrapper .sidebar-nav,
        body.admin-layout .sidebar-wrapper .sidebar-bottom,
        body.admin-layout .sidebar-wrapper .sidebar-header .sidebar-close,
        body.admin-layout .sidebar-wrapper .sidebar-header .logo-name,
        body.admin-layout .sidebar-nav .metismenu li,
        body.admin-layout .sidebar-nav .metismenu ul {
            border-color: var(--admin-border-color) !important;
        }

        body.admin-layout .table,
        body.admin-layout .table>:not(caption)>*>*,
        body.admin-layout .table-bordered,
        body.admin-layout .table-bordered>:not(caption)>*,
        body.admin-layout .table-bordered>:not(caption)>*>*,
        body.admin-layout .table-responsive,
        body.admin-layout .dataTables_wrapper table.dataTable,
        body.admin-layout .dataTables_wrapper table.dataTable thead th,
        body.admin-layout .dataTables_wrapper table.dataTable tbody td {
            border-color: var(--admin-border-color) !important;
        }

        body.admin-layout .table,
        body.admin-layout .dataTables_wrapper table.dataTable {
            --bs-table-border-color: var(--admin-border-color);
            border-color: var(--admin-border-color) !important;
        }

        body.admin-layout .table-bordered,
        body.admin-layout .dataTables_wrapper table.dataTable.table-bordered {
            border: 1px solid var(--admin-border-color) !important;
        }

        body.admin-layout .table-responsive {
            border: 1px solid var(--admin-border-color) !important;
            border-radius: 0.85rem;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            max-width: 100%;
            background-color: var(--admin-surface-color);
        }

        body.admin-layout .table-responsive .dataTables_wrapper {
            min-width: 100%;
        }

        body.admin-layout .table-responsive>table {
            min-width: 100%;
        }

        body.admin-layout .table-bordered>:not(caption)>*>*,
        body.admin-layout .dataTables_wrapper table.dataTable.table-bordered>:not(caption)>*>*,
        body.admin-layout .dataTables_wrapper table.dataTable thead th,
        body.admin-layout .dataTables_wrapper table.dataTable tbody td {
            border-width: 1px !important;
            border-style: solid !important;
            border-color: var(--admin-border-color) !important;
        }

        body.admin-layout .dataTables_wrapper .dataTables_length,
        body.admin-layout .dataTables_wrapper .dataTables_filter {
            margin-bottom: 0.75rem;
        }

        body.admin-layout .dataTables_wrapper .dataTables_scroll {
            width: 100%;
        }

        body.admin-layout .dataTables_wrapper .dataTables_scrollBody {
            border-bottom-left-radius: 0.85rem;
            border-bottom-right-radius: 0.85rem;
            overflow-x: auto !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
        }

        body.admin-layout .dataTables_wrapper .dataTables_scrollHead {
            width: 100% !important;
        }

        body.admin-layout .dataTables_wrapper .dataTables_scrollHeadInner,
        body.admin-layout .dataTables_wrapper .dataTables_scrollHeadInner table,
        body.admin-layout .dataTables_wrapper .dataTables_scrollBody table {
            min-width: 100%;
        }

        body.admin-layout .dataTables_wrapper .dataTables_length select,
        body.admin-layout .dataTables_wrapper .dataTables_filter input {
            background-color: var(--admin-surface-color) !important;
            color: var(--admin-text-color) !important;
            border: 1px solid var(--admin-border-color) !important;
            border-radius: 0.65rem;
        }

        body.admin-layout .form-control,
        body.admin-layout .form-select,
        body.admin-layout .input-group-text,
        body.admin-layout textarea,
        body.admin-layout select,
        body.admin-layout input:not([type="checkbox"]):not([type="radio"]) {
            background-color: var(--admin-surface-color) !important;
            color: var(--admin-text-color) !important;
            border-color: var(--admin-border-color) !important;
        }

        body.admin-layout .form-check-input:checked {
            background-color: var(--admin-primary-color) !important;
            border-color: var(--admin-primary-color) !important;
        }

        body.admin-layout .form-control::placeholder,
        body.admin-layout textarea::placeholder,
        body.admin-layout input::placeholder {
            color: var(--admin-muted-text-color) !important;
            opacity: 0.85 !important;
        }

        @media (max-width: 991.98px) {
            body.admin-layout .dataTables_wrapper .row {
                --bs-gutter-y: 0.75rem;
            }

            body.admin-layout .dataTables_wrapper .dataTables_length,
            body.admin-layout .dataTables_wrapper .dataTables_filter,
            body.admin-layout .dataTables_wrapper .dataTables_info,
            body.admin-layout .dataTables_wrapper .dataTables_paginate {
                text-align: left !important;
            }

            body.admin-layout .dataTables_wrapper .dataTables_paginate ul.pagination {
                justify-content: flex-start !important;
                flex-wrap: wrap;
            }

            body.admin-layout .card-body,
            body.admin-layout .card .card-body {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }

        body.admin-layout .btn-primary,
        body.admin-layout .btn-primary:hover,
        body.admin-layout .btn-primary:focus,
        body.admin-layout .btn.btn-grd-primary,
        body.admin-layout .btn.btn-grd-primary:hover,
        body.admin-layout .btn.btn-grd-primary:focus,
        body.admin-layout .btn.btn-grd.btn-grd-success,
        body.admin-layout .btn.btn-grd.btn-grd-success:hover,
        body.admin-layout .btn.btn-grd.btn-grd-success:focus,
        body.admin-layout .page-item.active .page-link,
        body.admin-layout .btn-outline-primary:hover,
        body.admin-layout .btn-outline-primary:focus,
        body.admin-layout .dt-buttons .btn.btn-primary {
            background: linear-gradient(135deg, var(--admin-primary-color) 0%, var(--admin-highlight-color) 100%) !important;
            border-color: var(--admin-primary-color) !important;
            color: var(--admin-primary-contrast-color) !important;
            box-shadow: 0 0.6rem 1.4rem rgba(var(--admin-primary-color-rgb), 0.18);
        }

        body.admin-layout .btn-outline-primary,
        body.admin-layout a,
        body.admin-layout .page-link,
        body.admin-layout .text-primary {
            color: var(--admin-primary-color) !important;
        }

        body.admin-layout .btn-outline-primary,
        body.admin-layout .page-link,
        body.admin-layout .dt-buttons .btn,
        body.admin-layout .btn-light {
            border-color: var(--admin-border-color) !important;
        }

        body.admin-layout .btn-outline-primary {
            background-color: rgba(var(--admin-primary-color-rgb), 0.04) !important;
            border-color: rgba(var(--admin-primary-color-rgb), 0.35) !important;
        }

        body.admin-layout .btn-outline-primary:hover,
        body.admin-layout .btn-outline-primary:focus,
        body.admin-layout .page-item.active .page-link {
            color: var(--admin-primary-contrast-color) !important;
        }

        body.admin-layout .btn-light,
        body.admin-layout .btn-light:hover,
        body.admin-layout .btn-light:focus {
            background-color: rgba(var(--admin-primary-color-rgb), 0.06) !important;
            color: var(--admin-text-color) !important;
        }

        body.admin-layout .overlay {
            background: rgba(0, 0, 0, 0.18);
        }

        body.admin-layout.theme-card-sharp .card,
        body.admin-layout.theme-card-sharp .modal-content,
        body.admin-layout.theme-card-sharp .btn,
        body.admin-layout.theme-card-sharp .form-control,
        body.admin-layout.theme-card-sharp .form-select,
        body.admin-layout.theme-card-sharp .input-group-text,
        body.admin-layout.theme-card-sharp .page-footer {
            border-radius: 0.65rem !important;
        }

        body.admin-layout.theme-density-compact .table>:not(caption)>*>* {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        body.admin-layout.theme-density-compact .card-body {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        body.admin-layout .top-header .navbar .btn-toggle a,
        body.admin-layout .top-header .navbar .nav-right-links .nav-link {
            color: var(--admin-text-color) !important;
        }

        body.admin-layout .sidebar-wrapper .sidebar-header .logo-icon {
            width: 3.25rem;
            height: 3.25rem;
            min-width: 3.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 1rem;
            background: linear-gradient(135deg, var(--admin-primary-color) 0%, #7D2C22 100%);
            box-shadow: 0 0.65rem 1.4rem rgba(var(--admin-primary-color-rgb), 0.22);
        }

        body.admin-layout .sidebar-wrapper .sidebar-header .logo-img {
            width: 2rem !important;
            max-width: 2rem;
            height: auto;
            object-fit: contain;
        }

        body.admin-layout .sidebar-wrapper .sidebar-header .logo-name h5 {
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        body.admin-layout .top-header .navbar .btn-toggle a:hover,
        body.admin-layout .top-header .navbar .btn-toggle a:focus,
        body.admin-layout .top-header .navbar .nav-right-links .nav-link:hover,
        body.admin-layout .top-header .navbar .nav-right-links .nav-link:focus,
        body.admin-layout .sidebar-wrapper .sidebar-header .sidebar-close:hover,
        body.admin-layout .sidebar-wrapper .sidebar-header .sidebar-close:focus,
        body.admin-layout .dropdown-item:hover,
        body.admin-layout .dropdown-item:focus {
            background-color: rgba(var(--admin-primary-color-rgb), 0.08) !important;
            color: var(--admin-text-color) !important;
        }

        body.admin-layout .dropdown-item,
        body.admin-layout .dropdown-item .material-icons-outlined,
        body.admin-layout .dropdown-item .notify-title,
        body.admin-layout .dropdown-item .notify-desc,
        body.admin-layout .dropdown-item .notify-time,
        body.admin-layout .dropdown-item .user-name {
            color: inherit !important;
        }

        body.admin-layout .top-header .navbar .dropdown-menu::after {
            background: var(--admin-surface-color) !important;
            border-top-color: var(--admin-border-color) !important;
            border-left-color: var(--admin-border-color) !important;
        }

        body.admin-layout img[data-admin-image-fallback] {
            display: block;
            object-fit: cover;
            max-width: 100%;
        }

        body.admin-layout img.admin-image-fallback-applied {
            background: #fff;
        }

        body.admin-layout .top-header .navbar .nav-item .dropdown-notify .option,
        body.admin-layout .top-header .navbar .nav-item .dropdown-notify .user-wrapper,
        body.admin-layout .top-header .navbar .nav-item .dropdown-notify .notify-close {
            background-color: rgba(var(--admin-primary-color-rgb), 0.08) !important;
            color: var(--admin-text-color) !important;
        }

        body.admin-layout .sidebar-nav .metismenu ul {
            background-color: rgba(var(--admin-primary-color-rgb), 0.06) !important;
            border: 1px solid rgba(var(--admin-primary-color-rgb), 0.08);
            border-radius: 0.85rem;
            margin: 0.3rem 0 0.5rem;
            padding: 0.35rem !important;
        }

        body.admin-layout .sidebar-nav .metismenu ul a {
            background-color: transparent !important;
            color: var(--admin-sidebar-text-color) !important;
        }

        body.admin-layout .sidebar-nav .metismenu a:hover,
        body.admin-layout .sidebar-nav .metismenu a:focus,
        body.admin-layout .sidebar-nav .metismenu a:active,
        body.admin-layout .sidebar-nav .metismenu .mm-active>a {
            background-color: rgba(var(--admin-primary-color-rgb), 0.12) !important;
            color: var(--admin-primary-color) !important;
        }

        body.admin-layout .sidebar-nav .metismenu .has-arrow:after {
            border-color: var(--admin-sidebar-muted-text-color) !important;
        }

        body.admin-layout .sidebar-nav .metismenu .menu-title-with-alert {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }

        body.admin-layout .sidebar-nav .metismenu .sidebar-notification-dot {
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 999px;
            background: #d93025;
            box-shadow: 0 0 0 0.15rem rgba(217, 48, 37, 0.14);
            flex-shrink: 0;
        }
    </style>

</head>

<body
    class="admin-layout {{ $cardStyle === 'sharp' ? 'theme-card-sharp' : 'theme-card-rounded' }} {{ $tableDensity === 'compact' ? 'theme-density-compact' : 'theme-density-comfortable' }} {{ $sidebarCollapsed ? 'toggled' : '' }}"
    style="
        --admin-primary-color: {{ $resolvedColors['theme_primary_color'] }};
        --admin-primary-color-rgb: {{ $hexToRgb($resolvedColors['theme_primary_color']) }};
        --admin-primary-contrast-color: {{ $contrastTextColor($resolvedColors['theme_primary_color']) }};
        --admin-highlight-color: {{ $resolvedColors['theme_highlight_color'] ?? '#C8A24A' }};
        --admin-background-color: {{ $resolvedColors['theme_background_color'] }};
        --admin-surface-color: {{ $resolvedColors['theme_surface_color'] }};
        --admin-sidebar-background-color: {{ $resolvedColors['theme_sidebar_background_color'] }};
        --admin-text-color: {{ $resolvedTextColor }};
        --admin-text-color-rgb: {{ $hexToRgb($resolvedTextColor) }};
        --admin-muted-text-color: {{ $resolvedMutedTextColor }};
        --admin-sidebar-text-color: {{ $resolvedSidebarTextColor }};
        --admin-sidebar-muted-text-color: {{ $resolvedSidebarMutedTextColor }};
        --admin-border-color: {{ $resolvedColors['theme_border_color'] }};
        --bs-body-bg: {{ $resolvedColors['theme_background_color'] }};
        --bs-body-bg-rgb: {{ $hexToRgb($resolvedColors['theme_background_color']) }};
        --bs-body-color: {{ $resolvedTextColor }};
        --bs-body-color-rgb: {{ $hexToRgb($resolvedTextColor) }};
        --bs-border-color: {{ $resolvedColors['theme_border_color'] }};
        --bs-border-color-translucent: {{ $resolvedColors['theme_border_color'] }};
        --bs-primary: {{ $resolvedColors['theme_primary_color'] }};
        --bs-primary-rgb: {{ $hexToRgb($resolvedColors['theme_primary_color']) }};
    ">

    <!--start header-->
    @include('admin.layout.header')
    <!--end top header-->


    <!--start sidebar-->
    @include('admin.layout.sidebar')
    <!--end sidebar-->


    <!--start main wrapper-->
    <main class="main-wrapper">
        <div class="admin-page-content">
            @yield('content')
        </div>

        <!--start footer-->
        @include('admin.layout.footer')
        <!--end footer-->
    </main>
    <!--end main wrapper-->


    <!--start overlay-->
    <div class="overlay btn-toggle"></div>
    <!--end overlay-->

    <!--bootstrap js-->
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/js/jquery.min.js') }}"></script>
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}">
    </script>
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/metismenu/metisMenu.min.js') }}"></script>
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/apexchart/apexcharts.min.js') }}"></script>
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/simplebar/js/simplebar.min.js') }}"></script>
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/peity/jquery.peity.min.js') }}"></script>
    <script>
        $(".data-attributes span").peity("donut")
    </script>
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/js/main.js') }}"></script>
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/js/dashboard1.js') }}"></script>
    <script>
        new PerfectScrollbar(".user-list")
    </script>
    <script>
        (function() {
            function adminFallbackInitials(label) {
                var normalized = (label || '').trim();

                if (!normalized || normalized.toLowerCase() === 'no image') {
                    return '';
                }

                return normalized
                    .split(/\s+/)
                    .filter(Boolean)
                    .slice(0, 2)
                    .map(function(part) {
                        return part.charAt(0).toUpperCase();
                    })
                    .join('');
            }

            function adminFallbackDataUrl(label) {
                var safeLabel = (label || 'No Image').trim() || 'No Image';
                var initials = adminFallbackInitials(safeLabel);
                var svg = `
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240">
                        <defs>
                            <linearGradient id="fallbackBg" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#F7EEFF" />
                                <stop offset="100%" stop-color="#FFEAE1" />
                            </linearGradient>
                        </defs>
                        <rect width="240" height="240" rx="120" fill="url(#fallbackBg)" />
                        <circle cx="120" cy="120" r="102" fill="#FFFFFF" fill-opacity="0.82" />
                        ${
                            initials
                                ? `<text x="120" y="138" text-anchor="middle" font-family="Arial, sans-serif" font-size="82" font-weight="700" fill="#6B21D8">${initials}</text>`
                                : `
                                            <rect x="62" y="72" width="116" height="82" rx="14" fill="none" stroke="#A63D2F" stroke-width="8" stroke-opacity="0.30" />
                                            <circle cx="98" cy="104" r="13" fill="#C8A24A" fill-opacity="0.92" />
                                            <path d="M74 148l28-28c6-6 15-6 21 0l10 10 13-13c6-6 15-6 21 0l11 11v20H74z" fill="#A63D2F" fill-opacity="0.76" />
                                          `
                        }
                    </svg>
                `;

                return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
            }

            window.applyAdminImageFallback = function(image) {
                if (!image || image.dataset.adminFallbackApplied === 'true') {
                    return;
                }

                image.dataset.adminFallbackApplied = 'true';
                image.src = adminFallbackDataUrl(image.dataset.adminImageAlt || image.alt || 'No Image');
                image.classList.add('admin-image-fallback-applied');
            };

            function syncExistingAdminFallbackImages() {
                document.querySelectorAll('img[data-admin-image-fallback]').forEach(function(image) {
                    if (image.dataset.adminFallbackApplied === 'true') {
                        return;
                    }

                    if (!image.getAttribute('src')) {
                        window.applyAdminImageFallback(image);
                        return;
                    }

                    if (image.complete && image.naturalWidth === 0) {
                        window.applyAdminImageFallback(image);
                    }
                });
            }

            document.addEventListener('error', function(event) {
                var target = event.target;
                if (target instanceof HTMLImageElement && target.hasAttribute('data-admin-image-fallback')) {
                    window.applyAdminImageFallback(target);
                }
            }, true);

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', syncExistingAdminFallbackImages);
            } else {
                syncExistingAdminFallbackImages();
            }

            window.addEventListener('load', syncExistingAdminFallbackImages);
        })();
    </script>

    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/datatable/js/jquery.dataTables.min.js') }}">
    </script>
    <script src="{{ \App\Support\ProjectAsset::url('admin/assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}">
    </script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script>
        $(document).ready(function() {
            function syncScrollableDataTableColumns(tableApi) {
                if (!tableApi || typeof tableApi.columns !== 'function') {
                    return;
                }

                tableApi.columns.adjust();

                var container = $(tableApi.table().container());
                var scrollHead = container.find('.dataTables_scrollHead');
                var scrollBody = container.find('.dataTables_scrollBody');

                if (!scrollHead.length || !scrollBody.length) {
                    return;
                }

                var bodyTable = scrollBody.find('table');
                var headTable = scrollHead.find('table');

                if (bodyTable.length && headTable.length) {
                    headTable.css('width', bodyTable.outerWidth() + 'px');
                }
            }

            function detectSerialInsertIndex(table) {
                var firstHeader = table.querySelector('thead tr th');

                if (firstHeader && firstHeader.querySelector('input[type="checkbox"]')) {
                    return 1;
                }

                return 0;
            }

            function getSerialHeaderIndex(table) {
                var headers = Array.from(table.querySelectorAll('thead th'));

                for (var index = 0; index < headers.length; index += 1) {
                    var header = headers[index];
                    var text = (header.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();

                    if (['#', 's.no', 'sl no', 'sl. no', 'sr no', 'sr. no', 'serial no'].includes(text)) {
                        return index;
                    }
                }

                return null;
            }

            function ensureAdminSerialColumn(table) {
                if (!table) {
                    return null;
                }

                var existingSerialIndex = getSerialHeaderIndex(table);

                if (existingSerialIndex !== null) {
                    table.dataset.adminSerialReady = 'true';
                    table.dataset.adminSerialIndex = String(existingSerialIndex);
                    return existingSerialIndex;
                }

                if (table.dataset.adminSerialReady === 'true') {
                    return Number(table.dataset.adminSerialIndex || 0);
                }

                var headRow = table.querySelector('thead tr');

                if (!headRow) {
                    return null;
                }

                var insertIndex = detectSerialInsertIndex(table);
                var serialHeader = document.createElement('th');
                serialHeader.textContent = 'S.No';
                serialHeader.setAttribute('data-admin-serial-column', 'true');

                if (insertIndex >= headRow.children.length) {
                    headRow.appendChild(serialHeader);
                } else {
                    headRow.insertBefore(serialHeader, headRow.children[insertIndex]);
                }

                table.querySelectorAll('tbody tr').forEach(function(row) {
                    if (row.children.length === 1 && row.children[0].hasAttribute('colspan')) {
                        row.children[0].colSpan = Number(row.children[0].getAttribute('colspan') || 1) + 1;
                        return;
                    }

                    var serialCell = document.createElement('td');
                    serialCell.setAttribute('data-admin-serial-cell', 'true');

                    if (insertIndex >= row.children.length) {
                        row.appendChild(serialCell);
                    } else {
                        row.insertBefore(serialCell, row.children[insertIndex]);
                    }
                });

                table.dataset.adminSerialReady = 'true';
                table.dataset.adminSerialIndex = String(insertIndex);

                return insertIndex;
            }

            function updateStaticSerials(table) {
                var serialIndex = Number(table.dataset.adminSerialIndex || 0);
                var counter = 0;

                table.querySelectorAll('tbody tr').forEach(function(row) {
                    if (row.children.length === 1 && row.children[0].hasAttribute('colspan')) {
                        return;
                    }

                    var targetCell = row.children[serialIndex];

                    if (!targetCell) {
                        return;
                    }

                    counter += 1;
                    targetCell.textContent = counter;
                });
            }

            $('[data-admin-static-serial="true"]').each(function() {
                ensureAdminSerialColumn(this);
                updateStaticSerials(this);
            });

            $('[data-admin-datatable="true"], #example2').each(function() {
                if ($.fn.DataTable.isDataTable(this)) {
                    return;
                }

                var serialIndex = ensureAdminSerialColumn(this);
                var enableScrollX = $(this).data('adminScrollX') !== false;
                var enableSearching = $(this).data('adminSearching') !== false;
                var enablePaging = $(this).data('adminPaging') !== false;
                var enableInfo = $(this).data('adminInfo') !== false;
                var configuredSearchDelay = parseInt($(this).data('adminSearchDelay'), 10);
                var configuredPageLength = parseInt($(this).data('adminPageLength'), 10);
                var serialOffset = parseInt($(this).data('adminSerialOffset'), 10);
                var dataTableOptions = {
                    lengthChange: enablePaging,
                    pageLength: isNaN(configuredPageLength) ? 25 : configuredPageLength,
                    lengthMenu: [
                        [10, 25, 50, 100, -1],
                        [10, 25, 50, 100, 'All']
                    ],
                    order: [],
                    autoWidth: false,
                    deferRender: true,
                    searching: enableSearching,
                    paging: enablePaging,
                    info: enableInfo,
                    searchDelay: isNaN(configuredSearchDelay) ? 0 : configuredSearchDelay,
                    scrollX: enableScrollX,
                    dom: '<"row align-items-center g-2 mb-3"<"col-sm-12 col-md-6 d-flex flex-wrap align-items-center gap-2"lB><"col-sm-12 col-md-6"f>>rt<"row align-items-center g-2 mt-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    buttons: ['copy', 'csv', 'print'],
                    language: {
                        lengthMenu: 'Show _MENU_ rows'
                    }
                };

                if (serialIndex !== null) {
                    dataTableOptions.columnDefs = [{
                        targets: serialIndex,
                        orderable: false,
                        searchable: false,
                    }];

                    dataTableOptions.drawCallback = function() {
                        var api = this.api();
                        var pageInfo = api.page.info();

                        api.rows({
                            page: 'current',
                            search: 'applied',
                            order: 'applied',
                        }).every(function(rowIndex, tableLoop, displayIndex) {
                            var cellNode = api.cell(rowIndex, serialIndex).node();

                            if (cellNode) {
                                cellNode.textContent = (isNaN(serialOffset) ? 0 :
                                    serialOffset) + pageInfo.start + displayIndex + 1;
                            }
                        });
                    }
                }

                var table = $(this).DataTable(dataTableOptions);
                var syncTableLayout = function() {
                    syncScrollableDataTableColumns(table);
                };

                var toolbar = $(this).closest('.table-responsive').parent().find('.datatable-toolbar')
                    .first();

                if (toolbar.length) {
                    table.buttons().container().appendTo(toolbar);
                } else {
                    table.buttons().container().appendTo($(this).closest('.dataTables_wrapper').find(
                        '.col-md-6:eq(0)'));
                }

                if (enableScrollX) {
                    table.on('draw.dt column-sizing.dt', syncTableLayout);
                    $(table.table().container()).find('.dataTables_scrollBody').on('scroll.adminHeadSync',
                        function() {
                            $(table.table().container()).find('.dataTables_scrollHead').scrollLeft(this
                                .scrollLeft);
                        });
                }

                setTimeout(syncTableLayout, 0);
                setTimeout(syncTableLayout, 120);
            });

            $(window).on('load.adminDatatables', function() {
                $.fn.dataTable.tables({
                    visible: true,
                    api: true
                }).iterator('table', function(context) {
                    syncScrollableDataTableColumns(new $.fn.dataTable.Api(context));
                });
            });

            $(window).on('resize.adminDatatables', function() {
                $.fn.dataTable.tables({
                    visible: true,
                    api: true
                }).iterator('table', function(context) {
                    syncScrollableDataTableColumns(new $.fn.dataTable.Api(context));
                });
            });
        });
    </script>
    <!--Datatable-->

</body>

</html>
