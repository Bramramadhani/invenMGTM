<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta http-equiv="X-UA-Compatible" content="ie=edge" />
  <title>{{ $title }} - {{ config('app.name', 'Laravel') }}</title>

  {{-- ====== META TAG TAMBAHAN (dipertahankan karena sistem internal) ====== --}}
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
  <meta name="msapplication-TileColor" content="#206bc4" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#206bc4" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="HandheldFriendly" content="True" />
  <meta name="MobileOptimized" content="320" />
  <meta name="robots" content="noindex,nofollow,noarchive" />

  {{-- ====== FAVICON ====== --}}
  <link rel="icon" href="{{ asset('icon.png') }}" type="image/x-icon" />
  <link rel="shortcut icon" href="{{ asset('icon.png') }}" type="image/x-icon" />

  {{-- ====== FONT AWESOME ====== --}}
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css"
        integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr"
        crossorigin="anonymous">

  {{-- ====== BOOTSTRAP ====== --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  {{-- ====== TABLER CORE & PLUGINS ====== --}}
  <link href="{{ asset('dist/css/tabler.min.css') }}" rel="stylesheet" />
  <link href="{{ asset('dist/css/demo.min.css') }}" rel="stylesheet" />
  <link href="{{ asset('dist/libs/selectize/dist/css/selectize.css') }}" rel="stylesheet" />

  {{-- ====== CUSTOM STYLE (opsional) ====== --}}
  @stack('css')
</head>

<body class="antialiased">
  {{-- ====== SIDEBAR ====== --}}
  @include('layouts._sidebar')

  <div class="page">
    {{-- ====== NAVBAR ====== --}}
    @include('layouts._navbar')

    {{-- ====== MAIN CONTENT ====== --}}
    <div class="content">

      {{-- ====== ALERT NOTIFICATION (Success / Error / Validation) ====== --}}
      @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
          <strong>Sukses!</strong> {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif

      @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">
          <strong>Gagal!</strong> {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif

      @if ($errors->any())
        <div class="alert alert-warning alert-dismissible fade show mt-2" role="alert">
          <strong>Perhatian!</strong> Terdapat beberapa kesalahan input:
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif

      {{-- ====== YIELD CONTENT (isi halaman utama) ====== --}}
      @yield('content')

      {{-- ====== FOOTER ====== --}}
      @include('layouts._footer')

      {{-- ====== SWEETALERT (NOTIFIKASI TAMBAHAN) ====== --}}
      @include('sweetalert::alert')
    </div>
  </div>

  {{-- ====== MODALS STACK (semua modal halaman dirender di sini) ====== --}}
  @stack('modals')

  {{-- ====== JAVASCRIPT LIBRARIES ====== --}}
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="{{ asset('dist/libs/jquery/dist/jquery.slim.min.js') }}"></script>
  <script src="{{ asset('dist/libs/selectize/dist/js/standalone/selectize.min.js') }}"></script>
  <script src="{{ asset('dist/libs/apexcharts/dist/apexcharts.min.js') }}"></script>

  {{-- ====== TABLER CORE JS ====== --}}
  <script src="{{ asset('backend/dist/js/tabler.min.js') }}"></script>

  {{-- ====== SWEETALERT2 ====== --}}
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  {{-- ====== GLOBAL SCRIPT UNTUK DELETE KONFIRMASI ====== --}}
  <script>
    function deleteData(id) {
      const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
          confirmButton: 'btn btn-success',
          cancelButton: 'btn btn-danger'
        },
        buttonsStyling: true
      });

      swalWithBootstrapButtons.fire({
        title: 'Apakah kamu yakin ingin menghapus data ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Tolong Hapus!',
        cancelButtonText: 'Tidak!',
        reverseButtons: true
      }).then((result) => {
        if (result.value) {
          event.preventDefault();
          document.getElementById('delete-form-' + id).submit();
        } else if (result.dismiss === Swal.DismissReason.cancel) {
          swalWithBootstrapButtons.fire('Data kamu tetap aman!', '', 'error');
        }
      });
    }

    // Selectize (contoh untuk input tag atau select dinamis)
    $(function() {
      $('#select-tags-advanced').selectize({
        maxItems: 15,
        plugins: ['remove_button']
      });
    });
  </script>

  {{-- ====== STACK UNTUK SCRIPT TAMBAHAN SETIAP HALAMAN ====== --}}
  @stack('js')
</body>
</html>
