@php
  // Inject Tailwind build (opsional)
  $css = @file_get_contents(public_path('css/app.css'));

  // Helper angka
  function qty_fmt($n, $dec = 4) {
    $s = number_format((float)$n, $dec, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' ? '0' : $s;
  }

  // Logo opsional
  $logoPath = public_path('logo.png');
  $hasLogo  = file_exists($logoPath);

  /**
   * Data input yang mungkin dikirim dari controller:
   * - $receipts      : (opsional) koleksi receipt posted dengan items
   * - $datesSummary  : (opsional) ringkasan per tanggal
   * - $items         : (opsional) agregat semua tanggal
   * - $po            : PurchaseOrder (HARUS ada)
   * - $receipt       : meta sintetis dokumen (HARUS ada)
   */

  // Fallback: jika $receipts tidak disuplai controller, ambil di sini
  if (!isset($receipts)) {
      $receipts = \App\Models\PurchaseReceipt::with('items')
          ->where('purchase_order_id', $po->id)
          ->where('status', 'posted')
          ->orderBy('receipt_date')
          ->orderBy('id')
          ->get();
  }

  // Kelompokkan per tanggal 'Y-m-d'
  $grouped = $receipts->groupBy(fn($r) => optional($r->receipt_date)->format('Y-m-d'));

  // Ringkasan per tanggal (fallback jika tidak ada $datesSummary)
  if (!isset($datesSummary)) {
      $datesSummary = $grouped->map(function ($group, $ymd) {
          return [
              'date'     => $ymd,
              'receipts' => $group->count(),
              'rows'     => $group->sum(fn($r) => $r->items->count()),
              'qty'      => $group->reduce(fn($s, $r) => $s + (float) $r->items->sum('received_quantity'), 0),
          ];
      })->values()->all();
  }

  // Periode & jumlah receipt
  $minDate = optional($receipts->min('receipt_date'))->format('d-m-Y');
  $maxDate = optional($receipts->max('receipt_date'))->format('d-m-Y');
  $totalReceipts = $receipts->count();

@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $receipt->receipt_number ?? 'Receipt Merged' }}</title>

  @if($css)
    <style>{!! $css !!}</style>
  @endif

  <style>
    @page { margin: 18mm 14mm; }
    * { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
    h1,h2,h3,h4 { margin: 0 0 6px 0; }
    .mb-1 { margin-bottom: 4px; }
    .mb-2 { margin-bottom: 8px; }
    .mb-3 { margin-bottom: 12px; }
    .mb-4 { margin-bottom: 16px; }
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
    .section-title { font-weight: bold; margin: 10px 0 6px 0; }
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
        <h2 class="mb-1">Goods Receipt — Merged</h2>
        <div class="small">Gabungan semua penerimaan (POSTED) untuk PO ini</div>
      </div>
    </div>
    <div class="header-right">
      <div><strong>No. Dokumen:</strong> {{ $receipt->receipt_number ?? '-' }}</div>
      <div><span class="badge">{{ strtoupper($receipt->status ?? 'posted') }}</span></div>
    </div>
  </div>

  {{-- Meta --}}
  <table class="meta-table mb-3">
    <tr>
      <td style="width: 25%"><strong>No. PO</strong></td>
      <td style="width: 45%">: {{ $po->po_number ?? '-' }}</td>
      <td style="width: 15%"><strong>Periode</strong></td>
      <td style="width: 15%">: {{ $minDate ?: '-' }} @if($maxDate && $maxDate !== $minDate) s/d {{ $maxDate }} @endif</td>
    </tr>
    <tr>
      <td><strong>Supplier</strong></td>
      <td>: {{ optional($po->supplier)->name ?? '-' }}</td>
      <td><strong>Jumlah Receipt</strong></td>
      <td>: {{ $totalReceipts }}</td>
    </tr>
    <tr>
      <td><strong>Catatan PO</strong></td>
      <td colspan="3">: {{ $po->notes ?? '-' }}</td>
    </tr>
  </table>

  {{-- Ringkasan per Tanggal --}}
  <h4 class="mb-1">Ringkasan Penerimaan per Tanggal</h4>
  <table class="table mb-4">
    <thead>
      <tr>
        <th style="width:120px">Tanggal</th>
        <th style="width:120px" class="text-center">Jumlah Receipt</th>
        <th class="text-right" style="width:160px">Total Qty</th>
      </tr>
    </thead>
    <tbody>
      @foreach($datesSummary as $row)
        <tr>
          <td>{{ \Carbon\Carbon::parse($row['date'])->format('d-m-Y') }}</td>
          <td class="text-center">{{ $row['receipts'] }}</td>
          <td class="text-right">{{ qty_fmt($row['qty']) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  {{-- Rincian Item per Tanggal --}}
  <h4 class="mb-1">Rincian Item — Per Tanggal Kedatangan</h4>

  @forelse($grouped as $ymd => $rows)
    @php
      // Agregasi item untuk tanggal ini: gabungkan material+unit
      $map = [];
      foreach ($rows as $rc) {
          foreach ($rc->items as $it) {
              $key = $it->material_name.'|'.$it->unit;
              if (!isset($map[$key])) {
                  $map[$key] = [
                      'material_name' => $it->material_name,
                      'unit'          => $it->unit,
                      'qty'           => 0.0,
                      'notes'         => [],
                  ];
              }
              $map[$key]['qty'] += (float) $it->received_quantity;
              if (!empty($it->notes)) {
                  $map[$key]['notes'][] = $it->notes;
              }
          }
      }
      $rowsForDate = collect($map)->sortBy('material_name')->values();
    @endphp

    <div class="section-title">Tanggal: {{ \Carbon\Carbon::parse($ymd)->format('d-m-Y') }}</div>
    <table class="table mb-3">
      <thead>
        <tr>
          <th style="width:40px">No</th>
          <th>Material</th>
          <th style="width:90px">Unit</th>
          <th class="text-right" style="width:140px">Qty Diterima</th>
          <th>Catatan</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rowsForDate as $i => $line)
          <tr>
            <td class="text-center">{{ $i+1 }}</td>
            <td>{{ $line['material_name'] }}</td>
            <td class="text-center">{{ $line['unit'] }}</td>
            <td class="text-right">{{ qty_fmt($line['qty']) }}</td>
            <td>{{ empty($line['notes']) ? '' : implode(' / ', array_unique($line['notes'])) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @empty
    <p class="text-muted">Tidak ada data penerimaan.</p>
  @endforelse

  {{-- Footer --}}
  <p class="small" style="margin-top:10px">
    Dicetak: {{ now()->format('d-m-Y H:i') }}
    @if(!empty($receipt->posted_at))
      | Last Posted: {{ optional($receipt->posted_at)->format('d-m-Y H:i') }}
    @endif
  </p>

</body>
</html>
