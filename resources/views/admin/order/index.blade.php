@extends('layouts.master', ['title' => 'Permintaan Barang'])

@section('content')
<div class="container">

  {{-- Header --}}
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Permintaan Barang</h4>
    <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">
      <i class="fas fa-plus"></i> Buat Permintaan
    </a>
  </div>

  {{-- Pencarian --}}
  <form method="get" action="{{ route('admin.orders.index') }}" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label">Cari</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
          <input
            type="text"
            name="q"
            class="form-control"
            value="{{ $q ?? '' }}"
            placeholder="Ketik nomor dokumen atau nama produksi"
            autocomplete="off"
          >
        </div>
      </div>
      <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">Reset</a>
      </div>
    </div>
  </form>

  {{-- Data Table --}}
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
          <thead class="table-light text-center align-middle">
            <tr>
              <th style="width:150px">Nomor Dokumen</th>
              <th style="width:160px">Nama Produksi</th>
              <th style="width:160px">Leader Produksi</th>
              <th style="width:140px">Checker Gudang</th>
              <th style="width:140px">Leader Gudang</th>
              <th style="width:150px">Supply&nbsp;Chain<br>Head</th>
              <th style="width:120px">Tanggal</th>
              <th style="width:90px">Jam</th>
              <th style="width:150px">Sumber Stok</th>
              <th style="width:120px">Status</th>
              <th style="width:220px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($orders as $o)
              @php
                $label = strtolower((string) $o->status);
                $badgeClass = match ($label) {
                  'menunggu konfirmasi' => 'bg-warning text-dark',
                  'terverifikasi'       => 'bg-info text-dark',
                  'selesai'             => 'bg-success',
                  default               => 'bg-secondary',
                };

                $sourceType  = $o->source_type ?? 'po';
                $sourceLabel = $sourceType === 'fob'
                  ? 'Stok FOB' . (optional($o->buyer)->name ? ' ('.optional($o->buyer)->name.')' : '')
                  : 'Stok PO / Buyer';
              @endphp
              <tr>
                <td class="fw-semibold text-center">{{ $o->name ?? '—' }}</td>
                <td class="text-center">{{ $o->production_name ?? '—' }}</td>
                <td class="text-center">{{ $o->production_leader_name ?? '—' }}</td>
                <td class="text-center">{{ $o->warehouse_admin_name ?? '—' }}</td>
                <td class="text-center">{{ $o->warehouse_leader_name ?? '—' }}</td>
                <td class="text-center">{{ $o->supply_chain_head_name ?? '—' }}</td>
                <td class="text-center">{{ optional($o->created_at)->format('d-m-Y') }}</td>
                <td class="text-center">{{ optional($o->created_at)->format('H:i') }}</td>
                <td class="text-center">{{ $sourceLabel }}</td>
                <td class="text-center">
                  <span class="badge {{ $badgeClass }}">{{ $o->status }}</span>
                </td>
                <td class="text-center">
                  <div class="btn-group">
                    <a href="{{ route('admin.orders.show', $o) }}" class="btn btn-sm btn-outline-primary">
                      <i class="fas fa-eye"></i> Detail
                    </a>
                    <a href="{{ route('admin.orders.edit', $o) }}" class="btn btn-sm btn-outline-warning">
                      <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="{{ route('admin.orders.receipt-pdf', $o) }}" class="btn btn-sm btn-outline-secondary">
                      <i class="fas fa-file-pdf"></i> PDF
                    </a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="11" class="text-center text-muted">Tidak ada data permintaan barang.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Pagination --}}
    <div class="card-footer">
      {{ $orders->withQueryString()->links() }}
    </div>
  </div>

</div>
@endsection
