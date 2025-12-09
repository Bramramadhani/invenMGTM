@extends('layouts.master', ['title' => 'Detail Permintaan Barang'])

@section('content')
<div class="container">

  @php
    $label = strtolower((string) $order->status);
    $badgeClass = match ($label) {
      'menunggu konfirmasi' => 'bg-warning text-dark',
      'terverifikasi'       => 'bg-info text-dark',
      'selesai'             => 'bg-success',
      default               => 'bg-secondary',
    };

    $itemCount = $order->items->count();
    $totalQty  = $order->items->sum('quantity');

    // Style dari relasi ke PurchaseOrderStyle
    $style = optional($order->purchaseOrderStyle ?? null);

    $sourceType  = $order->source_type ?? 'po';
    $sourceLabel = $sourceType === 'fob' ? 'Stok FOB (Buyer)' : 'Stok PO / Supplier';
    $buyer       = $order->buyer;
  @endphp

  <style>
    .table-centered th,
    .table-centered td { text-align: center; vertical-align: middle; }
    .summary-chip {
      font-size: .85rem; padding: .35rem .65rem; border-radius: 999px;
      background: #f8f9fa; display: inline-flex; align-items: center; gap: .35rem;
    }
  </style>

  {{-- Header Judul --}}
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h4 class="mb-0">
      Detail Permintaan
      <span class="text-muted">—</span>
      <span class="fw-semibold">{{ $order->name ?? ('#'.$order->id) }}</span>
      <span class="badge {{ $badgeClass }} ms-2">{{ $order->status }}</span>
    </h4>

    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('admin.orders.receipt-pdf', $order) }}" class="btn btn-outline-secondary">
        <i class="fas fa-file-pdf"></i> Download Receipt PDF
      </a>

      {{-- Tombol Hapus + rollback stok --}}
      <form action="{{ route('admin.orders.destroy', $order) }}"
            method="post"
            onsubmit="return confirm('Yakin ingin menghapus permintaan ini?\nStok akan dikembalikan seperti sebelum permintaan dibuat.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger">
          <i class="fas fa-trash"></i> Hapus
        </button>
      </form>

      <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Kembali
      </a>
    </div>
  </div>

  {{-- Informasi Umum + Style --}}
  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      {{-- Baris 1: info dokumen --}}
      <div class="row g-3">
        <div class="col-md-3">
          <div class="text-muted small">Nomor Dokumen</div>
          <div class="fw-semibold">{{ $order->name ?? ('#'.$order->id) }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted small">Tanggal</div>
          <div class="fw-semibold">{{ optional($order->created_at)->format('d-m-Y') }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted small">Jam</div>
          <div class="fw-semibold">{{ optional($order->created_at)->format('H:i') }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted small">Dibuat oleh</div>
          <div class="fw-semibold">{{ optional($order->user)->name ?? '—' }}</div>
        </div>
      </div>

      <hr>

      {{-- Baris 2: produksi & gudang --}}
      <div class="row g-3">
        <div class="col-md-3">
          <div class="text-muted small">Nama Produksi</div>
          <div class="fw-semibold">{{ $order->production_name ?? '—' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted small">Leader Produksi</div>
          <div class="fw-semibold">{{ $order->production_leader_name ?? '—' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted small">Checker Gudang</div>
          <div class="fw-semibold">{{ $order->warehouse_admin_name ?? '—' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted small">Leader Gudang</div>
          <div class="fw-semibold">{{ $order->warehouse_leader_name ?? '—' }}</div>
        </div>
      </div>

      {{-- Baris 3: Supply Chain Head --}}
      <div class="row g-3 mt-2">
        <div class="col-md-3">
          <div class="text-muted small">Supply Chain Head</div>
          <div class="fw-semibold">{{ $order->supply_chain_head_name ?? '—' }}</div>
        </div>
      </div>

      {{-- Baris 4: Sumber Stok & Buyer --}}
      <div class="row g-3 mt-2">
        <div class="col-md-3">
          <div class="text-muted small">Sumber Stok</div>
          <div class="fw-semibold">{{ $sourceLabel }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted small">Buyer (FOB)</div>
          <div class="fw-semibold">{{ optional($buyer)->name ?? '—' }}</div>
        </div>
      </div>

      {{-- Baris 5: Style + ringkasan item (SEJAJAR) --}}
      <div class="row g-3 mt-3 align-items-center">
        <div class="col-md-3">
          <div class="text-muted small mb-1">Style</div>
          <div class="fw-semibold">
            {{ $style->name ?? $style->style_name ?? '—' }}
          </div>
        </div>
        <div class="col-md-9 d-flex justify-content-md-end mt-2 mt-md-0">
          <div class="d-flex flex-wrap gap-2">
            <div class="summary-chip">
              <i class="fas fa-list"></i>
              <span><strong>{{ $itemCount }}</strong> item permintaan</span>
            </div>
            <div class="summary-chip">
              <i class="fas fa-cubes"></i>
              <span>Total Qty: <strong>{{ fmt_number($totalQty) }}</strong></span>
            </div>
          </div>
        </div>
      </div>

      @if (!empty($order->notes))
        <hr>
        <div class="text-muted small mb-1">Catatan Tambahan</div>
        <div>{{ $order->notes }}</div>
      @endif
    </div>
  </div>

  {{-- Item permintaan --}}
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Item Permintaan</strong>
      <span class="small text-muted">Menampilkan {{ $itemCount }} baris</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 table-centered">
          <thead class="table-light">
            <tr>
              <th style="width:60px">No</th>
              <th style="width:140px">Kode</th>
              <th>Material</th>
              <th style="width:90px">Unit</th>
              <th style="width:180px">Supplier / Buyer</th>
              <th style="width:160px">No. PO (Stok)</th>
              <th style="width:140px">Qty Diminta</th>
              <th>Catatan Item</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($order->items as $it)
              @php
                $stock        = $it->stock;
                $po           = optional($stock?->purchaseOrder);
                $poId         = $po->id ?? null;
                $poNo         = $po->po_number ?? null;
                $supplierName = optional($stock?->supplier)->name;
                $buyerNameRow = optional($stock?->buyer)->name;
                $sourceCell   = $supplierName ?: $buyerNameRow ?: '—';
              @endphp
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $it->material_code ?: '—' }}</td>
                <td class="fw-semibold text-start">{{ $it->material_name }}</td>
                <td>{{ $it->unit }}</td>
                <td class="text-start">{{ $sourceCell }}</td>
                <td>
                  @if ($poId)
                    <a href="{{ route('admin.purchase-orders.show', $poId) }}">
                      {{ $poNo ?: 'PO #'.$poId }}
                    </a>
                  @else
                    —
                  @endif
                </td>
                <td>{{ fmt_number($it->quantity) }}</td>
                <td class="text-start">{{ $it->notes ?: '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center text-muted">Tidak ada item.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
