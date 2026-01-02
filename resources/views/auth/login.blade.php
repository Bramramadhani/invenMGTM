<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta http-equiv="X-UA-Compatible" content="ie=edge" />
  <title>Login - MMI</title>

  <!-- CSRF untuk proteksi 419 -->
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <meta name="msapplication-TileColor" content="#206bc4" />
  <meta name="theme-color" content="#206bc4" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="HandheldFriendly" content="True" />
  <meta name="MobileOptimized" content="320" />
  <meta name="robots" content="noindex,nofollow,noarchive" />

  @php
    $faviconPng = asset('icon.png') . '?v=' . @filemtime(public_path('icon.png'));
    $faviconIco = asset('favicon.ico') . '?v=' . @filemtime(public_path('favicon.ico'));
    $logoUrl = asset('image/logo-mmi.png') . '?v=' . @filemtime(public_path('image/logo-mmi.png'));
    $illustrationPathPrimary = public_path('image/login-warehouse.png');
    $illustrationPathFallback = public_path('login-warehouse.png');
    if (file_exists($illustrationPathPrimary)) {
      $illustrationUrl = asset('image/login-warehouse.png') . '?v=' . @filemtime($illustrationPathPrimary);
    } elseif (file_exists($illustrationPathFallback)) {
      $illustrationUrl = asset('login-warehouse.png') . '?v=' . @filemtime($illustrationPathFallback);
    } else {
      $illustrationUrl = null;
    }
  @endphp

  <link rel="icon" href="{{ $faviconIco }}" type="image/x-icon" />
  <link rel="icon" href="{{ $faviconPng }}" type="image/png" />
  <link rel="shortcut icon" href="{{ $faviconIco }}" type="image/x-icon" />

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

  <link href="{{ asset('dist/css/tabler.min.css') }}" rel="stylesheet" />
  <style>
    :root {
      --mm-blue: #2f6db0;
      --mm-blue-700: #1f4f8f;
      --mm-ink: #1e2a39;
      --mm-muted: #5b6b7f;
      --mm-border: #d6dee9;
      --mm-card: #ffffff;
    }

    body {
      font-family: "Plus Jakarta Sans", system-ui, -apple-system, sans-serif;
      background:
        radial-gradient(900px 600px at 78% 22%, #eef2f8 0%, rgba(255,255,255,0.9) 55%),
        #ffffff;
      color: var(--mm-ink);
      min-height: 100vh;
    }

    .login-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 48px 24px;
    }

    .login-shell {
      width: min(1200px, 100%);
      display: grid;
      grid-template-columns: 1fr 1.1fr;
      gap: 56px;
      align-items: center;
    }

    .login-left {
      max-width: 520px;
    }

    .brand-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
    }

    .brand-row img {
      width: 40px;
      height: 40px;
      object-fit: contain;
    }

    .brand-name {
      font-weight: 700;
      letter-spacing: 0.08em;
      color: var(--mm-blue-700);
      font-size: 14px;
    }

    .heading {
      font-size: clamp(34px, 4.2vw, 52px);
      line-height: 1.05;
      font-weight: 800;
      color: var(--mm-blue);
      letter-spacing: 0.02em;
      margin-bottom: 14px;
    }

    .heading span {
      display: block;
    }

    .subtext {
      color: var(--mm-muted);
      font-size: 16px;
      margin-bottom: 28px;
    }

    .form-label {
      font-weight: 600;
      color: var(--mm-ink);
      margin-bottom: 6px;
    }

    .form-control {
      border-radius: 12px;
      border: 1px solid var(--mm-border);
      padding: 12px 14px;
      font-size: 15px;
      background: #ffffff;
    }

    .form-control:focus {
      border-color: var(--mm-blue);
      box-shadow: 0 0 0 3px rgba(47, 109, 176, 0.15);
    }

    .password-row {
      position: relative;
    }

    .toggle-pass {
      position: absolute;
      right: 14px;
      top: 36px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border: 0;
      background: transparent;
      color: var(--mm-muted);
      font-size: 13px;
      cursor: pointer;
      padding: 4px;
    }

    .toggle-pass svg {
      width: 16px;
      height: 16px;
    }

    .helper-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 10px;
    }

    .login-submit {
      width: 100%;
      margin-top: 20px;
      padding: 12px 16px;
      border-radius: 14px;
      background: linear-gradient(135deg, #2f6db0, #255a9c);
      border: none;
      color: #fff;
      font-weight: 700;
      font-size: 16px;
      box-shadow: 0 10px 18px rgba(47, 109, 176, 0.22);
    }

    .login-submit:hover {
      filter: brightness(1.03);
    }

    .login-right {
      display: flex;
      justify-content: center;
    }

    .illustration-card {
      width: 100%;
      background: linear-gradient(135deg, #f5f7fb, #eef2f7);
      border-radius: 36px;
      padding: 28px;
      box-shadow: 0 24px 60px rgba(21, 41, 72, 0.12);
      min-height: 480px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .illustration-card img {
      width: 100%;
      height: auto;
      border-radius: 24px;
    }

    .illustration-placeholder {
      width: 100%;
      height: 100%;
      border-radius: 24px;
      background:
        radial-gradient(circle at 20% 20%, rgba(47, 109, 176, 0.12), transparent 40%),
        radial-gradient(circle at 80% 30%, rgba(47, 109, 176, 0.08), transparent 45%),
        linear-gradient(180deg, rgba(255,255,255,0.9), rgba(241,245,251,0.95));
      border: 1px solid #e4eaf3;
      position: relative;
      overflow: hidden;
      min-height: 420px;
    }

    .illustration-placeholder::before,
    .illustration-placeholder::after {
      content: "";
      position: absolute;
      border-radius: 18px;
      background: #ffffff;
      box-shadow: 0 10px 20px rgba(25, 45, 80, 0.08);
    }

    .illustration-placeholder::before {
      width: 180px;
      height: 120px;
      top: 40px;
      left: 40px;
    }

    .illustration-placeholder::after {
      width: 220px;
      height: 160px;
      bottom: 40px;
      right: 40px;
    }

    .alert {
      border-radius: 14px;
    }

    @media (max-width: 991.98px) {
      .login-shell {
        grid-template-columns: 1fr;
      }

      .login-right {
        display: none;
      }
    }
  </style>
</head>

<body>
  <div class="login-page">
    <div class="login-shell">
      <div class="login-left">
        <div class="brand-row">
          <img src="{{ $logoUrl }}" alt="MMI Logo">
          <div class="brand-name">MEGATAMA MAKMUR INDONESIA</div>
        </div>

        <h1 class="heading">
          <span>INVENTORY</span>
          <span>MEGATAMA</span>
        </h1>

        <div class="subtext">Silakan login untuk melanjutkan.</div>

        @if (session('status'))
          <div class="alert alert-success mb-3" role="alert">
            {{ session('status') }}
          </div>
        @endif

        @if ($errors->any() && !$errors->has('email') && !$errors->has('password'))
          <div class="alert alert-danger mb-3" role="alert">
            {{ $errors->first() }}
          </div>
        @endif

        <form action="{{ route('login') }}" method="POST" autocomplete="on">
          @csrf
          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input
              id="email"
              type="email"
              name="email"
              value="{{ old('email') }}"
              class="form-control @error('email') is-invalid @enderror"
              placeholder="Masukkan email anda"
              required
              autocomplete="username"
              autofocus
            >
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-2 password-row">
            <label class="form-label" for="password">Password</label>
            <input
              id="password"
              type="password"
              name="password"
              class="form-control @error('password') is-invalid @enderror"
              placeholder="Masukkan password anda"
              required
              autocomplete="current-password"
            >
            <button type="button" class="toggle-pass" id="togglePassword">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/>
                <circle cx="12" cy="12" r="3.2"/>
              </svg>
              <span id="togglePasswordText">Hide</span>
            </button>
            @error('password')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="helper-row">
            <label class="form-check m-0">
              <input type="checkbox" class="form-check-input" name="remember" {{ old('remember') ? 'checked' : '' }}>
              <span class="form-check-label">Remember me</span>
            </label>
          </div>

          <button type="submit" class="login-submit">Login</button>
        </form>
      </div>

      <div class="login-right">
        <div class="illustration-card">
          @if ($illustrationUrl)
            <img src="{{ $illustrationUrl }}" alt="Warehouse illustration">
          @else
            <div class="illustration-placeholder"></div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const btn = document.getElementById('togglePassword');
      const input = document.getElementById('password');
      const label = document.getElementById('togglePasswordText');

      if (btn && input && label) {
        btn.addEventListener('click', function () {
          const isHidden = input.getAttribute('type') === 'password';
          input.setAttribute('type', isHidden ? 'text' : 'password');
          label.textContent = isHidden ? 'Hide' : 'Show';
        });
      }
    })();
  </script>
</body>
</html>
