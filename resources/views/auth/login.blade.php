<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta http-equiv="X-UA-Compatible" content="ie=edge" />
  <title>Login - Inventory MMI</title>

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

  <link rel="icon" href="{{ asset('icon.png') }}" type="image/x-icon" />
  <link rel="shortcut icon" href="{{ asset('icon.png') }}" type="image/x-icon" />
  <link href="{{ asset('dist/css/tabler.min.css') }}" rel="stylesheet" />
  <style>
    .card-md { max-width: 380px; margin: 0 auto; }
  </style>
</head>

<body class="antialiased border-top-wide border-primary d-flex flex-column">
  <div class="flex-fill d-flex flex-column justify-content-center">
    <div class="container-tight py-6">

      {{-- Alert sukses / info (kalau ada) --}}
      @if (session('status'))
        <div class="alert alert-success mb-3" role="alert">
          {{ session('status') }}
        </div>
      @endif

      {{-- Alert error global (mis. kredensial salah) --}}
      @if ($errors->any() && !$errors->has('email') && !$errors->has('password'))
        <div class="alert alert-danger mb-3" role="alert">
          {{ $errors->first() }}
        </div>
      @endif

      <form class="card card-md border-0 rounded-3" action="{{ route('login') }}" method="POST" autocomplete="on">
        @csrf
        <div class="card-body">
          <h3 class="text-center mb-3 fw-semibold">Login</h3>

          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input
              id="email"
              type="email"
              name="email"
              value="{{ old('email') }}"
              class="form-control @error('email') is-invalid @enderror"
              placeholder="masukkan email anda"
              required
              autocomplete="username"
              autofocus
            >
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-2">
            <label class="form-label" for="password">Kata Sandi</label>
            <input
              id="password"
              type="password"
              name="password"
              class="form-control @error('password') is-invalid @enderror"
              placeholder="masukkan kata sandi anda"
              required
              autocomplete="current-password"
            >
            @error('password')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3 d-flex align-items-center justify-content-between">
            <label class="form-check m-0">
              <input type="checkbox" class="form-check-input" name="remember" {{ old('remember') ? 'checked' : '' }}>
              <span class="form-check-label">Ingat saya</span>
            </label>
            {{-- kalau pakai fitur reset password, bisa aktifkan ini: --}}
            {{-- <a href="{{ route('password.request') }}">Lupa password?</a> --}}
          </div>

          <div class="form-footer">
            <button type="submit" class="btn btn-primary w-100">Masuk</button>
          </div>
        </div>
      </form>

      {{-- debug kecil kalau masih 419 (boleh dihapus nanti) --}}
      {{-- <div class="text-center text-muted small mt-3">
        CSRF: {{ substr(csrf_token(), 0, 8) }}…
      </div> --}}
    </div>
  </div>

  {{-- Optional: set header CSRF untuk ajax bila dipakai --}}
  <script>
    (function() {
      const t = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (!t) return;
      if (window.axios) axios.defaults.headers.common['X-CSRF-TOKEN'] = t;
      if (window.jQuery) $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': t } });
    })();
  </script>
</body>
</html>
