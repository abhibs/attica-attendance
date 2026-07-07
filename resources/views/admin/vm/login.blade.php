<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VM Login</title>
    <link href="{{ \App\Support\ProjectAsset::url('public/admin/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: #f5f7fb;
            color: #172033;
        }

        .vm-login-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .vm-login-card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border: 1px solid rgba(23, 32, 51, 0.08);
            border-radius: 18px;
            box-shadow: 0 22px 60px rgba(23, 32, 51, 0.08);
            padding: 28px;
        }

        .vm-logo {
            width: 54px;
            height: 54px;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <main class="vm-login-shell">
        <section class="vm-login-card">
            <div class="d-flex align-items-center gap-3 mb-4">
                <img src="{{ \App\Support\ProjectAsset::url('public/admin/assets/images/attica_logo.png') }}" class="vm-logo" alt="Attica Pagar logo">
                <div>
                    <h4 class="mb-0">VM Login</h4>
                    <p class="mb-0 text-muted">Branch attendance access</p>
                </div>
            </div>

            @if (session('status'))
                <div class="alert alert-warning">{{ session('status') }}</div>
            @endif

            <form method="post" action="{{ route('admin-vm-login-post') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username', $defaultUsername) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Assigned Branches</label>
                    <textarea name="assigned_branches" class="form-control" rows="3" placeholder="AGPL030, AGPL012, AGPL001" required>{{ old('assigned_branches') }}</textarea>
                    <div class="form-text">Branch IDs are stored in session and are not passed through the report URL.</div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </section>
    </main>
</body>
</html>
