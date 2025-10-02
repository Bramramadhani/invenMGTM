@extends('layouts.master', ['title' => 'Detail Purchase Order'])

@section('content')
<div class="container">

  {{-- Alerts --}}
  @if (session('success'))  <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if (session('warning'))  <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
    </div>
  @endif

  @php
    $hasPostedReceipts = \App\Models\PurchaseReceipt::where('purchase_order_id', $purchaseOrder->id)
                          ->where('status','posted')->exists();

    if (!function_exists('qty_fmt')) {
      function qty_fmt($n, $dec = 4) {
        $s = number_format((float)$n, $dec, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
      }
    }
  @endphp

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Detail Purchase Order — {{ $purchaseOrder->po_number }}</h4>
    <div class="d-flex gap-2">
      @if($hasPostedReceipts)
        <a href="{{ route('admin.receipts.pdf-merged', $purchaseOrder) }}" class="btn btn-outline-secondary">
          <i class="fas fa-file-pdf"></i> Download PDF
        </a>
      @endif
      <a href="{{ route('admin.receipts.create', $purchaseOrder) }}" class="btn btn-outline-success">
        <i class="fas fa-inbox"></i> Terima Barang
      </a>
    </div>
  </div>

  {{-- Info PO --}}
  <div class="card mb-4">
    <div class="card-body">
      <p class="mb-2"><strong class="me-1">Supplier:</strong> {{ optional($purchaseOrder->supplier)->name ?? '-' }}</p>
      <p class="mb-2"><strong class="me-1">Target Penyelesaian:</strong> {{ optional($purchaseOrder->target_completion_date)->format('d-m-Y') ?? '-' }}</p>
      <p class="mb-0"><strong class="me-1">Catatan PO:</strong> {{ $purchaseOrder->notes ?? '-' }}</p>
    </div>
  </div>

  {{-- Draft Receipts --}}
  @php
    $draftReceipts = \App\Models\PurchaseReceipt::where('purchase_order_id', $purchaseOrder->id)
                      ->where('status','draft')->orderBy('id','desc')->get();
  @endphp
  @if($draftReceipts->count())
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Draft Receipts</strong>
        <small class="text-muted">Posting untuk menambah stok</small>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:180px">No. Receipt</th>
                <th style="width:140px">Tanggal</th>
                <th>Ringkasan Item</th>
                <th class="text-end" style="width:200px">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @foreach($draftReceipts as $rc)
                @php
                  $sumQty = (float) $rc->items()->sum('received_quantity');
                  $rows   = (int) $rc->items()->count();
                @endphp
                <tr>
                  <td>{{ $rc->receipt_number }}</td>
                  <td>{{ optional($rc->receipt_date)->format('d-m-Y') }}</td>
                  <td>{{ $rows }} baris, total diterima: <strong>{{ qty_fmt($sumQty) }}</strong></td>
                  <td class="text-end">
                    <div class="d-flex gap-2 justify-content-end">
                      <form method="post" action="{{ route('admin.receipts.delete', $rc) }}"
                            onsubmit="return confirm('Batalkan penerimaan barang ini?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Batalkan</button>
                      </form>
                      <form method="post" action="{{ route('admin.receipts.post', $rc) }}"
                            onsubmit="return confirm('Posting receipt ini? Stok akan bertambah.');">
                        @csrf
                        <button class="btn btn-sm btn-success"><i class="fas fa-check"></i> Posting</button>
                      </form>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- Riwayat Penerimaan (POSTED) --}}
  @php
    $postedByDate = \App\Models\PurchaseReceipt::with('items')
        ->where('purchase_order_id', $purchaseOrder->id)
        ->where('status', 'posted')
        ->orderBy('receipt_date','desc')->orderBy('id','desc')
        ->get()
        ->groupBy(fn($r) => optional($r->receipt_date)->format('Y-m-d'));

    // daftar rata untuk render modal di luar tabel
    $allReceipts = $postedByDate->flatten(1);
  @endphp

  @if($postedByDate->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header">
        <strong>Riwayat Penerimaan</strong>
      </div>

      <div class="card-body p-0">
        @foreach($postedByDate as $ymd => $rows)
          @php $dateLabel = \Carbon\Carbon::parse($ymd)->format('d-m-Y'); @endphp

          <div class="table-responsive">
            <table class="table mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width:180px">No. Receipt</th>
                  <th style="width:140px">Tanggal</th>
                  <th>Ringkasan Item</th>
                  <th style="width:120px" class="text-end">Detail</th>
                </tr>
              </thead>
              <tbody>
                @foreach($rows as $rc)
                  @php
                    $sumQty  = (float) $rc->items->sum('received_quantity');
                    $rowCnt  = (int) $rc->items->count();
                    $modalId = "receiptModal-{$rc->id}";
                  @endphp
                  <tr>
                    <td class="fw-semibold">{{ $rc->receipt_number }}</td>
                    <td>{{ optional($rc->receipt_date)->format('d-m-Y') ?? '-' }}</td>
                    <td>{{ $rowCnt }} baris, total diterima: <strong>{{ qty_fmt($sumQty) }}</strong></td>
                    <td class="text-end">
                      <button type="button"
                              class="btn btn-sm btn-outline-primary js-open-receipt-modal"
                              data-target="{{ $modalId }}">
                        <i class="fas fa-list"></i> Detail
                      </button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          @if(!$loop->last) <hr class="my-0"> @endif
        @endforeach
      </div>
    </div>

    {{-- Semua modal dirender di luar tabel --}}
    @foreach($allReceipts as $rc)
      @php
        $modalId = "receiptModal-{$rc->id}";
        $dateLbl = optional($rc->receipt_date)->format('d-m-Y') ?? '-';
      @endphp
      <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="label-{{ $modalId }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="label-{{ $modalId }}">
                Tanggal: {{ $dateLbl }} — {{ $rc->receipt_number }}
              </h5>
              {{-- X DIHILANGKAN: tidak ada tombol close di header --}}
            </div>

            <div class="modal-body">
              <div class="mb-3 small text-muted">
                Nomor Receipt: <strong>{{ $rc->receipt_number }}</strong>
                @if($rc->notes) — Catatan: {{ $rc->notes }} @endif
              </div>

              <div class="table-responsive">
                <table class="table table-bordered mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width:60px">No</th>
                      <th>Material</th>
                      <th style="width:90px">Unit</th>
                      <th class="text-end" style="width:160px">Qty Diterima</th>
                      <th>Catatan</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($rc->items as $i => $it)
                      <tr>
                        <td>{{ $i+1 }}</td>
                        <td>{{ $it->material_name }}</td>
                        <td>{{ $it->unit }}</td>
                        <td class="text-end">{{ qty_fmt($it->received_quantity) }}</td>
                        <td>{{ $it->notes ?: '—' }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                Tutup
              </button>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  @endif

  {{-- Tabel item PO --}}
  <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:60px">No</th>
          <th>Material</th>
          <th style="width:120px">Unit</th>
          <th class="text-end" style="width:160px">Qty Orders</th>
          <th class="text-end" style="width:180px">Sudah Diterima</th>
          <th class="text-end" style="width:160px">Balance</th>
        </tr>
      </thead>
      <tbody>
        @forelse($purchaseOrder->items as $item)
          @php
            $ordered   = (float) $item->ordered_quantity;
            $received  = (float) $item->receiptItems()
                              ->whereHas('receipt', fn($q) => $q->where('status','posted'))
                              ->sum('received_quantity');
            $remaining = max(0, $ordered - $received);
          @endphp
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $item->material_name }}</td>
            <td>{{ $item->unit }}</td>
            <td class="text-end">{{ qty_fmt($ordered) }}</td>
            <td class="text-end">
              {{ qty_fmt($received) }}
              @if($received > $ordered) <span class="badge bg-danger ms-2">Over</span> @endif
            </td>
            <td class="text-end">{{ qty_fmt($remaining) }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted">Tidak ada data item</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary mt-3">
    <i class="fas fa-arrow-left"></i> Kembali
  </a>
</div>
@endsection

@push('css')
<style>
  /* Modal di atas sidebar/header */
  .modal { z-index: 1080; }
  .modal-backdrop { z-index: 1075; }
</style>
@endpush

@push('js')
<script>
(function () {
  // Buka modal (kompatibel Bootstrap 5 & 4)
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-open-receipt-modal');
    if (!btn) return;
    const id = btn.getAttribute('data-target');
    const el = document.getElementById(id);
    if (!el) return;

    if (window.bootstrap && window.bootstrap.Modal) {
      new bootstrap.Modal(el, {backdrop: true, keyboard: true}).show();
    } else if (window.jQuery && typeof jQuery.fn.modal === 'function') {
      jQuery(el).modal('show');
    }
  });
})();
</script>
@endpush
