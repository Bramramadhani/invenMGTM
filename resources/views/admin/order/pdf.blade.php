@php
  $css = @file_get_contents(public_path('css/app.css'));

  if (!function_exists('qty_fmt')) {
    function qty_fmt($n, $dec = 4) {
      $s = number_format((float)$n, $dec, '.', '');
      $s = rtrim(rtrim($s, '0'), '.');
      return $s === '' ? '0' : $s;
    }
  }

  $logoPath = public_path('logo.png');
  $hasLogo  = file_exists($logoPath);
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Receipt Permintaan — {{ $order->name }}</title>

  @if (!empty($css))
    <style>{!! $css !!}</style>
  @endif

  <style>
    @page { margin: 16mm 12mm; }
    * { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; }
    h1,h2,h3,h4,h5 { margin: 0 0 6px 0; }
    .small { font-size: 10px; color:#555; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .text-left { text-align: left; }
    .mb-1 { margin-bottom: 4px; }
    .mb-2 { margin-bottom: 8px; }
    .mb-3 { margin-bottom: 12px; }
    .mb-4 { margin-bottom: 16px; }

    .table { width:100%; border-collapse: collapse; table-layout: fixed; }
    .table th, .table td { 
      border:1px solid #000; 
      padding:6px; 
      vertical-align: middle; 
      font-size: 12px; 
      text-align: center; /* default tengah */
    }
    .table th { background-color: #f2f2f2; font-weight: bold; }

    /* Khusus catatan kiri */
    .table td.catatan { text-align: left; }

    .meta td { padding: 2px 4px; }
    .logo { height: 36px; }
    .header { display: table; width:100%; margin-bottom: 10px; }
    .header-left { display: table-cell; vertical-align: middle; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .sign-row { display: table; width: 100%; margin-top: 24px; }
    .sign-col { display: table-cell; width: 50%; vertical-align: top; }
    .sign-box { border:1px dashed #000; height: 80px; }
  </style>
</head>
<body>

  {{-- Header --}}
  <div class="header">
    <div class="header-left">
      @if($hasLogo)
        <img class="logo" src="{{ $logoPath }}" alt="Logo">
      @endif
      <div>
        <h3 class="mb-1">Receipt Permintaan Barang</h3>
        <div class="small">Bukti fisik pengeluaran untuk produksi</div>
      </div>
    </div>
    <div class="header-right">
      <div><strong>No. Dokumen:</strong> {{ $order->name }}</div>
    </div>
  </div>

  {{-- Meta --}}
  <table class="meta mb-3" style="width:100%;">
    <tr>
      <td style="width:22%"><strong>Tanggal Cetak</strong></td>
      <td style="width:28%">: {{ $printedAtDate }}</td>
      <td style="width:22%"><strong>Jam Permintaan</strong></td>
      <td style="width:28%">: {{ $printedAtTime }}</td>
    </tr>
    <tr>
      <td><strong>Dibuat Oleh</strong></td>
      <td>: {{ optional($order->user)->name ?? '—' }}</td>
      <td><strong>Admin</strong></td>
      <td>: {{ $adminName ?? '' }}</td>
    </tr>
    @if(!empty($order->notes))
      <tr>
        <td><strong>Catatan</strong></td>
        <td colspan="3">: {{ $order->notes }}</td>
      </tr>
    @endif
  </table>

  {{-- Tabel Item --}}
  <table class="table mb-4">
    <thead>
      <tr>
        <th style="width:40px">No</th>
        <th style="width:80px">Kode</th>
        <th style="width:150px">Material</th>
        <th style="width:70px">Unit</th>
        <th style="width:70px">Qty</th>
        <th style="width:160px">Supplier / PO</th>
        <th style="width:160px">Catatan</th>
      </tr>
    </thead>
    <tbody>
      @forelse($order->items as $i => $row)
        @php
          $stock    = $row->stock;
          $supplier = optional($stock?->supplier)->name;
          $po       = $stock?->last_po_number;
        @endphp
        <tr>
          <td>{{ $i+1 }}</td>
          <td>{{ $row->material_code ?? '—' }}</td>
          <td>{{ $row->material_name }}</td> <!-- Material otomatis tengah -->
          <td>{{ $row->unit ?? '—' }}</td>
          <td>{{ qty_fmt($row->quantity) }}</td> <!-- Qty otomatis tengah -->
          <td>
            @if($supplier || $po)
              @if($supplier) <div>{{ $supplier }}</div> @endif
              @if($po) <div class="small">PO: {{ $po }}</div> @endif
            @else
              —
            @endif
          </td>
          <td class="catatan">{{ $row->notes ?: '—' }}</td> <!-- Catatan kiri -->
        </tr>
      @empty
        <tr>
          <td colspan="7" class="small">Tidak ada item.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  {{-- Tanda Tangan --}}
  <div class="sign-row">
    <div class="sign-col" style="padding-right:10px;">
      <div class="mb-1"><strong>Checker :</strong></div>
      <div class="sign-box"></div>
      <div class="small" style="margin-top:6px;">Tanda tangan & nama jelas</div>
    </div>
    <div class="sign-col" style="padding-left:10px;">
      <div class="mb-1"><strong>Admin:</strong> {{ $adminName ?? '' }}</div>
      <div class="sign-box"></div>
      <div class="small" style="margin-top:6px;">Tanda tangan & nama jelas</div>
    </div>
  </div>

  <p class="small" style="margin-top:14px;">
    Dicetak pada {{ $printedAtDate }} pukul {{ $printedAtTime }}.
  </p>

</body>
</html>
