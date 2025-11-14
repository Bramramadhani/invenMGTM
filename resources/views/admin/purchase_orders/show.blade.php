@extends('layouts.master', ['title' => 'Detail Purchase Order'])

@section('content')
<div class="container">

  {{-- ALERT 
  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      <strong>Sukses!</strong> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show">
      <strong>Perhatian!</strong> {{ session('warning') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
      <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif --}}

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

  {{-- HEADER --}}
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h4 class="mb-0">Detail Purchase Order — {{ $purchaseOrder->po_number }}</h4>

    <div class="d-flex gap-2 flex-wrap">
      {{-- Tombol Reject Barang --}}
      <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModalGlobal">
        <i class="fas fa-ban"></i> Reject Barang
      </button>

      {{-- Download PDF --}}
      @if($hasPostedReceipts)
        <a href="{{ route('admin.receipts.pdf-merged', $purchaseOrder) }}" class="btn btn-outline-secondary">
          <i class="fas fa-file-pdf"></i> Download PDF
        </a>
      @endif

      {{-- Terima Barang --}}
      <a href="{{ route('admin.receipts.create', $purchaseOrder) }}" class="btn btn-outline-success">
        <i class="fas fa-inbox"></i> Terima Barang
      </a>
    </div>
  </div>

  {{-- INFORMASI PO --}}
  <div class="card mb-4">
    <div class="card-body">
      <p class="mb-2"><strong>Supplier:</strong> {{ optional($purchaseOrder->supplier)->name ?? '-' }}</p>
      <p class="mb-2"><strong>Target Penyelesaian:</strong> {{ optional($purchaseOrder->target_completion_date)->format('d-m-Y') ?? '-' }}</p>
      <p class="mb-0"><strong>Catatan PO:</strong> {{ $purchaseOrder->notes ?? '-' }}</p>
    </div>
  </div>

  {{-- ========================= --}}
  {{-- DRAFT RECEIPT SECTION (BARU DITAMBAHKAN) --}}
  {{-- Menampilkan draft penerimaan (status = draft) dengan tombol Posting & Hapus --}}
  {{-- ========================= --}}
  @php
    $draftReceipts = \App\Models\PurchaseReceipt::with('items')
        ->where('purchase_order_id', $purchaseOrder->id)
        ->where('status', 'draft')
        ->orderBy('created_at','desc')
        ->get();
  @endphp

  @if($draftReceipts->isNotEmpty())
    <div class="card mb-4 border-warning">
      <div class="card-header bg-warning bg-opacity-25">
        <strong>Draft Penerimaan Barang (Belum Diposting)</strong>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>No. Draft</th>
                <th>Tanggal Input</th>
                <th>Jumlah Item</th>
                <th>Total Qty</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @foreach($draftReceipts as $rc)
                <tr>
                  <td>{{ $rc->receipt_number ?? '(Draft)' }}</td>
                  <td>{{ optional($rc->created_at)->format('d-m-Y H:i') }}</td>
                  <td>{{ $rc->items->count() }} item</td>
                  <td>{{ qty_fmt($rc->items->sum('received_quantity')) }}</td>
                  <td class="text-end">
                    <form method="POST" action="{{ route('admin.receipts.post', $rc) }}" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-upload"></i> Posting
                      </button>
                    </form>
                    <form method="POST" action="{{ route('admin.receipts.delete', $rc) }}" class="d-inline" onsubmit="return confirm('Yakin hapus draft ini?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash"></i> Hapus
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif
  {{-- ========================= --}}
  {{-- END DRAFT RECEIPT SECTION --}}
  {{-- ========================= --}}

  {{-- RIWAYAT PENERIMAAN BARANG PER TANGGAL --}}
  @php
    $postedByDate = \App\Models\PurchaseReceipt::with('items')
        ->where('purchase_order_id', $purchaseOrder->id)
        ->where('status', 'posted')
        ->orderBy('receipt_date','desc')
        ->get()
        ->groupBy(fn($r) => optional($r->receipt_date)->format('Y-m-d'));
    $allReceipts = $postedByDate->flatten(1);
  @endphp

  @if($postedByDate->isNotEmpty())
  <div class="card mb-4">
    <div class="card-header"><strong>Riwayat Penerimaan Barang per Tanggal</strong></div>
    <div class="card-body p-0">
      @foreach($postedByDate as $ymd => $rows)
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>No. Receipt</th>
                <th>Tanggal</th>
                <th>Ringkasan Item</th>
                <th class="text-end">Detail</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $rc)
                <tr>
                  <td>{{ $rc->receipt_number }}</td>
                  <td>{{ optional($rc->receipt_date)->format('d-m-Y') }}</td>
                  <td>{{ $rc->items->count() }} item, total: <strong>{{ qty_fmt($rc->items->sum('received_quantity')) }}</strong></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary js-open-receipt-modal" data-target="receiptModal-{{ $rc->id }}">
                      <i class="fas fa-list"></i> Detail
                    </button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @if(!$loop->last)<hr class="my-0">@endif
      @endforeach
    </div>
  </div>

  {{-- Modal Detail per Receipt --}}
  @foreach($allReceipts as $rc)
    <div class="modal fade" id="receiptModal-{{ $rc->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ $rc->receipt_number }} — {{ optional($rc->receipt_date)->format('d-m-Y') }}</h5>
          </div>
          <div class="modal-body">
            <table class="table table-bordered mb-0">
              <thead><tr><th>No</th><th>Material</th><th>Unit</th><th class="text-end">Qty</th><th>Catatan</th></tr></thead>
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
          <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
        </div>
      </div>
    </div>
  @endforeach
  @endif

  {{-- ITEM PO --}}
<div class="table-responsive mt-4">
  <table class="table table-bordered align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th style="width:45px;">No</th>
        <th style="min-width:140px;">Material</th>
        <th style="width:70px;">Unit</th>
        <th class="text-end" style="width:90px;">Qty Order</th>
        <th class="text-end" style="width:90px;">Diterima</th>
        <th class="text-end" style="width:90px;">Balance</th>
        <th class="text-end" style="width:90px;">Rejected</th>
        <th style="width:170px; text-align:center;">Catatan Reject</th>
      </tr>
    </thead>
    <tbody>
      @foreach($purchaseOrder->items as $item)
        @php
          $ordered  = (float)$item->ordered_quantity;
          $received = (float)$item->receiptItems()
                      ->whereHas('receipt', fn($q)=>$q->where('status','posted'))
                      ->sum('received_quantity');
          $rejected = \App\Models\PurchaseOrderReject::where('purchase_order_item_id', $item->id)->sum('reject_quantity');
          $remaining = max(0, $ordered - $received);
          $lastReject = \App\Models\PurchaseOrderReject::where('purchase_order_item_id', $item->id)
                        ->latest('rejected_at')->first();
        @endphp
        <tr>
          <td class="text-center">{{ $loop->iteration }}</td>
          <td>{{ $item->material_name }}</td>
          <td class="text-center">{{ $item->unit }}</td>
          <td class="text-end">{{ qty_fmt($ordered) }}</td>
          <td class="text-end">{{ qty_fmt($received) }}</td>
          <td class="text-end">{{ qty_fmt($remaining) }}</td>
          <td class="text-end text-danger fw-semibold">{{ qty_fmt($rejected) }}</td>
          <td class="align-middle text-center" style="vertical-align: middle;">
            @if($lastReject)
              <div class="d-inline-block text-start" style="max-width:150px; padding:2px 4px;">
                <div class="small text-muted mb-1" style="line-height:1.3;">
                  {{ Str::limit($lastReject->new_notes ?? '—', 45) }}<br>
                  <em>{{ $lastReject->rejected_at->format('d/m/Y H:i') }}</em>
                </div>
                <button type="button"
                        class="btn btn-sm btn-outline-primary w-100"
                        style="font-size: 0.75rem; padding: 2px 0;"
                        data-bs-toggle="modal"
                        data-bs-target="#rejectHistoryModal-{{ $item->id }}">
                  <i class="fas fa-history"></i> Lihat Semua
                </button>
              </div>
            @else
              <span class="text-muted">—</span>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

  <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary mt-3">
    <i class="fas fa-arrow-left"></i> Kembali
  </a>
</div>

{{-- === MODAL REJECT HISTORY PER ITEM === --}}
@foreach($purchaseOrder->items as $item)
  @php
    $rejects = \App\Models\PurchaseOrderReject::where('purchase_order_item_id', $item->id)
                ->orderByDesc('rejected_at')->get();
  @endphp
  <div class="modal fade" id="rejectHistoryModal-{{ $item->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title">Riwayat Reject — {{ $item->material_name }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          @if($rejects->count())
            <table class="table table-bordered">
              <thead class="table-light">
                <tr>
                  <th>No</th>
                  <th>Tanggal Reject</th>
                  <th>Jumlah</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                @foreach($rejects as $i => $r)
                  <tr>
                    <td>{{ $i+1 }}</td>
                    <td>{{ $r->rejected_at->format('d/m/Y H:i') }}</td>
                    <td>{{ qty_fmt($r->reject_quantity) }} {{ $r->unit }}</td>
                    <td>{{ $r->new_notes ?? '-' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @else
            <p class="text-muted mb-0">Belum ada riwayat reject untuk item ini.</p>
          @endif
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>
@endforeach

{{-- === MODAL GLOBAL REJECT === --}}
<div class="modal fade" id="rejectModalGlobal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form method="POST" action="{{ route('admin.purchase-orders.reject', $purchaseOrder->id) }}">
      @csrf
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Form Reject Barang</h5>
        </div>
        <div class="modal-body">
          <p class="text-muted">Masukkan jumlah barang yang direject dan alasan/catatan.</p>
          <div class="table-responsive">
            <table class="table table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th>No</th>
                  <th>Material</th>
                  <th>Unit</th>
                  <th>Qty Diterima</th>
                  <th>Qty Reject</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                @foreach($purchaseOrder->items as $i => $it)
                  @php
                    $received = (float)$it->receiptItems()
                      ->whereHas('receipt', fn($q)=>$q->where('status','posted'))
                      ->sum('received_quantity');
                  @endphp
                  <tr>
                    <td>{{ $i+1 }}</td>
                    <td>{{ $it->material_name }}</td>
                    <td>{{ $it->unit }}</td>
                    <td class="text-end">{{ qty_fmt($received) }}</td>
                    <td style="width:150px">
                      <input type="number" step="0.0001" name="rejects[{{ $it->id }}][quantity]" class="form-control form-control-sm" min="0" max="{{ $received }}" placeholder="0">
                    </td>
                    <td>
                      <input type="text" name="rejects[{{ $it->id }}][notes]" class="form-control form-control-sm" placeholder="Catatan (opsional)">
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Simpan Reject</button>
        </div>
      </div>
    </form>
  </div>
</div>

@endsection

@push('js')
<script>
document.addEventListener('click', function(e){
  const btn = e.target.closest('.js-open-receipt-modal');
  if (!btn) return;
  const id = btn.getAttribute('data-target');
  const el = document.getElementById(id);
  if (window.bootstrap?.Modal) new bootstrap.Modal(el).show();
});
</script>
@endpush
