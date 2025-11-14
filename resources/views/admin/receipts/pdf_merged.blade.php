@php
  $css = @file_get_contents(public_path('css/app.css'));

  function qty_fmt($n, $dec = 4) {
    $s = number_format((float)$n, $dec, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' ? '0' : $s;
  }

  $logoPath = public_path('logo.png');
  $hasLogo  = file_exists($logoPath);

  if (!isset($receipts)) {
      $receipts = \App\Models\PurchaseReceipt::with(['items'])
          ->where('purchase_order_id', $po->id)
          ->where('status', 'posted')
          ->orderBy('receipt_date')
          ->orderBy('id')
          ->get();
  }

  $grouped = $receipts->groupBy(fn($r) => optional($r->receipt_date)->format('Y-m-d'));

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

  $minDate = optional($receipts->min('receipt_date'))->format('d-m-Y');
  $maxDate = optional($receipts->max('receipt_date'))->format('d-m-Y');
  $totalReceipts = $receipts->count();

  // 🧩 Ambil semua data reject berdasarkan purchase_order_id langsung
  $rejectsAll = \App\Models\PurchaseOrderReject::with('item')
      ->whereHas('item', fn($q) => $q->where('purchase_order_id', $po->id))
      ->get();

  // Bentuk map reject per material|unit
  $rejectMapGlobal = [];
  foreach ($rejectsAll as $rj) {
      if (!$rj->item) continue;
      $key = $rj->item->material_name.'|'.$rj->item->unit;
      if (!isset($rejectMapGlobal[$key])) $rejectMapGlobal[$key] = ['qty' => 0, 'notes' => []];
      $rejectMapGlobal[$key]['qty'] += (float)$rj->reject_quantity;
      if (!empty($rj->new_notes)) $rejectMapGlobal[$key]['notes'][] = $rj->new_notes;
  }
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
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { border: 1px solid #000; padding: 6px; vertical-align: top; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .section-title { font-weight: bold; margin: 10px 0 6px 0; }
    .small { font-size: 10px; color: #555; }
    .reject-note { color: #b00000; font-size: 10px; display:block; margin-top:2px; }
  </style>
</head>
<body>

  {{-- Header --}}
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
    <div>
      @if($hasLogo)
        <img src="{{ $logoPath }}" style="height:36px;" alt="Logo"><br>
      @endif
      <strong>Goods Receipt — Merged</strong><br>
      <span class="small">Gabungan semua penerimaan (POSTED)</span>
    </div>
    <div style="text-align:right;">
      <strong>No Dokumen:</strong> {{ $receipt->receipt_number ?? '-' }}<br>
      <span class="small">{{ strtoupper($receipt->status ?? 'posted') }}</span>
    </div>
  </div>

  <table style="width:100%; margin-bottom:10px;">
    <tr>
      <td style="width:25%"><strong>No. PO</strong></td>
      <td style="width:45%">: {{ $po->po_number ?? '-' }}</td>
      <td style="width:15%"><strong>Periode</strong></td>
      <td style="width:15%">: {{ $minDate ?: '-' }} @if($maxDate && $maxDate !== $minDate) s/d {{ $maxDate }} @endif</td>
    </tr>
    <tr>
      <td><strong>Supplier</strong></td>
      <td>: {{ optional($po->supplier)->name ?? '-' }}</td>
      <td><strong>Jumlah Receipt</strong></td>
      <td>: {{ $totalReceipts }}</td>
    </tr>
  </table>

  <h4>Rincian Item — Per Tanggal Kedatangan</h4>

  @forelse($grouped as $ymd => $rows)
    @php
      $itemsMap = [];
      foreach ($rows as $rc) {
          foreach ($rc->items as $it) {
              $key = $it->material_name.'|'.$it->unit;
              if (!isset($itemsMap[$key])) {
                  $itemsMap[$key] = [
                      'material_name' => $it->material_name,
                      'unit'          => $it->unit,
                      'qty'           => 0.0,
                      'notes'         => [],
                  ];
              }
              $itemsMap[$key]['qty'] += (float) $it->received_quantity;
              if (!empty($it->notes)) $itemsMap[$key]['notes'][] = $it->notes;
          }
      }
      $rowsForDate = collect($itemsMap)->sortBy('material_name')->values();
    @endphp

    <div class="section-title">Tanggal: {{ \Carbon\Carbon::parse($ymd)->format('d-m-Y') }}</div>
    <table class="table" style="margin-bottom:12px;">
      <thead>
        <tr>
          <th style="width:40px;">No</th>
          <th>Material</th>
          <th style="width:80px;">Unit</th>
          <th class="text-right" style="width:100px;">Qty Diterima</th>
          <th class="text-right" style="width:90px;">Rejected</th>
          <th>Catatan</th>
          <th style="width:160px;">Catatan Reject</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rowsForDate as $i => $line)
          @php
            $k = $line['material_name'].'|'.$line['unit'];
            $rejQty = $rejectMapGlobal[$k]['qty'] ?? 0;
            $rejNotes = $rejectMapGlobal[$k]['notes'] ?? [];
            $combinedNotes = implode(' / ', array_unique($line['notes']));
            $combinedRejectNotes = implode(' / ', array_unique($rejNotes));
          @endphp
          <tr>
            <td class="text-center">{{ $i+1 }}</td>
            <td>{{ $line['material_name'] }}</td>
            <td class="text-center">{{ $line['unit'] }}</td>
            <td class="text-right">{{ qty_fmt($line['qty']) }}</td>
            <td class="text-right">{{ qty_fmt($rejQty) }}</td>
            <td>{{ $combinedNotes ?: '—' }}</td>
            <td>
              @if($combinedRejectNotes)
                <span class="reject-note">{{ $combinedRejectNotes }}</span>
              @else
                &mdash;
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @empty
    <p class="text-muted">Tidak ada data penerimaan.</p>
  @endforelse

  <p class="small" style="margin-top:10px;">
    Dicetak: {{ now()->format('d-m-Y H:i') }}
  </p>

</body>
</html>
