<!doctype html>
@php
    $themePreference = $themePreference ?? 'light';
    $cardStyle = $cardStyle ?? 'rounded';
    $resolvedColors = $resolvedColors ?? [
        'theme_primary_color' => '#A63D2F',
        'theme_highlight_color' => '#C8A24A',
        'theme_background_color' => '#FCF7F2',
        'theme_surface_color' => '#FFFFFF',
        'theme_sidebar_background_color' => '#FFF9F3',
        'theme_text_color' => '#35231D',
        'theme_muted_text_color' => '#8B6B61',
        'theme_border_color' => '#E9D8CC',
    ];
    $resolvedTextColor = $resolvedTextColor ?? '#35231D';
    $resolvedMutedTextColor = $resolvedMutedTextColor ?? '#8B6B61';
    $loginFormTextColor = $loginFormTextColor ?? $resolvedTextColor;
    $loginFormMutedTextColor = $loginFormMutedTextColor ?? $resolvedMutedTextColor;
    $themePrimaryColorRgb = $themePrimaryColorRgb ?? '166, 61, 47';
    $themePrimaryContrastColor = $themePrimaryContrastColor ?? '#FFF9F4';
    $resolvedTextColorRgb = $resolvedTextColorRgb ?? '53, 35, 29';
    $loginFormTextColorRgb = $loginFormTextColorRgb ?? $resolvedTextColorRgb;
    $themeBackgroundColorRgb = $themeBackgroundColorRgb ?? '252, 247, 242';
