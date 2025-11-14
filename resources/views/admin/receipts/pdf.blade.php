@php
  // Ambil CSS publik (Tailwind build). Aman jika file belum ada (pakai @).
  $css = @file_get_contents(public_path('css/app.css'));

  // Formatter angka qty
  function qty_fmt($n, $dec = 4) {
    $s = number_format((float)$n, $dec, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' ? '0' : $s;
  }

  // Logo opsional: public/logo.png
  $logoPath = public_path('logo.png');
  $hasLogo  = file_exists($logoPath);
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $receipt->receipt_number ?? ('Receipt #'.$receipt->id) }}</title>

  {{-- Inject Tailwind build (jika ada) --}}
  @if($css)
    <style>{!! $css !!}</style>
  @endif

  {{-- CSS minimal yang didukung DomPDF --}}
  <style>
    @page { margin: 18mm 14mm; }
    * { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
    h1,h2,h3,h4 { margin: 0 0 6px 0; }
    .mb-1 { margin-bottom: 4px; }
    .mb-2 { margin-bottom: 8px; }
    .mb-3 { margin-bottom: 12px; }
    .small { font-size: 10px; color: #555; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .text-muted { color: #666; }
    .w-100 { width: 100%; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { border: 1px solid #000; padding: 6px; vertical-align: top; }
    .meta-table { width: 100%; border-collapse: collapse; }
    .meta-table td { padding: 4px 6px; vertical-align: top; }
    .logo { height: 36px; }
    .header { margin-bottom: 12px; display: table; width: 100%; }
    .header-left  { display: table-cell; vertical-align: middle; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .badge { display: inline-block; padding: 2px 6px; border:1px solid #333; border-radius: 3px; font-size: 10px; }

    /* 🧩 Styling tambahan untuk catatan */
    .note-block {
      white-space: pre-line;
      font-size: 11px;
      line-height: 1.35;
    }
    .note-reject {
      color: #c00;
      font-size: 10px;
      margin-top: 3px;
      display: block;
    }
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
        <h2 class="mb-1">Goods Receipt</h2>
        <div class="small">Dokumen penerimaan barang</div>
      </div>
    </div>
    <div class="header-right">
      <div><strong>No. Receipt:</strong> {{ $receipt->receipt_number ?? '-' }}</div>
      <div><span class="badge">{{ strtoupper($receipt->status) }}</span></div>
    </div>
  </div>

  {{-- Meta --}}
  <table class="meta-table mb-3">
    <tr>
      <td style="width: 25%"><strong>No. PO</strong></td>
      <td style="width: 45%">: {{ optional($receipt->purchaseOrder)->po_number ?? '-' }}</td>
      <td style="width: 15%"><strong>Tanggal</strong></td>
      <td style="width: 15%">: {{ optional($receipt->receipt_date)->format('d-m-Y') ?? '-' }}</td>
    </tr>
    <tr>
      <td><strong>Supplier</strong></td>
      <td>: {{ optional(optional($receipt->purchaseOrder)->supplier)->name ?? '-' }}</td>
      <td><strong>Posted</strong></td>
      <td>: {{ $receipt->posted_at ? $receipt->posted_at->format('d-m-Y H:i') : '-' }}</td>
    </tr>
    <tr>
      <td><strong>Catatan PO</strong></td>
      <td colspan="3">: {{ optional($receipt->purchaseOrder)->notes ?? '-' }}</td>
    </tr>
  </table>

  {{-- Tabel Item --}}
  <table class="table">
    <thead>
      <tr>
        <th style="width:40px">No</th>
        <th>Material</th>
        <th style="width:90px">Unit</th>
        <th class="text-right" style="width:120px">Qty Diterima</th>
        <th>Catatan</th>
      </tr>
    </thead>
    <tbody>
      @forelse($receipt->items as $i => $it)
        <tr>
          <td class="text-center">{{ $i+1 }}</td>
          <td>{{ $it->material_name }}</td>
          <td class="text-center">{{ $it->unit }}</td>
          <td class="text-right">{{ qty_fmt($it->received_quantity) }}</td>
          <td class="note-block">
            {{-- Catatan penerimaan --}}
            @if(!empty($it->notes))
              {{ $it->notes }}
            @endif

            {{-- Catatan reject (jika ada) --}}
            @php
              $lines = preg_split('/[\r\n]+/', trim($it->notes ?? ''));
              $rejects = array_filter($lines, fn($l) => str_contains($l, 'Reject:'));
            @endphp
            @if(!empty($rejects))
              @foreach($rejects as $r)
                <span class="note-reject">{{ $r }}</span>
              @endforeach
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="text-center text-muted">Tidak ada item.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  {{-- Footer --}}
  <p class="small" style="margin-top:10px">
    Dicetak: {{ now()->format('d-m-Y H:i') }}
    @if($receipt->posted_at)
      | Posted: {{ $receipt->posted_at->format('d-m-Y H:i') }}
    @endif
  </p>

</body>
</html>
