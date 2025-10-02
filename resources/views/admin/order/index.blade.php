@extends('layouts.master', ['title' => 'Permintaan Barang'])

@section('content')
<div class="container">

  {{-- Flash --}}
  @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Permintaan Barang</h4>
    <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">
      <i class="fas fa-plus"></i> Buat Permintaan
    </a>
  </div>

  {{-- Search --}}
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
            placeholder="Ketik nomor dokumen"
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

  {{-- Table --}}
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:220px">Nomor Dokumen</th>
              <th>Dibuat Oleh</th>
              <th style="width:130px">Tanggal</th>
              <th style="width:100px">Jam</th>
              <th style="width:140px">Status</th>
              <th style="width:210px">Aksi</th>
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
              @endphp
              <tr>
                <td>
                  <div class="fw-semibold">{{ $o->name ?? '' }}</div>
                </td>
                <td>{{ optional($o->user)->name ?? '—' }}</td>
                <td>{{ optional($o->created_at)->format('d-m-Y') }}</td>
                <td>{{ optional($o->created_at)->format('H:i') }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ $o->status }}</span></td>
                <td class="text-center">
                  <div class="btn-group">
                    <a href="{{ route('admin.orders.show', $o) }}" class="btn btn-sm btn-outline-primary">
                      <i class="fas fa-eye"></i> Detail
                    </a>
                    <a href="{{ route('admin.orders.receipt-pdf', $o) }}" class="btn btn-sm btn-outline-secondary">
                      <i class="fas fa-file-pdf"></i> PDF
                    </a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted">Tidak ada data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer">
      {{ $orders->withQueryString()->links() }}
    </div>
  </div>
</div>
@endsection
