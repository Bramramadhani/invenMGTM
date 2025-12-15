@extends('layouts.master', ['title' => 'Stok FOB (Buyer)'])

@section('content')
<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0">Stok FOB (Buyer)</h4>

    <div class="btn-group">
      <a href="{{ route('admin.fob-stocks.purchase-report') }}" class="btn btn-outline-primary">
        <i class="fas fa-file-invoice-dollar"></i> Laporan Pembelian FOB
      </a>
      <a href="{{ route('admin.fob-stocks.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Tambah Stok FOB
      </a>
    </div>
  </div>

  <form method="GET" class="mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small mb-1">Pencarian</label>
        <input type="text"
               name="q"
               value="{{ $q }}"
               class="form-control"
               placeholder="Cari material / kode">
      </div>

      <div class="col-md-3">
        <label class="form-label small mb-1">Filter Buyer</label>
        <select name="buyer_id" class="form-select">
          <option value="">— Semua Buyer —</option>
          @foreach($buyers as $b)
            <option value="{{ $b->id }}" {{ (string)$buyerId === (string)$b->id ? 'selected' : '' }}>
              {{ $b->name }} @if($b->code) ({{ $b->code }}) @endif
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-md-2">
        <button class="btn btn-outline-secondary w-100 mt-3">
          <i class="fas fa-search"></i> Cari
        </button>
      </div>
    </div>
  </form>

  @php
    if (!function_exists('qty_fmt')) {
      function qty_fmt($n, $dec = 4) {
        $s = number_format((float)$n, $dec, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
      }
    }
  @endphp

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:50px;" class="text-center">No</th>
              <th style="width:200px;">Buyer FOB</th>
              <th style="width:110px;">Kode</th>
              <th>Material</th>
              <th style="width:80px;" class="text-center">Unit</th>
              <th style="width:120px;" class="text-end">Qty</th>
              <th style="width:120px;" class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($stocks as $i => $s)
              <tr>
                <td class="text-center">{{ $stocks->firstItem() + $i }}</td>
                <td>
                  @if($s->buyer)
                    <div class="fw-semibold">{{ $s->buyer->name }}</div>
                    @if($s->buyer->code)
                      <div class="small text-muted">{{ $s->buyer->code }}</div>
                    @endif
                  @else
                    <div class="text-muted">—</div>
                  @endif

                  @if($s->vendor_name)
                    <div class="small text-muted">Vendor: {{ $s->vendor_name }}</div>
                  @endif
                </td>
                <td>{{ $s->material_code ?: '—' }}</td>
                <td>{{ $s->material_name }}</td>
                <td class="text-center">{{ $s->unit }}</td>
                <td class="text-end">{{ qty_fmt($s->quantity) }}</td>
                <td class="text-center">
                  <a href="{{ route('admin.fob-stocks.history', $s->id) }}" class="btn btn-sm btn-outline-secondary me-1">
                    <i class="fas fa-info-circle"></i> Detail
                  </a>
                  <a href="{{ route('admin.fob-stocks.edit', $s) }}" class="btn btn-sm btn-warning">
                    <i class="fas fa-edit"></i> Edit
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted py-3">
                  Belum ada stok FOB.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if($stocks->hasPages())
      <div class="card-footer">
        {{ $stocks->withQueryString()->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
