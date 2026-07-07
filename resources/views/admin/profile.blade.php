@extends('admin.layout.app')

@section('content')
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    @php
        $adminImageUrl = !empty($admin->image)
            ? \App\Support\ProjectAsset::url('storage/admin/' . $admin->image)
            : \App\Support\ProjectAsset::url('no_image.jpg');
        $selectedTheme = old('theme_preference', $admin->theme_preference ?: 'light');
        $themeOptions = array_values(array_unique(array_merge($themeOptions ?? [], [
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
        ])));
        $selectedCardStyle = old('card_style', $admin->card_style ?: 'rounded');
        $selectedTableDensity = old('table_density', $admin->table_density ?: 'comfortable');
        $selectedSidebarCollapsed = old('sidebar_collapsed', $admin->sidebar_collapsed ? '1' : '0') === '1';
        $selectedPrimaryColor = old('theme_primary_color', $admin->theme_primary_color ?: $themeColorDefaults['theme_primary_color']);
        $selectedBackgroundColor = old('theme_background_color', $admin->theme_background_color ?: $themeColorDefaults['theme_background_color']);
        $selectedSurfaceColor = old('theme_surface_color', $admin->theme_surface_color ?: $themeColorDefaults['theme_surface_color']);
        $selectedSidebarBackgroundColor = old('theme_sidebar_background_color', $admin->theme_sidebar_background_color ?: $themeColorDefaults['theme_sidebar_background_color']);
        $selectedTextColor = old('theme_text_color', $admin->theme_text_color ?: $themeColorDefaults['theme_text_color']);
        $selectedMutedTextColor = old('theme_muted_text_color', $admin->theme_muted_text_color ?: $themeColorDefaults['theme_muted_text_color']);
        $selectedBorderColor = old('theme_border_color', $admin->theme_border_color ?: $themeColorDefaults['theme_border_color']);
        $themeLabels = [
            'blue-theme' => 'Blue Theme',
            'light' => 'Warm Light',
            'dark' => 'Dark Theme',
            'semi-dark' => 'Semi Dark',
            'bodered-theme' => 'Warm Bordered',
            'emerald-theme' => 'Emerald Fresh',
            'violet-theme' => 'Royal Violet',
            'sunset-theme' => 'Sunset Rose',
            'copper-theme' => 'Copper Clay',
            'ocean-theme' => 'Ocean Mist',
            'mulberry-theme' => 'Mulberry Ink',
        ];
        $themeDescriptions = [
            'blue-theme' => 'Gradient blue surfaces with the current brand feel.',
            'light' => 'White interface with soothing red and gold accents by default.',
            'dark' => 'Full dark interface for low-light usage.',
            'semi-dark' => 'Dark navigation with lighter content area.',
            'bodered-theme' => 'Warm white interface with stronger bordered containers.',
            'emerald-theme' => 'Deep green navigation with clean mint workspaces.',
            'violet-theme' => 'Purple navigation with pink highlights and airy surfaces.',
            'sunset-theme' => 'Rose navigation with amber accents and warm light pages.',
            'copper-theme' => 'Burnished copper accents with a soft clay workspace.',
            'ocean-theme' => 'Deep teal navigation with crisp coastal blue highlights.',
            'mulberry-theme' => 'Rich mulberry navigation with elegant blush-toned surfaces.',
        ];
        $themePreviewClasses = [
            'blue-theme' => 'theme-preview-blue',
            'light' => 'theme-preview-light',
            'dark' => 'theme-preview-dark',
            'semi-dark' => 'theme-preview-semi-dark',
            'bodered-theme' => 'theme-preview-bordered',
            'emerald-theme' => 'theme-preview-emerald',
            'violet-theme' => 'theme-preview-violet',
            'sunset-theme' => 'theme-preview-sunset',
            'copper-theme' => 'theme-preview-copper',
            'ocean-theme' => 'theme-preview-ocean',
            'mulberry-theme' => 'theme-preview-mulberry',
        ];
        $fullThemePaletteMap = [
            'blue-theme' => [
                'theme_primary_color' => '#0D6EFD',
                'theme_highlight_color' => '#F0C36A',
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
    @endphp

    <style>
        .theme-option-card {
            border: 1px solid rgba(23, 32, 51, 0.12);
            border-radius: 1rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            height: 100%;
        }

        .theme-option-card:hover {
            border-color: rgba(var(--admin-primary-color-rgb), 0.45);
            box-shadow: 0 12px 30px rgba(var(--admin-primary-color-rgb), 0.08);
        }

        .theme-option-input:checked+.theme-option-card {
            border-color: var(--admin-primary-color);
            box-shadow: 0 14px 34px rgba(var(--admin-primary-color-rgb), 0.16);
        }

        .theme-preview-chip {
            display: inline-flex;
            width: 100%;
            min-height: 92px;
            border-radius: 0.875rem;
            overflow: hidden;
            border: 1px solid rgba(23, 32, 51, 0.08);
            margin-bottom: 0.75rem;
        }

        .theme-preview-chip span {
            flex: 1 1 0;
            display: block;
        }

        .theme-preview-blue span:nth-child(1) {
            background: linear-gradient(180deg, #0d6efd 0%, #6ea8fe 100%);
        }

        .theme-preview-blue span:nth-child(2) {
            background: #ffffff;
        }

        .theme-preview-blue span:nth-child(3) {
            background: #eef4ff;
        }

        .theme-preview-light span:nth-child(1) {
            background: linear-gradient(180deg, #a63d2f 0%, #c8a24a 100%);
        }

        .theme-preview-light span:nth-child(2) {
            background: #ffffff;
        }

        .theme-preview-light span:nth-child(3) {
            background: #fcf7f2;
        }

        .theme-preview-dark span:nth-child(1) {
            background: #111827;
        }

        .theme-preview-dark span:nth-child(2) {
            background: #1f2937;
        }

        .theme-preview-dark span:nth-child(3) {
            background: #374151;
        }

        .theme-preview-semi-dark span:nth-child(1) {
            background: #111827;
        }

        .theme-preview-semi-dark span:nth-child(2) {
            background: #ffffff;
        }

        .theme-preview-semi-dark span:nth-child(3) {
            background: #eff6ff;
        }

        .theme-preview-bordered span:nth-child(1) {
            background: linear-gradient(180deg, #a63d2f 0%, #c8a24a 100%);
            border-right: 1px solid rgba(23, 32, 51, 0.1);
        }

        .theme-preview-bordered span:nth-child(2) {
            background: #fff9f5;
            border-right: 1px solid rgba(23, 32, 51, 0.1);
        }

        .theme-preview-bordered span:nth-child(3) {
            background: #ffffff;
        }

        .theme-preview-emerald span:nth-child(1) {
            background: linear-gradient(180deg, #052e2b 0%, #047857 100%);
        }

        .theme-preview-emerald span:nth-child(2) {
            background: #ffffff;
        }

        .theme-preview-emerald span:nth-child(3) {
            background: #ecfdf5;
        }

        .theme-preview-violet span:nth-child(1) {
            background: linear-gradient(180deg, #1e1b4b 0%, #7c3aed 100%);
        }

        .theme-preview-violet span:nth-child(2) {
            background: #ffffff;
        }

        .theme-preview-violet span:nth-child(3) {
            background: #fdf2f8;
        }

        .theme-preview-sunset span:nth-child(1) {
            background: linear-gradient(180deg, #3b1827 0%, #e11d48 58%, #f59e0b 100%);
        }

        .theme-preview-sunset span:nth-child(2) {
            background: #ffffff;
        }

        .theme-preview-sunset span:nth-child(3) {
            background: #fff1f2;
        }

        .theme-preview-copper span:nth-child(1) {
            background: linear-gradient(180deg, #3a2315 0%, #b45309 62%, #e9a23b 100%);
        }

        .theme-preview-copper span:nth-child(2) {
            background: #ffffff;
        }

        .theme-preview-copper span:nth-child(3) {
            background: #fff8f1;
        }

        .theme-preview-ocean span:nth-child(1) {
            background: linear-gradient(180deg, #0b2f3a 0%, #0f766e 58%, #38bdf8 100%);
        }

        .theme-preview-ocean span:nth-child(2) {
            background: #ffffff;
        }

        .theme-preview-ocean span:nth-child(3) {
            background: #f1fafc;
        }

        .theme-preview-mulberry span:nth-child(1) {
            background: linear-gradient(180deg, #341326 0%, #9d174d 62%, #f59eb2 100%);
        }

        .theme-preview-mulberry span:nth-child(2) {
            background: #ffffff;
        }

        .theme-preview-mulberry span:nth-child(3) {
            background: #fff6fa;
        }

        .settings-helper {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .theme-color-control {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.85rem 1rem;
            border: 1px solid rgba(23, 32, 51, 0.08);
            border-radius: 0.9rem;
            background: rgba(255, 255, 255, 0.72);
        }

        .theme-color-control input[type="color"] {
            width: 52px;
            min-width: 52px;
            height: 52px;
            padding: 0;
            border: 0;
            background: transparent;
            cursor: pointer;
        }

        .theme-color-control code {
            font-size: 0.82rem;
        }
    </style>

    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Profile</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('flash_success'))
            <div class="alert alert-success border-0">{{ session('flash_success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger border-0">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="position-relative mb-5">
                    <img src="{{ \App\Support\ProjectAsset::url('admin/assets/images/gallery/profile-cover-min.png') }}" class="img-fluid rounded-4 shadow"
                        alt="">
                    <div class="profile-avatar position-absolute top-100 start-50 translate-middle">
                        <img src="{{ $adminImageUrl }}"
                            class="img-fluid rounded-circle p-1 bg-grd-danger shadow" width="170" height="170"
                            alt="" data-admin-image-fallback data-admin-image-alt="No Image">
                    </div>
                </div>

                <div class="profile-info pt-5 d-flex align-items-center justify-content-center">
                    <div class="text-center">
                        <h3>{{ $admin->name }}</h3>
                        <p class="mb-0">{{ $admin->email }}</p>
                        <p class="mb-0">{{ $admin->position }}</p>
                        <p class="mb-0">{{ $admin->phone }}</p>
                        <p class="mb-0">{{ $admin->address }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-5">
                <form method="post" action="{{ route('admin-profile-details-update') }}" enctype="multipart/form-data">
                    @csrf
                <div class="card rounded-4 border-top border-4 border-primary border-gradient-1 h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div>
                                <h5 class="mb-1 fw-bold">Profile Details</h5>
                                <p class="mb-0 settings-helper">Update your account information and profile photo.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-12">
                                <label for="input1" class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" id="input1"
                                    placeholder="Enter your name" value="{{ old('name', $admin->name) }}">
                            </div>
                            <div class="col-md-12">
                                <label for="input2" class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="input2"
                                    placeholder="Enter your email" value="{{ old('email', $admin->email) }}">
                            </div>
                            <div class="col-md-12">
                                <label for="input3" class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" id="input3"
                                    placeholder="Enter your mobile number" value="{{ old('phone', $admin->phone) }}">
                            </div>
                            <div class="col-md-12">
                                <label for="admin_position" class="form-label">Position</label>
                                <input type="text" class="form-control" name="position" id="admin_position"
                                    placeholder="Enter position" value="{{ old('position', $admin->position) }}">
                            </div>
                            <div class="col-md-12">
                                <label for="image" class="form-label">Image</label>
                                <input type="file" class="form-control" name="image" id="image"
                                    placeholder="Upload your image">
                            </div>
                            <div class="col-md-12">
                                <img id="showImage"
                                    src="{{ $adminImageUrl }}"
                                    class="rounded-circle p-1 shadow mb-3" width="90" height="90" alt=""
                                    data-admin-image-fallback data-admin-image-alt="No Image">
                            </div>
                            <div class="col-md-12">
                                <label for="input11" class="form-label">Address</label>
                                <textarea class="form-control" id="input11" name="address" placeholder="Enter your address" rows="4"
                                    cols="4">{{ old('address', $admin->address) }}</textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <div class="d-md-flex d-grid align-items-center gap-3">
                                <button type="submit" class="btn btn-grd-primary px-4">Save Profile Details</button>
                            </div>
                        </div>
                    </div>
                </div>
                </form>
            </div>

            <div class="col-12 col-xl-7">
                <form method="post" action="{{ route('admin-profile-theme-update') }}" id="admin-theme-settings-form">
                    @csrf
                <div class="card rounded-4 border-top border-4 border-success border-gradient-2 h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div>
                                <h5 class="mb-1 fw-bold">Theme & Customization</h5>
                                <p class="mb-0 settings-helper">Choose the default look applied every time this admin logs in.</p>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            @foreach ($themeOptions as $themeOption)
                                <div class="col-md-6 col-xl-4">
                                    <label class="w-100">
                                        <input type="radio" class="btn-check theme-option-input" name="theme_preference"
                                            value="{{ $themeOption }}" autocomplete="off"
                                            @checked($selectedTheme === $themeOption)>
                                        <div class="theme-option-card">
                                            <div class="theme-preview-chip {{ $themePreviewClasses[$themeOption] ?? 'theme-preview-light' }}">
                                                <span></span>
                                                <span></span>
                                                <span></span>
                                            </div>
                                            <strong>{{ $themeLabels[$themeOption] ?? $themeOption }}</strong>
                                            <div class="settings-helper">
                                                {{ $themeDescriptions[$themeOption] ?? 'Custom admin theme.' }}
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label for="card_style" class="form-label">Card Shape</label>
                                <select class="form-select theme-preview-trigger" name="card_style" id="card_style">
                                    @foreach ($cardStyleOptions as $option)
                                        <option value="{{ $option }}" @selected($selectedCardStyle === $option)>
                                            {{ ucfirst($option) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="settings-helper mt-2">Rounded keeps the current soft style. Sharp uses tighter corners.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="table_density" class="form-label">Table Density</label>
                                <select class="form-select theme-preview-trigger" name="table_density" id="table_density">
                                    @foreach ($tableDensityOptions as $option)
                                        <option value="{{ $option }}" @selected($selectedTableDensity === $option)>
                                            {{ ucfirst($option) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="settings-helper mt-2">Compact reduces table and card spacing across the admin panel.</div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input theme-preview-trigger" type="checkbox" role="switch"
                                        id="sidebar_collapsed" name="sidebar_collapsed" value="1"
                                        @checked($selectedSidebarCollapsed)>
                                    <label class="form-check-label" for="sidebar_collapsed">Start with collapsed sidebar</label>
                                </div>
                                <div class="settings-helper mt-2">Useful when you want a wider workspace by default.</div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
                                <div>
                                    <h6 class="mb-1 fw-bold">Theme Colors</h6>
                                    <p class="mb-0 settings-helper">These colors are applied across cards, forms, tables, text, and borders.</p>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="reset-theme-colors">
                                    Reset Colors To Theme Defaults
                                </button>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_primary_color">Primary Color</label>
                                    <div class="theme-color-control">
                                        <input type="color" class="theme-preview-trigger color-value-sync" id="theme_primary_color"
                                            name="theme_primary_color" value="{{ $selectedPrimaryColor }}">
                                        <div>
                                            <div class="fw-semibold">Buttons and active links</div>
                                            <code data-color-output="theme_primary_color">{{ $selectedPrimaryColor }}</code>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_background_color">Page Background</label>
                                    <div class="theme-color-control">
                                        <input type="color" class="theme-preview-trigger color-value-sync" id="theme_background_color"
                                            name="theme_background_color" value="{{ $selectedBackgroundColor }}">
                                        <div>
                                            <div class="fw-semibold">Outer page canvas</div>
                                            <code data-color-output="theme_background_color">{{ $selectedBackgroundColor }}</code>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_surface_color">Surface Color</label>
                                    <div class="theme-color-control">
                                        <input type="color" class="theme-preview-trigger color-value-sync" id="theme_surface_color"
                                            name="theme_surface_color" value="{{ $selectedSurfaceColor }}">
                                        <div>
                                            <div class="fw-semibold">Cards, forms, dropdowns</div>
                                            <code data-color-output="theme_surface_color">{{ $selectedSurfaceColor }}</code>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_sidebar_background_color">Sidebar Background</label>
                                    <div class="theme-color-control">
                                        <input type="color" class="theme-preview-trigger color-value-sync" id="theme_sidebar_background_color"
                                            name="theme_sidebar_background_color" value="{{ $selectedSidebarBackgroundColor }}">
                                        <div>
                                            <div class="fw-semibold">Sidebar wrapper and navigation</div>
                                            <code data-color-output="theme_sidebar_background_color">{{ $selectedSidebarBackgroundColor }}</code>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_text_color">Text Color</label>
                                    <div class="theme-color-control">
                                        <input type="color" class="theme-preview-trigger color-value-sync" id="theme_text_color"
                                            name="theme_text_color" value="{{ $selectedTextColor }}">
                                        <div>
                                            <div class="fw-semibold">Main text and headings</div>
                                            <code data-color-output="theme_text_color">{{ $selectedTextColor }}</code>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_muted_text_color">Muted Text Color</label>
                                    <div class="theme-color-control">
                                        <input type="color" class="theme-preview-trigger color-value-sync" id="theme_muted_text_color"
                                            name="theme_muted_text_color" value="{{ $selectedMutedTextColor }}">
                                        <div>
                                            <div class="fw-semibold">Descriptions and helper text</div>
                                            <code data-color-output="theme_muted_text_color">{{ $selectedMutedTextColor }}</code>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_border_color">Border Color</label>
                                    <div class="theme-color-control">
                                        <input type="color" class="theme-preview-trigger color-value-sync" id="theme_border_color"
                                            name="theme_border_color" value="{{ $selectedBorderColor }}">
                                        <div>
                                            <div class="fw-semibold">Inputs, tables, separators</div>
                                            <code data-color-output="theme_border_color">{{ $selectedBorderColor }}</code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <div class="d-md-flex d-grid align-items-center gap-3">
                                <button type="submit" class="btn btn-grd-primary px-4">Save Theme Settings</button>
                            </div>
                        </div>
                    </div>
                </div>
                </form>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        $(document).ready(function() {
            const fullThemePaletteMap = @json($fullThemePaletteMap);

            $('#image').change(function(e) {
                const file = e.target.files[0];

                if (!file) {
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(event) {
                    $('#showImage').attr('src', event.target.result);
                };
                reader.readAsDataURL(file);
            });

            function hexToRgb(hex) {
                const normalized = (hex || '').replace('#', '');

                if (normalized.length !== 6) {
                    return '13, 110, 253';
                }

                return `${parseInt(normalized.slice(0, 2), 16)}, ${parseInt(normalized.slice(2, 4), 16)}, ${parseInt(normalized.slice(4, 6), 16)}`;
            }

            function getAccessibleTextColor(hex) {
                const normalized = (hex || '').replace('#', '');

                if (normalized.length !== 6) {
                    return '#FFF9F4';
                }

                const red = parseInt(normalized.slice(0, 2), 16);
                const green = parseInt(normalized.slice(2, 4), 16);
                const blue = parseInt(normalized.slice(4, 6), 16);
                const brightness = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

                return brightness >= 160 ? '#2D1A14' : '#FFF9F4';
            }

            function relativeLuminance(hex) {
                const normalized = (hex || '').replace('#', '');

                if (normalized.length !== 6) {
                    return 0;
                }

                const channels = [
                    parseInt(normalized.slice(0, 2), 16),
                    parseInt(normalized.slice(2, 4), 16),
                    parseInt(normalized.slice(4, 6), 16)
                ].map((channel) => {
                    const value = channel / 255;
                    return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
                });

                return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
            }

            function contrastRatio(foreground, background) {
                const foregroundLuminance = relativeLuminance(foreground);
                const backgroundLuminance = relativeLuminance(background);
                const lighter = Math.max(foregroundLuminance, backgroundLuminance);
                const darker = Math.min(foregroundLuminance, backgroundLuminance);

                return (lighter + 0.05) / (darker + 0.05);
            }

            function getReadableTextColor(preferred, backgrounds, fallbackDark = '#2D1A14', fallbackLight = '#FFF9F4', minContrast = 4.5) {
                const isPreferredReadable = backgrounds.every((background) => contrastRatio(preferred, background) >= minContrast);

                if (isPreferredReadable) {
                    return preferred;
                }

                return [fallbackDark, fallbackLight]
                    .map((candidate) => ({
                        candidate,
                        score: Math.min(...backgrounds.map((background) => contrastRatio(candidate, background)))
                    }))
                    .sort((left, right) => right.score - left.score)[0].candidate;
            }

            function syncColorLabels() {
                $('.color-value-sync').each(function() {
                    const fieldId = $(this).attr('id');
                    $(`[data-color-output="${fieldId}"]`).text($(this).val().toUpperCase());
                });
            }

            function applyPalette(themeName) {
                const palette = fullThemePaletteMap[themeName] || fullThemePaletteMap['light'];

                Object.entries(palette).forEach(([field, value]) => {
                    const input = $(`#${field}`);

                    if (input.length) {
                        input.val(value);
                    }
                });

                syncColorLabels();
            }

            function applyAdminThemePreview() {
                const selectedTheme = $('input[name="theme_preference"]:checked').val() || 'light';
                const cardStyle = $('#card_style').val() || 'rounded';
                const tableDensity = $('#table_density').val() || 'comfortable';
                const sidebarCollapsed = $('#sidebar_collapsed').is(':checked');
                const themePalette = fullThemePaletteMap[selectedTheme] || fullThemePaletteMap['light'];
                const primaryColor = ($('#theme_primary_color').val() || themePalette.theme_primary_color || '#A63D2F').toUpperCase();
                const accentGoldColor = (themePalette.theme_highlight_color || '#C8A24A').toUpperCase();
                const backgroundColor = ($('#theme_background_color').val() || themePalette.theme_background_color || '#FCF7F2').toUpperCase();
                const surfaceColor = ($('#theme_surface_color').val() || '#FFFFFF').toUpperCase();
                const sidebarBackgroundColor = ($('#theme_sidebar_background_color').val() || themePalette.theme_sidebar_background_color || '#FFF9F3').toUpperCase();
                const textColor = ($('#theme_text_color').val() || themePalette.theme_text_color || '#35231D').toUpperCase();
                const mutedTextColor = ($('#theme_muted_text_color').val() || themePalette.theme_muted_text_color || '#8B6B61').toUpperCase();
                const borderColor = ($('#theme_border_color').val() || themePalette.theme_border_color || '#E9D8CC').toUpperCase();
                const primaryContrastColor = getAccessibleTextColor(primaryColor);
                const readableTextColor = getReadableTextColor(textColor, [backgroundColor, surfaceColor]);
                const readableMutedTextColor = getReadableTextColor(
                    mutedTextColor,
                    [backgroundColor, surfaceColor],
                    readableTextColor,
                    readableTextColor,
                    3.2
                );
                const readableSidebarTextColor = getReadableTextColor(textColor, [sidebarBackgroundColor]);
                const readableSidebarMutedTextColor = getReadableTextColor(
                    mutedTextColor,
                    [sidebarBackgroundColor],
                    readableSidebarTextColor,
                    readableSidebarTextColor,
                    3.2
                );

                $('html').attr('data-bs-theme', selectedTheme);
                $('body')
                    .toggleClass('theme-card-sharp', cardStyle === 'sharp')
                    .toggleClass('theme-card-rounded', cardStyle !== 'sharp')
                    .toggleClass('theme-density-compact', tableDensity === 'compact')
                    .toggleClass('theme-density-comfortable', tableDensity !== 'compact')
                    .toggleClass('toggled', sidebarCollapsed)
                    .css({
                        '--admin-primary-color': primaryColor,
                        '--admin-highlight-color': accentGoldColor,
                        '--admin-primary-contrast-color': primaryContrastColor,
                        '--admin-background-color': backgroundColor,
                        '--admin-surface-color': surfaceColor,
                        '--admin-sidebar-background-color': sidebarBackgroundColor,
                        '--admin-text-color': readableTextColor,
                        '--admin-muted-text-color': readableMutedTextColor,
                        '--admin-sidebar-text-color': readableSidebarTextColor,
                        '--admin-sidebar-muted-text-color': readableSidebarMutedTextColor,
                        '--admin-border-color': borderColor,
                        '--admin-primary-color-rgb': hexToRgb(primaryColor),
                        '--admin-text-color-rgb': hexToRgb(readableTextColor),
                        '--bs-primary': primaryColor,
                        '--bs-primary-rgb': hexToRgb(primaryColor),
                        '--bs-body-bg': backgroundColor,
                        '--bs-body-bg-rgb': hexToRgb(backgroundColor),
                        '--bs-body-color': readableTextColor,
                        '--bs-body-color-rgb': hexToRgb(readableTextColor),
                        '--bs-border-color': borderColor,
                        '--bs-border-color-translucent': borderColor
                    });

                syncColorLabels();
            }

            $('input[name="theme_preference"]').on('change', function() {
                applyPalette($(this).val());
                applyAdminThemePreview();
            });

            $('.theme-preview-trigger').on('change input', applyAdminThemePreview);
            $('#reset-theme-colors').on('click', function() {
                const selectedTheme = $('input[name="theme_preference"]:checked').val() || 'light';
                applyPalette(selectedTheme);
                applyAdminThemePreview();
            });

            syncColorLabels();
            applyAdminThemePreview();
        });
    </script>
@endsection
