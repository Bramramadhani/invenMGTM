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

  $reqDate = optional($order->created_at)->format('d-m-Y') ?? '-';
  $reqTime = optional($order->created_at)->format('H:i') ?? '-';

  // Style yang dipilih di header
  $style     = optional($order->purchaseOrderStyle ?? null);
  $styleName = $style->name
    ?? $style->style_name
    ?? $style->nama_style
    ?? '';

  $sourceType  = $order->source_type ?? 'po';
  $sourceLabel = match ($sourceType) {
    'fob', 'fob_full' => 'Stok FOB (Buyer) terkait PO/Style',
    'mixed'          => 'Campuran (PO + FOB)',
    default          => 'Stok PO / Buyer',
  };
  $buyerName   = optional($order->buyer)->name;

  $showVendorCol  = in_array($sourceType, ['fob', 'mixed'], true);
  $targetPoNo  = optional(optional($style)->purchaseOrder)->po_number;
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
      text-align: center;
    }
    .table th { background-color: #f2f2f2; font-weight: bold; }
    .table td.catatan { text-align: center; }

    /* HEADER */
    .logo { height: 36px; }
    .header { display: table; width:100%; margin-bottom: 10px; }
    .header-left  { display: table-cell; vertical-align: middle; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .header-right .row { margin-bottom: 2px; }

    /* META */
    .meta { width:100%; border-collapse: collapse; }
    .meta td { padding: 3px 4px; vertical-align: top; white-space: nowrap; }
    .meta .label { width: 160px; font-weight: bold; }
    .meta .sep   { width: 12px; text-align: center; }
    .meta .value { width: 180px; }
    .meta .flex  { width: 100%; }

    .meta .label-r { width: 240px; text-align: right; padding-right: 6px; }
    .meta .value-r { width: 260px; }

    /* SIGNATURES */
    .sign-grid { width:100%; border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
    .sign-grid th, .sign-grid td { border: 0; padding: 0 8px; }

    .sign-title {
      font-size: 12px;
      font-weight: 700;
      padding-bottom: 6px;
      text-align: center;
    }
    .sign-box {
      border:1px dashed #000;
      height: 80px;
      width: 90%;
      margin: 0 auto;
    }
    .sig-name {
      margin-top: 8px;
      width: 90%;
      font-weight: 700;
      text-align: center;
      word-break: break-word;
      margin-left: auto;
      margin-right: auto;
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
        <h3 class="mb-1">Receipt Permintaan Barang</h3>
        <div class="small">Bukti pengeluaran barang untuk keperluan produksi</div>
      </div>
    </div>
    <div class="header-right">
      <div class="row"><strong>No. Dokumen:</strong> {{ $order->name ?? ('#'.$order->id) }}</div>
      <div class="row small">Status: {{ $order->status ?? '-' }}</div>
    </div>
  </div>

  {{-- Meta --}}
  <table class="meta mb-3">
    <tr>
      <td class="label">Tanggal Permintaan</td>
      <td class="sep">:</td>
      <td class="value">{{ $reqDate }}</td>

      <td class="flex"></td>

      <td class="label label-r">Jam Permintaan</td>
      <td class="sep">:</td>
      <td class="value value-r">{{ $reqTime }}</td>
    </tr>
    <tr>
      <td class="label">Dibuat Oleh</td>
      <td class="sep">:</td>
      <td class="value">{{ optional($order->user)->name ?? '—' }}</td>

      <td class="flex"></td>

      <td class="label label-r">Style</td>
      <td class="sep">:</td>
      <td class="value value-r">{{ $styleName ?: '—' }}</td>
    </tr>
    <tr>
      <td class="label">Sumber Stok</td>
      <td class="sep">:</td>
      <td class="value">{{ $sourceLabel }}</td>

      <td class="flex"></td>

      <td class="label label-r">Buyer (FOB)</td>
      <td class="sep">:</td>
      <td class="value value-r">{{ $buyerName ?: '—' }}</td>
    </tr>
    @if(!empty($order->notes))
      <tr>
        <td class="label">Catatan</td>
        <td class="sep">:</td>
        <td class="value" colspan="5">{{ $order->notes }}</td>
      </tr>
    @endif
  </table>

  {{-- Tabel Item --}}
  <table class="table mb-4">
    <thead>
      <tr>
        <th style="width:40px">No</th>
        <th style="width:80px">Kode</th>
        <th style="width:160px">Material</th>
        <th style="width:60px">Unit</th>
        <th style="width:80px">Qty</th>
        <th style="width:180px">Supplier / Buyer / PO</th>
        <th style="width:160px">Catatan</th>
      </tr>
    </thead>
    <tbody>
      @forelse($order->items as $i => $row)
        @php
          $stock        = $row->stock;
          $isFobItem    = !is_null($stock?->buyer_id);
          $supplierName = optional($stock?->supplier)->name;
          $buyerNameRow = optional($stock?->buyer)->name;
          $sourceLabel  = $supplierName ?: $buyerNameRow;
          $vendor       = $stock?->vendor_name;
          $po           = $isFobItem ? $targetPoNo : optional($stock?->purchaseOrder)->po_number;
          if (!$isFobItem && empty($po) && is_null($stock?->purchase_order_id)) {
            $po = 'GLOBAL';
          }
          $poLabel = $isFobItem ? 'PO target' : 'PO stok';
        @endphp
        <tr>
          <td>{{ $i+1 }}</td>
          <td>{{ $row->material_code ?? '—' }}</td>
          <td>{{ $row->material_name }}</td>
          <td>{{ $row->unit ?? '—' }}</td>
          <td>{{ qty_fmt($row->quantity) }}</td>
          <td>
            @if($sourceLabel || $po)
              @if($sourceLabel) <div>{{ $sourceLabel }}</div> @endif
              @if($isFobItem && !empty($vendor)) <div class="small">Vendor: {{ $vendor }}</div> @endif
              @if($po) <div class="small">{{ $poLabel }}: {{ $po }}</div> @endif
            @else
              —
            @endif
          </td>
          <td class="catatan">{{ $row->notes ?: '—' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="small">Tidak ada item.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  {{-- Tanda Tangan 1: Produksi, Leader Produksi, Checker Gudang --}}
  <table class="sign-grid mb-3">
    <colgroup>
      <col style="width:33%">
      <col style="width:34%">
      <col style="width:33%">
    </colgroup>
    <thead>
      <tr>
        <th class="sign-title">Checker Produksi</th>
        <th class="sign-title">Leader Produksi</th>
        <th class="sign-title">Checker Gudang</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><div class="sign-box"></div></td>
        <td><div class="sign-box"></div></td>
        <td><div class="sign-box"></div></td>
      </tr>
      <tr>
        <td><div class="sig-name">{{ $order->production_name ?: ' ' }}</div></td>
        <td><div class="sig-name">{{ $order->production_leader_name ?: ' ' }}</div></td>
        <td><div class="sig-name">{{ $order->warehouse_admin_name ?: ' ' }}</div></td>
      </tr>
    </tbody>
  </table>

  {{-- Tanda Tangan 2: Leader Gudang & Supply Chain Head --}}
  <table class="sign-grid">
    <colgroup>
      <col style="width:50%">
      <col style="width:50%">
    </colgroup>
    <thead>
      <tr>
        <th class="sign-title">Leader Gudang</th>
        <th class="sign-title">Supply Chain Head</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><div class="sign-box"></div></td>
        <td><div class="sign-box"></div></td>
      </tr>
      <tr>
        <td><div class="sig-name">{{ $order->warehouse_leader_name ?: ' ' }}</div></td>
        <td><div class="sig-name">{{ $order->supply_chain_head_name ?: ' ' }}</div></td>
      </tr>
    </tbody>
  </table>

  <p class="small" style="margin-top:14px;">
    Dicetak pada {{ $printedAtDate }} pukul {{ $printedAtTime }}.
  </p>

</body>
</html>