@endphp
<html lang="en" data-bs-theme="{{ $themePreference }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attica Pagar Admin Login</title>
    <!--favicon-->
    <link rel="icon" href="{{ asset('admin/assets/images/attica_favicon.png') }}?v=1" type="image/png">
    <!-- loader-->
    <link href="{{ asset('admin/assets/css/pace.min.css') }}" rel="stylesheet">
    <script src="{{ asset('admin/assets/js/pace.min.js') }}"></script>

    <!--plugins-->
    <link href="{{ asset('admin/assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin/assets/plugins/metismenu/metisMenu.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin/assets/plugins/metismenu/mm-vertical.css') }}">
    <!--bootstrap css-->
    <link href="{{ asset('admin/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons+Outlined" rel="stylesheet">
    <!--main css-->
    <link href="{{ asset('admin/assets/css/bootstrap-extended.css') }}" rel="stylesheet">
    <link href="{{ asset('admin/sass/main.css') }}" rel="stylesheet">
    <link href="{{ asset('admin/sass/dark-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('admin/sass/blue-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('admin/sass/semi-dark.css') }}" rel="stylesheet">
    <link href="{{ asset('admin/sass/bordered-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('admin/sass/responsive.css') }}" rel="stylesheet">
    <style>
        body.admin-login {
            position: relative;
            width: 100vw;
            height: 100vh;
            min-height: 100vh;
            overflow: hidden;
            background:
                linear-gradient(118deg, #0c111d 0%, #151924 23%, var(--admin-primary-color) 56%, #65a8ff 100%);
            color: var(--admin-login-form-text-color);
        }

        body.admin-login .auth-basic-wrapper {
            position: relative;
            isolation: isolate;
            width: 100%;
            height: 100vh;
            min-height: 0;
            padding: clamp(0.75rem, 3vw, 2rem);
        }

        body.admin-login .auth-basic-wrapper::before,
        body.admin-login .auth-basic-wrapper::after {
            content: "";
            position: fixed;
            pointer-events: none;
            z-index: -1;
        }

        body.admin-login .auth-basic-wrapper::before {
            inset: -12% -8% auto auto;
            width: min(58vw, 46rem);
            height: min(58vw, 46rem);
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.32), rgba(255, 255, 255, 0.04)),
                linear-gradient(45deg, rgba(var(--admin-primary-color-rgb), 0.40), rgba(200, 162, 74, 0.30));
            clip-path: polygon(18% 0, 100% 12%, 84% 82%, 34% 100%, 0 48%);
            transform: rotate(-10deg);
        }

        body.admin-login .auth-basic-wrapper::after {
            left: -14%;
            bottom: -18%;
            width: min(54vw, 42rem);
            height: min(44vw, 34rem);
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.24), rgba(255, 255, 255, 0.02)),
                linear-gradient(110deg, rgba(18, 22, 31, 0.45), rgba(var(--admin-primary-color-rgb), 0.35));
            clip-path: polygon(0 18%, 72% 0, 100% 68%, 30% 100%);
            transform: rotate(8deg);
        }

        body.admin-login .auth-basic-wrapper>.container-fluid {
            position: relative;
            z-index: 1;
        }

        body.admin-login .auth-login-frame {
            width: min(100%, 980px);
            margin: 0 auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: clamp(1rem, 2.2vw, 1.5rem);
            align-items: stretch;
        }

        body.admin-login .auth-panel {
            position: relative;
            overflow: hidden;
            width: 100%;
            min-height: clamp(36rem, 82vh, 46rem);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.01)),
                var(--admin-surface-color);
            border: 1px solid var(--admin-border-color);
            box-shadow: 0 1rem 3rem rgba(var(--admin-login-form-text-color-rgb), 0.08);
            border-radius: 1.35rem;
        }

        body.admin-login .auth-brand-panel {
            color: #fff;
            padding: clamp(2rem, 4vw, 3.5rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.24), transparent 38%),
                linear-gradient(135deg, rgba(var(--admin-primary-color-rgb), 0.34), rgba(18, 28, 45, 0.82));
        }

        body.admin-login .auth-brand-panel::before,
        body.admin-login .auth-brand-panel::after {
            content: "";
            position: absolute;
            pointer-events: none;
        }

        body.admin-login .auth-brand-panel::before {
            right: -5rem;
            top: -4rem;
            width: 16rem;
            height: 16rem;
            border: 1px solid rgba(255, 255, 255, 0.20);
            transform: rotate(26deg);
        }

        body.admin-login .auth-brand-panel::after {
            left: -5rem;
            bottom: -6rem;
            width: 18rem;
            height: 18rem;
            background: linear-gradient(135deg, rgba(101, 168, 255, 0.26), rgba(200, 162, 74, 0.22));
            clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%);
            transform: rotate(18deg);
        }

        body.admin-login .auth-brand-content {
            position: relative;
            z-index: 1;
        }

        body.admin-login .auth-brand-panel h1 {
            color: #fff;
            font-size: clamp(2rem, 4vw, 3.9rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
        }

        body.admin-login .auth-brand-panel p,
        body.admin-login .auth-brand-panel .text-secondary {
            color: rgba(255, 255, 255, 0.82) !important;
        }

        body.admin-login .auth-badge,
        body.admin-login .auth-feature-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            width: fit-content;
            color: #fff;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 999px;
            padding: 0.45rem 0.8rem;
            font-size: 0.78rem;
            font-weight: 700;
        }

        body.admin-login .auth-admin-note {
            margin-top: auto;
            padding: 1.1rem;
            background: rgba(255, 255, 255, 0.11);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 1.2rem;
            color: #fff;
        }

        body.admin-login .auth-form-panel {
            background:
                linear-gradient(180deg, rgba(var(--admin-primary-color-rgb), 0.08), rgba(255, 255, 255, 0.01)),
                var(--admin-surface-color);
        }

        body.admin-login .auth-form-panel .card-body {
            min-height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        body.admin-login .auth-form-panel::before {
            content: "";
            position: absolute;
            inset: 0 auto auto 0;
            width: 100%;
            height: 0.4rem;
            background: linear-gradient(135deg, var(--admin-primary-color) 0%, var(--admin-highlight-color) 100%);
        }

        body.admin-login .auth-form-panel::after {
            content: "";
            position: absolute;
            inset: 0 0 auto auto;
            width: 9rem;
            height: 9rem;
            background: linear-gradient(135deg, rgba(var(--admin-primary-color-rgb), 0.18), rgba(200, 162, 74, 0.16));
            clip-path: polygon(100% 0, 100% 100%, 0 0);
            pointer-events: none;
        }

        body.admin-login .auth-form-panel .card-body {
            position: relative;
            z-index: 1;
        }

        body.admin-login .auth-form-panel .text-secondary,
        body.admin-login .text-secondary,
        body.admin-login .form-text {
            color: var(--admin-login-form-muted-text-color) !important;
        }

        body.admin-login .auth-form-panel .form-label,
        body.admin-login .auth-form-panel h1,
        body.admin-login .auth-form-panel h2,
        body.admin-login .auth-form-panel h3,
        body.admin-login .auth-form-panel h4,
        body.admin-login .auth-form-panel p {
            color: var(--admin-login-form-text-color);
        }

        body.admin-login .form-control,
        body.admin-login .input-group-text {
            background-color: var(--admin-surface-color);
            color: var(--admin-login-form-text-color);
            border-color: var(--admin-border-color);
        }

        body.admin-login .input-group-text {
            cursor: pointer;
            color: var(--admin-primary-color) !important;
        }

        body.admin-login .input-group-text i {
            color: currentColor;
            opacity: 1;
        }

        body.admin-login .form-control::placeholder {
            color: var(--admin-login-form-muted-text-color);
            opacity: 1;
        }

        body.admin-login .form-control:focus {
            color: var(--admin-login-form-text-color);
            background-color: var(--admin-surface-color);
            border-color: rgba(var(--admin-primary-color-rgb), 0.42);
            box-shadow: 0 0 0 0.25rem rgba(var(--admin-primary-color-rgb), 0.12);
        }

        body.admin-login .btn-login {
            background: linear-gradient(135deg, var(--admin-primary-color) 0%, var(--admin-highlight-color) 100%);
            border: none;
            color: var(--admin-primary-contrast-color);
            box-shadow: 0 0.75rem 1.6rem rgba(var(--admin-primary-color-rgb), 0.22);
        }

        body.admin-login .btn-login:hover,
        body.admin-login .btn-login:focus {
            color: var(--admin-primary-contrast-color);
            transform: translateY(-1px);
        }

        body.admin-login .auth-logo-wrap {
            width: 88px;
            height: 88px;
            margin: 0 auto 1rem;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--admin-primary-color) 0%, var(--admin-highlight-color) 100%);
            box-shadow: 0 0.75rem 1.7rem rgba(var(--admin-primary-color-rgb), 0.22);
        }

        body.admin-login .auth-logo-wrap img {
            width: 62px;
            height: 62px;
            object-fit: contain;
            border-radius: 999px;
            background: #fff;
            padding: 6px;
        }

        body.admin-login.theme-card-sharp .card,
        body.admin-login.theme-card-sharp .btn,
        body.admin-login.theme-card-sharp .form-control,
        body.admin-login.theme-card-sharp .input-group-text {
            border-radius: 0.65rem !important;
        }

        body.admin-login .auth-login-icon {
            width: 2.6rem;
            height: 2.6rem;
            flex: 0 0 2.6rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 1rem;
            color: var(--admin-primary-color);
            background: rgba(var(--admin-primary-color-rgb), 0.10);
            font-size: 1.25rem;
        }

        body.admin-login .auth-brand-logo {
            width: 7.5rem;
            height: 7.5rem;
            padding: 0.8rem;
            border-radius: 1.4rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.34), rgba(200, 162, 74, 0.88));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.20);
        }

        body.admin-login .auth-brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 999px;
            background: #fff;
            padding: 0.45rem;
        }

        @media (max-width: 991.98px) {
            body.admin-login {
                height: auto;
                overflow: auto;
            }

            body.admin-login .auth-basic-wrapper {
                height: auto;
                min-height: 100vh;
            }

            body.admin-login .auth-login-frame {
                grid-template-columns: 1fr;
            }

            body.admin-login .auth-panel {
                min-height: auto;
            }

            body.admin-login .auth-brand-panel {
                padding: 2rem;
            }
        }

        @media (max-height: 640px) {
            body.admin-login .auth-logo-wrap {
                width: 68px;
                height: 68px;
                border-radius: 20px;
                margin-bottom: 0.75rem;
            }

            body.admin-login .auth-logo-wrap img {
                width: 48px;
                height: 48px;
            }

            body.admin-login .card-body {
                padding: 1.35rem !important;
            }
        }
    </style>

