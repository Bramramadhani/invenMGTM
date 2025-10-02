@extends('layouts.master', ['title' => 'Laporan'])

@section('content')
<div class="container">

  {{-- Alerts --}}
  @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

  @php
    if (!function_exists('qty_fmt')) {
      function qty_fmt($n, $dec = 4) {
        $s = number_format((float)$n, $dec, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
      }
    }
    $f = $filters ?? [];
  @endphp

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Laporan Pergerakan Stok</h4>
  </div>

  {{-- FILTERS --}}
  <form method="get" action="{{ route('admin.reports.index') }}" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Dari Tanggal</label>
        <input type="date" name="date_from" class="form-control"
               value="{{ $f['date_from'] ?? '' }}" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Sampai Tanggal</label>
        <input type="date" name="date_to" class="form-control"
               value="{{ $f['date_to'] ?? '' }}" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Supplier</label>
        <select name="supplier_id" class="form-select">
          <option value="">Semua Supplier</option>
          @foreach($suppliers as $s)
            <option value="{{ $s->id }}" @selected(($f['supplier_id'] ?? '')==$s->id)>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Jenis</label>
        <select name="type" class="form-select">
          <option value="all" @selected(($f['type'] ?? 'all')==='all')>Semua</option>
          <option value="in"  @selected(($f['type'] ?? 'all')==='in')>Penerimaan (IN)</option>
          <option value="out" @selected(($f['type'] ?? 'all')==='out')>Pengeluaran (OUT)</option>
        </select>
      </div>

      <div class="col-md-9">
        <label class="form-label">Cari</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
     <input type="text" name="q" class="form-control"
       value="{{ $f['q'] ?? '' }}"
       placeholder="Cari berdasarkan kode, material, unit, supplier, atau No. PO">
        </div>
      </div>

      <div class="col-md-3 text-md-end mt-2 mt-md-0">
        <button class="btn btn-primary"><i class="fas fa-filter"></i> Terapkan</button>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary">Reset</a>
      </div>
    </div>
  </form>

  {{-- KPI CARDS --}}
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Total Penerimaan (IN)</div>
          <div class="h3 mb-0">{{ qty_fmt($totalInQty) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Total Pengeluaran (OUT)</div>
          <div class="h3 mb-0">{{ qty_fmt($totalOutQty) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Net Movement (IN - OUT)</div>
          <div class="h3 mb-0">{{ qty_fmt($netQty) }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- EXPORT --}}
  <div class="mb-3">
    <a class="btn btn-outline-secondary"
       href="{{ route('admin.reports.export', [
          'date_from'   => $f['date_from'] ?? '',
          'date_to'     => $f['date_to'] ?? '',
          'supplier_id' => $f['supplier_id'] ?? '',
          'q'           => $f['q'] ?? '',
          'type'        => $f['type'] ?? 'all',
       ]) }}">
      <i class="fas fa-file-export"></i> Export CSV
    </a>
  </div>

  {{-- TABEL IN --}}
  @if(($f['type'] ?? 'all') !== 'out')
    <div class="card mb-3">
      <div class="card-header"><strong>Penerimaan (IN)</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:160px">Tanggal</th>
                <th>Supplier</th>
                <th style="width:140px">No. PO</th>
                <th style="width:140px">Kode</th>
                <th>Material</th>
                <th style="width:100px">Unit</th>
                <th class="text-end" style="width:140px">Qty</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody>
              @forelse($inRows as $r)
                <tr>
                  {{-- IN: show date only (no time) --}}
                  <td>{{ optional($r->moved_at)->format('d-m-Y') }}</td>
                  <td>{{ optional($r->supplier)->name ?? '—' }}</td>
                  <td>{{ $r->po_number ?? '—' }}</td>
                  <td>{{ optional($r->stock)->material_code ?? '—' }}</td>
                  <td class="fw-semibold">{{ $r->material_name ?? optional($r->stock)->material_name ?? $r->material }}</td>
                  <td>{{ $r->unit ?? (optional($r->stock)->unit ?? '—') }}</td>
                  <td class="text-end">{{ qty_fmt($r->quantity) }}</td>
                  <td>{{ $r->notes ?? '—' }}</td>
                </tr>
              @empty
                <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- TABEL OUT --}}
  @if(($f['type'] ?? 'all') !== 'in')
    <div class="card">
      <div class="card-header"><strong>Pengeluaran (OUT)</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:160px">Tanggal</th>
                <th>Supplier</th>
                <th style="width:140px">No. PO</th>
                <th style="width:140px">Kode</th>
                <th>Material</th>
                <th style="width:100px">Unit</th>
                <th class="text-end" style="width:140px">Qty</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody>
              @forelse($outRows as $r)
                <tr>
                  {{-- OUT: show date+time --}}
                  <td>{{ optional($r->moved_at)->format('d-m-Y H:i') }}</td>
                  <td>{{ optional($r->supplier)->name ?? '—' }}</td>
                  <td>{{ $r->po_number ?? '—' }}</td>
                  <td>{{ optional($r->stock)->material_code ?? '—' }}</td>
                  <td class="fw-semibold">{{ $r->material_name ?? optional($r->stock)->material_name ?? $r->material }}</td>
                  <td>{{ $r->unit ?? (optional($r->stock)->unit ?? '—') }}</td>
                  <td class="text-end">{{ qty_fmt($r->quantity) }}</td>
                  <td>{{ $r->notes ?? '—' }}</td>
                </tr>
              @empty
                <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

</div>
@endsection
