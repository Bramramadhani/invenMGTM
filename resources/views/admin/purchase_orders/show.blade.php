@extends('layouts.master', ['title' => 'Detail Purchase Order'])

@section('content')
<div class="container">

  @php
    $hasPostedReceipts = \App\Models\PurchaseReceipt::where('purchase_order_id', $purchaseOrder->id)
                        ->where('status','posted')->exists();

    $isFullFob = method_exists($purchaseOrder, 'isFullFob')
      ? $purchaseOrder->isFullFob()
      : (($purchaseOrder->stock_source ?? 'po') === 'fob_full');

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
    <div>
      <h4 class="mb-1">Detail Purchase Order — {{ $purchaseOrder->po_number }}</h4>
      <div class="small text-muted">
        Buyer: <strong>{{ optional($purchaseOrder->supplier)->name ?? '-' }}</strong>
      </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      @if(!$isFullFob)
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModalGlobal">
          <i class="fas fa-ban"></i> Reject Barang
        </button>
      @endif

      @if(!$isFullFob && $hasPostedReceipts)
        <a href="{{ route('admin.receipts.pdf-merged', $purchaseOrder) }}" class="btn btn-outline-secondary">
          <i class="fas fa-file-pdf"></i> Download PDF
        </a>
      @endif

      @if(!$isFullFob)
        <a href="{{ route('admin.receipts.create', $purchaseOrder) }}" class="btn btn-outline-success">
          <i class="fas fa-inbox"></i> Terima Barang
        </a>
      @endif
    </div>
  </div>

  @if($isFullFob)
    <div class="alert alert-info">
      <strong>PO FULL FOB</strong> ƒ?" PO ini tidak menggunakan penerimaan/receipt dan tidak memiliki item material di PO.
      Pengambilan barang dilakukan dari <strong>stok FOB</strong> melalui menu <strong>Permintaan Barang</strong>.
    </div>
  @endif

  {{-- INFO + STYLES --}}
  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <strong>Informasi Purchase Order</strong>
          <span>
            @if($purchaseOrder->is_completed ?? false)
              <span class="badge bg-success">SELESAI</span>
            @else
              <span class="badge bg-warning text-dark">BELUM SELESAI</span>
            @endif
            @if($isFullFob)
              <span class="badge bg-info text-dark ms-2">FULL FOB</span>
            @endif
          </span>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="text-muted small mb-1">Buyer</div>
              <div class="fw-semibold">{{ optional($purchaseOrder->supplier)->name ?? '-' }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small mb-1">Target Penyelesaian</div>
              <div>{{ optional($purchaseOrder->target_completion_date)->format('d-m-Y') ?? '-' }}</div>
            </div>
          </div>

          <div class="mt-3">
            <div class="text-muted small mb-1">Catatan PO</div>
            <div>{{ $purchaseOrder->notes ?? '-' }}</div>
          </div>
        </div>
      </div>
    </div>

    {{-- STYLES PO --}}
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <strong>Styles (Qty Tas)</strong>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="text-center" style="width:50px;">#</th>
                  <th>Nama Style</th>
                  <th class="text-center" style="width:120px;">Qty Tas</th>
                </tr>
              </thead>
              <tbody>
                @forelse($purchaseOrder->styles ?? [] as $i => $style)
                  <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $style->style_name }}</td>
                    <td class="text-center">{{ number_format($style->style_quantity) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted py-3">
                      Belum ada data style.
                    </td>
                  </tr>
                @endforelse
              </tbody>
              @if($purchaseOrder->styles && $purchaseOrder->styles->count() > 0)
                <tfoot>
                  <tr>
                    <th colspan="2" class="text-end">Total Qty Tas</th>
                    <th class="text-center">
                      {{ number_format($purchaseOrder->styles->sum('style_quantity')) }}
                    </th>
                  </tr>
                </tfoot>
              @endif
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if(!$isFullFob)
  {{-- DRAFT RECEIPT --}}
  @php
    $draftReceipts = \App\Models\PurchaseReceipt::with('items')
        ->where('purchase_order_id', $purchaseOrder->id)
        ->where('status', 'draft')
        ->orderBy('created_at','desc')
        ->get();
  @endphp

  @if($draftReceipts->isNotEmpty())
    <div class="card mb-4 border-warning">
      <div class="card-header bg-warning bg-opacity-25 d-flex justify-content-between align-items-center">
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

  {{-- RIWAYAT PENERIMAAN --}}
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
    <div class="card-header bg-light"><strong>Riwayat Penerimaan Barang per Tanggal</strong></div>
    <div class="card-body p-0">
      @foreach($postedByDate as $ymd => $rows)
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>No. Receipt</th>
                <th>Tanggal</th>
                <th>Ringkasan Item</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $rc)
                <tr>
                  <td>
                    {{ $rc->receipt_number }}
                    @if($rc->edited_at)
                      <span class="badge bg-warning text-dark ms-1">Dikoreksi</span>
                    @endif

                    @if($rc->notes)
                      <div class="small text-muted mt-1">
                        {{ Str::limit($rc->notes, 80) }}
                      </div>
                    @endif
                  </td>
                  <td>{{ optional($rc->receipt_date)->format('d-m-Y') }}</td>
                  <td>
                    {{ $rc->items->count() }} item, total:
                    <strong>{{ qty_fmt($rc->items->sum('received_quantity')) }}</strong>
                  </td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary js-open-receipt-modal"
                            data-target="receiptModal-{{ $rc->id }}">
                      <i class="fas fa-list"></i> Detail
                    </button>
                    <a href="{{ route('admin.receipts.correction.edit', $rc) }}"
                       class="btn btn-sm btn-warning ms-1">
                      <i class="fas fa-edit"></i> Koreksi
                    </a>
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

  @foreach($allReceipts as $rc)
    <div class="modal fade" id="receiptModal-{{ $rc->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ $rc->receipt_number }} — {{ optional($rc->receipt_date)->format('d-m-Y') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <table class="table table-bordered mb-0">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Material</th>
                  <th>Unit</th>
                  <th class="text-end">Qty</th>
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
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
  @endforeach
  @endif

  @endif

  {{-- ITEM PO --}}
  <div class="card mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
      <strong>Ringkasan Item PO</strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
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
              <th style="width:190px; text-align:center;">Catatan Reject</th>
            </tr>
          </thead>
          <tbody>
            @foreach($purchaseOrder->items as $item)
              @php
                $ordered = (float) $item->ordered_quantity;

                // Total diterima dari RECEIPT POSTED
                $receivedFromReceipts = (float) $item->receiptItems()
                    ->whereHas('receipt', fn ($q) => $q->where('status', 'posted'))
                    ->sum('received_quantity');

                // Fallback untuk data lama yang sudah terlanjur pakai summary
                $received = $receivedFromReceipts;
                if ($receivedFromReceipts <= 0 && (float) $item->actual_arrived_quantity > 0) {
                    $received = (float) $item->actual_arrived_quantity;
                }

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
                    <div class="d-inline-block text-start" style="max-width:170px; padding:2px 4px;">
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
    </div>
  </div>

  <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary mb-3">
    <i class="fas fa-arrow-left"></i> Kembali
  </a>
</div>

@if(!$isFullFob)
{{-- MODAL REJECT PER ITEM --}}
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

{{-- MODAL REJECT GLOBAL --}}
<div class="modal fade" id="rejectModalGlobal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form method="POST" action="{{ route('admin.purchase-orders.reject', $purchaseOrder->id) }}">
      @csrf
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Form Reject Barang</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                    $receivedFromReceipts = (float) $it->receiptItems()
                        ->whereHas('receipt', fn ($q) => $q->where('status', 'posted'))
                        ->sum('received_quantity');

                    $received = $receivedFromReceipts;
                    if ($receivedFromReceipts <= 0 && (float) $it->actual_arrived_quantity > 0) {
                        $received = (float) $it->actual_arrived_quantity;
                    }
                  @endphp
                  <tr>
                    <td>{{ $i+1 }}</td>
                    <td>{{ $it->material_name }}</td>
                    <td>{{ $it->unit }}</td>
                    <td class="text-end">{{ qty_fmt($received) }}</td>
                    <td style="width:150px">
                      <input type="number"
                             step="0.0001"
                             name="rejects[{{ $it->id }}][quantity]"
                             class="form-control form-control-sm"
                             min="0"
                             max="{{ $received }}"
                             placeholder="0">
                    </td>
                    <td>
                      <input type="text"
                             name="rejects[{{ $it->id }}][notes]"
                             class="form-control form-control-sm"
                             placeholder="Catatan (opsional)">
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

@endif

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
