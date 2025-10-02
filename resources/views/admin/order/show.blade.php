@extends('layouts.master', ['title' => 'Detail Permintaan Barang'])

@section('content')
<div class="container">

  {{-- Flash --}}
  @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Periksa kembali:</div>
      <ul class="mb-0">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

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

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">
      Detail Permintaan
      <span class="text-muted">—</span>
      <span class="fw-semibold">{{ $order->name ?? ('#'.$order->id) }}</span>
      <span class="badge {{ $badgeClass }} ms-2">{{ $order->status }}</span>
    </h4>

    <div class="d-flex gap-2">
      {{-- Download Receipt PDF --}}
      <a href="{{ route('admin.orders.receipt-pdf', $order) }}" class="btn btn-outline-secondary">
        <i class="fas fa-file-pdf"></i> Download Receipt PDF
      </a>
      {{-- Kembali --}}
      <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Kembali
      </a>
    </div>
  </div>

  {{-- Ringkasan: pisahkan Tanggal & Jam --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <div class="text-muted">Dibuat oleh</div>
          <div class="fw-semibold">{{ optional($order->user)->name ?? '—' }}</div>
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
          <div class="text-muted">Nomor Dokumen</div>
          <div class="fw-semibold">{{ $order->name ?? ('#'.$order->id) }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Item permintaan: tampilkan kode material & catatan per item --}}
  <div class="card">
    <div class="card-header">
      <strong>Item Permintaan</strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:60px">No</th>
              <th style="width:160px">Kode</th>
              <th>Material</th>
              <th style="width:110px">Unit</th>
              <th>Supplier</th>
              <th style="width:180px">No. PO</th>
              <th class="text-end" style="width:150px">Qty Diminta</th>
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
                <td class="fw-semibold">{{ $it->material_name }}</td>
                <td>{{ $it->unit }}</td>
                <td>{{ optional(optional($stock)->supplier)->name ?? '—' }}</td>
                <td>
                  @if ($poId)
                    <a href="{{ route('admin.purchase-orders.show', $poId) }}">
                      {{ $poNo ?: 'PO #'.$poId }}
                    </a>
                  @else
                    —
                  @endif
                </td>
                <td class="text-end">{{ fmt_number($it->quantity) }}</td>
                <td>{{ $it->notes ?: '—' }}</td>
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