</head>

<body class="admin-login {{ $cardStyle === 'sharp' ? 'theme-card-sharp' : 'theme-card-rounded' }}"
    style="
        --admin-primary-color: {{ $resolvedColors['theme_primary_color'] }};
        --admin-primary-color-rgb: {{ $themePrimaryColorRgb }};
        --admin-primary-contrast-color: {{ $themePrimaryContrastColor }};
        --admin-highlight-color: {{ $resolvedColors['theme_highlight_color'] }};
        --admin-background-color: {{ $resolvedColors['theme_background_color'] }};
        --admin-surface-color: {{ $resolvedColors['theme_surface_color'] }};
        --admin-sidebar-background-color: {{ $resolvedColors['theme_sidebar_background_color'] }};
        --admin-text-color: {{ $resolvedTextColor }};
        --admin-text-color-rgb: {{ $resolvedTextColorRgb }};
        --admin-muted-text-color: {{ $resolvedMutedTextColor }};
        --admin-login-form-text-color: {{ $loginFormTextColor }};
        --admin-login-form-text-color-rgb: {{ $loginFormTextColorRgb }};
        --admin-login-form-muted-text-color: {{ $loginFormMutedTextColor }};
        --admin-border-color: {{ $resolvedColors['theme_border_color'] }};
        --bs-body-bg: {{ $resolvedColors['theme_background_color'] }};
        --bs-body-bg-rgb: {{ $themeBackgroundColorRgb }};
        --bs-body-color: {{ $resolvedTextColor }};
        --bs-body-color-rgb: {{ $resolvedTextColorRgb }};
        --bs-border-color: {{ $resolvedColors['theme_border_color'] }};
        --bs-border-color-translucent: {{ $resolvedColors['theme_border_color'] }};
        --bs-primary: {{ $resolvedColors['theme_primary_color'] }};
        --bs-primary-rgb: {{ $themePrimaryColorRgb }};
    ">

    <!--authentication-->
    <div class="auth-basic-wrapper d-flex align-items-center justify-content-center">
        <div class="container-fluid">
            <div class="auth-login-frame">
                <section class="auth-panel auth-brand-panel">
                    <div class="auth-brand-content h-100 d-flex flex-column">
                        <div>
                            <div class="auth-brand-logo mb-4">
                                <img src="{{ asset('admin/assets/images/logo-icon.png') }}" alt="Attica Pagar logo">
                            </div>

                            <div class="auth-badge mb-4">
                                <i class="bi bi-shield-lock-fill"></i>
                                Admin workspace
                            </div>

                            <h1 class="fw-bold mb-3">Attica Pagar Admin Login</h1>
                            <p class="fs-6 mb-4">
                                Review attendance, branches, payroll actions, leave requests, and employee records from
                                one focused dashboard.
                            </p>

                            <div class="d-flex flex-wrap gap-2 mb-4">
                                <span class="auth-feature-pill"><i class="bi bi-clock-history"></i> Daily
                                    attendance</span>
                                <span class="auth-feature-pill"><i class="bi bi-building"></i> Branch control</span>
                                <span class="auth-feature-pill"><i class="bi bi-cash-stack"></i> Salary review</span>
                            </div>
                        </div>

                        <div class="auth-admin-note d-flex gap-3 align-items-start mt-4">
                            <span class="auth-login-icon text-white bg-white bg-opacity-10">
                                <i class="bi bi-shield-check"></i>
                            </span>
                            <div>
                                <h6 class="fw-bold mb-1 text-white">Private admin access</h6>
                                <p class="small mb-0">
                                    Sign in with an authorized admin email before reviewing employee data or attendance
                                    reports.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="auth-panel auth-form-panel">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <span class="auth-login-icon">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <div>
                                <p class="text-secondary small fw-semibold text-uppercase mb-1">Secure sign-in</p>
                                <h3 class="fw-bold mb-0">Welcome back</h3>
                            </div>
                        </div>
                        <p class="mb-4 text-secondary">
                            Enter your admin credentials to continue to Attica Pagar.
                        </p>

                        <div class="form-body">
                            <form action="{{ route('admin-login-post') }}" method="POST" class="row g-3">
                                @csrf
                                <div class="col-12">
                                    <label for="inputEmailAddress" class="form-label">Email / Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-envelope-fill"></i>
                                        </span>
                                        <input type="text" name="email"
                                            class="form-control @error('email') is-invalid @enderror"
                                            id="inputEmailAddress" value="{{ old('email') }}"
                                            placeholder="admin@example.com or HRadmin" autocomplete="username"
                                            autofocus>
                                    </div>
                                    @error('email')
                                        <div class="text-danger small mt-2">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label for="inputChoosePassword" class="form-label">Password</label>
                                    <div class="input-group" id="show_hide_password">
                                        <span class="input-group-text">
                                            <i class="bi bi-key-fill"></i>
                                        </span>
                                        <input type="password" name="password"
                                            class="form-control border-end-0 @error('password') is-invalid @enderror"
                                            id="inputChoosePassword" placeholder="Enter password"
                                            autocomplete="current-password">
                                        <a href="javascript:;" class="input-group-text"><i
                                                class="bi bi-eye-slash-fill"></i></a>
                                    </div>
                                    @error('password')
                                        <div class="text-danger small mt-2">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Use the admin email/username and password assigned to you.
                                    </div>
                                </div>

                                <div class="col-12 pt-3">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-login py-3 fw-semibold">
                                            Login
                                            <i class="bi bi-arrow-right ms-1"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <!--authentication-->


    <!--plugins-->
    <script src="{{ asset('admin/assets/js/jquery.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            $("#show_hide_password a").on('click', function(event) {
                event.preventDefault();
                if ($('#show_hide_password input').attr("type") == "text") {
                    $('#show_hide_password input').attr('type', 'password');
                    $('#show_hide_password i').addClass("bi-eye-slash-fill");
                    $('#show_hide_password i').removeClass("bi-eye-fill");
                } else if ($('#show_hide_password input').attr("type") == "password") {
                    $('#show_hide_password input').attr('type', 'text');
                    $('#show_hide_password i').removeClass("bi-eye-slash-fill");
                    $('#show_hide_password i').addClass("bi-eye-fill");
                }
            });
        });
    </script>

</body>

</html>
