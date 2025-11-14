@extends('layouts.master', ['title' => 'Detail Permintaan Barang'])

@section('content')
<div class="container">

  @php
    // Status disimpan sebagai: Menunggu Konfirmasi | Terverifikasi | Selesai
    $label = strtolower((string) $order->status);
    $badgeClass = match ($label) {
      'menunggu konfirmasi' => 'bg-warning text-dark',
      'terverifikasi'       => 'bg-info text-dark',
      'selesai'             => 'bg-success',
      default               => 'bg-secondary',
    };
  @endphp

  <style>
    /* Pusatkan semua sel di tabel item */
    .table-centered th,
    .table-centered td { text-align: center; }
  </style>

  {{-- Header Judul --}}
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">
      Detail Permintaan
      <span class="text-muted">—</span>
      <span class="fw-semibold">{{ $order->name ?? ('#'.$order->id) }}</span>
      <span class="badge {{ $badgeClass }} ms-2">{{ $order->status }}</span>
    </h4>

    <div class="d-flex gap-2">
      <a href="{{ route('admin.orders.receipt-pdf', $order) }}" class="btn btn-outline-secondary">
        <i class="fas fa-file-pdf"></i> Download Receipt PDF
      </a>
      <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Kembali
      </a>
    </div>
  </div>

  {{-- Informasi Umum --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <div class="text-muted">Nomor Dokumen</div>
          <div class="fw-semibold">{{ $order->name ?? ('#'.$order->id) }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Tanggal</div>
          <div class="fw-semibold">{{ optional($order->created_at)->format('d-m-Y') }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Jam</div>
          <div class="fw-semibold">{{ optional($order->created_at)->format('H:i') }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Dibuat oleh</div>
          <div class="fw-semibold">{{ optional($order->user)->name ?? '—' }}</div>
        </div>
      </div>

      <hr>

      <div class="row g-3">
        <div class="col-md-4">
          <div class="text-muted">Nama Produksi</div>
          <div class="fw-semibold">{{ $order->production_name ?? '—' }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Checker Gudang</div>
          <div class="fw-semibold">{{ $order->warehouse_admin_name ?? '—' }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Leader Gudang</div>
          <div class="fw-semibold">{{ $order->warehouse_leader_name ?? '—' }}</div>
        </div>
      </div>

      @if (!empty($order->notes))
        <hr>
        <div class="text-muted">Catatan Tambahan</div>
        <div>{{ $order->notes }}</div>
      @endif
    </div>
  </div>

  {{-- Item permintaan --}}
  <div class="card">
    <div class="card-header">
      <strong>Item Permintaan</strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 table-centered">
          <thead class="table-light">
            <tr>
              <th style="width:60px">No</th>
              <th style="width:160px">Kode</th>
              <th>Material</th>
              <th style="width:110px">Unit</th>
              <th>Supplier</th>
              <th style="width:180px">No. PO</th>
              <th style="width:150px">Qty Diminta</th>
              <th>Catatan Item</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($order->items as $it)
              @php
                $stock  = $it->stock;
                $poId   = optional($stock)->last_po_id;
                $poNo   = optional($stock)->last_po_number ?? optional(optional($stock)->lastPo)->po_number;
              @endphp
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $it->material_code ?: '—' }}</td>
                <td class="fw-semibold">{{ $it->material_name }}</td> {{-- sekarang center --}}
                <td>{{ $it->unit }}</td>
                <td>{{ optional(optional($stock)->supplier)->name ?? '—' }}</td> {{-- sekarang center --}}
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
