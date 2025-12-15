@extends('layouts.master', ['title' => 'Laporan Stok'])

@section('content')
<style>
  .card-shadow { box-shadow: 0 2px 10px rgba(0,0,0,.04); }
  .table thead th { background:#f8f9fa; z-index:1; }
  .table thead th, .table td { vertical-align: middle; }
  .table td.text-truncate { max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .gap-8 { gap:.5rem; }
  .nowrap { white-space:nowrap; }

  :root { --control-h: calc(1.5em + .75rem + 2px); }

  .control-shell{
    height:var(--control-h);
    padding:0;
    display:flex; align-items:stretch;
    border:1px solid #ced4da; border-radius:.375rem; background:#fff;
  }
  .control-shell .segmented{width:100%;}
  .control-shell .segmented .btn{
    height:calc(var(--control-h) - 2px);
    border:none !important; border-right:1px solid #dee2e6 !important;
    margin:0; border-radius:0 !important;
  }
  .control-shell .segmented .btn:last-child{
    border-right:0 !important;
    border-top-right-radius:.375rem !important;
    border-bottom-right-radius:.375rem !important;
  }
  .control-shell .segmented .btn:first-of-type{
    border-top-left-radius:.375rem !important;
    border-bottom-left-radius:.375rem !important;
  }
  .segmented .btn-check:checked + .btn{ background:#0d6efd; color:#fff; }

  .form-label.small-label{ font-size:.82rem; color:#6c757d; margin-bottom:.25rem; }

  /* Enhanced supplier dropdown */
  .supplier-select {
    max-height: 38px;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right .75rem center;
    background-size: 16px 12px;
  }
  .supplier-select option[value=""] { font-weight:500; }
  .supplier-select optgroup {
    font-weight:400;
    color:#6c757d;
    font-style:normal;
    padding:4px 0;
  }
  .supplier-select option {
    padding:4px 12px;
    margin:2px 0;
  }
</style>

@php
  // smart formatter: int -> tanpa desimal, pecahan -> max 4 desimal (trim nol)
  if (!function_exists('fmt_qty')) {
      function fmt_qty($n, $dec = 4) {
          $s = number_format((float)$n, $dec, '.', '');
          $s = rtrim(rtrim($s, '0'), '.');
          return $s === '' ? '0' : $s;
      }
  }

  $f = $filters ?? [];
  $dateFrom   = $f['date_from']   ?? now()->startOfMonth()->toDateString();
  $dateTo     = $f['date_to']     ?? now()->endOfMonth()->toDateString();
  $supplierId = $f['supplier_id'] ?? null;
  $q          = $f['q']           ?? '';
  $type       = $f['type']        ?? 'all'; // all|in|out
@endphp

<div class="container-xxl">

  {{-- FILTER --}}
  <div class="card card-shadow mb-3">
    <div class="card-body py-3">
      <form id="reportFilter" action="{{ route('admin.reports.index') }}" method="GET" class="row g-3 align-items-end">

        <div class="col-md-2">
          <label class="form-label small-label">Dari</label>
          <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control js-auto">
        </div>

        <div class="col-md-2">
          <label class="form-label small-label">Sampai</label>
          <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control js-auto">
        </div>

        <div class="col-md-3">
          <label class="form-label small-label">Supplier</label>
          <select class="form-select js-auto supplier-select" name="supplier_id" style="width:100%">
            <option value="">— Semua Supplier —</option>

            @if($suppliers->take(10)->count() > 0)
              <optgroup label="Buyer Utama">
                @foreach ($suppliers->take(10) as $s)
                  <option value="{{ $s->id }}" {{ (string)$supplierId===(string)$s->id ? 'selected' : '' }}>
                    {{ $s->name }}
                  </option>
                @endforeach
              </optgroup>
            @endif

            @if($suppliers->count() > 10)
              <optgroup label="Supplier Lainnya">
                @foreach ($suppliers->slice(10) as $s)
                  <option value="{{ $s->id }}" {{ (string)$supplierId===(string)$s->id ? 'selected' : '' }}>
                    {{ $s->name }}
                  </option>
                @endforeach
              </optgroup>
            @endif
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label small-label">Jenis</label>
          <div class="control-shell">
            <div class="segmented btn-group" role="group" aria-label="Jenis pergerakan">
              <input type="radio" class="btn-check js-auto-radio" name="type" id="type_all" value="all" {{ $type==='all'?'checked':'' }}>
              <label class="btn btn-outline-secondary w-100" for="type_all">Semua</label>

              <input type="radio" class="btn-check js-auto-radio" name="type" id="type_in" value="in" {{ $type==='in'?'checked':'' }}>
              <label class="btn btn-outline-secondary w-100" for="type_in">IN</label>

              <input type="radio" class="btn-check js-auto-radio" name="type" id="type_out" value="out" {{ $type==='out'?'checked':'' }}>
              <label class="btn btn-outline-secondary w-100" for="type_out">OUT</label>
            </div>
          </div>
        </div>

        <div class="col-md-2">
          <label class="form-label small-label">Cari</label>
          <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Kode / Material / PO / ...">
        </div>

        <div class="col-12 d-flex justify-content-between align-items-center">
          <div class="form-check">
            <input class="form-check-input js-auto" type="checkbox" value="1" id="show_names" name="show_names"
                   {{ request('show_names') ? 'checked' : '' }}>
            <label class="form-check-label small" for="show_names">
              Tampilkan: <strong>Produksi / Leader Produksi / Checker / Leader Gudang / Supply Chain Head</strong>
            </label>
          </div>

          <div class="d-flex gap-8">
            <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary">
              <i class="fas fa-undo me-1"></i> Reset
            </a>
            <a
              href="{{ route('admin.reports.export', array_merge(
                request()->except(['page', 'out_page', 'in_page']),
                [
                  'date_from'   => $dateFrom,
                  'date_to'     => $dateTo,
                  'supplier_id' => $supplierId,
                  'q'           => $q,
                  'type'        => $type,
                  'show_names'  => request('show_names') ? 1 : 0,
                ]
              )) }}"
              class="btn btn-success"
            >
              <i class="fas fa-file-excel me-1"></i> Export Excel
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- KPI --}}
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card card-shadow">
        <div class="card-body">
          <div class="text-muted small mb-1">Total IN</div>
          <div class="fs-5 fw-semibold">{{ fmt_qty($totalInQty) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-shadow">
        <div class="card-body">
          <div class="text-muted small mb-1">Total OUT</div>
          <div class="fs-5 fw-semibold">{{ fmt_qty($totalOutQty) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-shadow">
        <div class="card-body">
          <div class="text-muted small mb-1">Net (IN - OUT)</div>
          <div class="fs-5 fw-semibold">{{ fmt_qty($netQty) }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- OUT --}}
  @if($type !== 'in')
    <div class="card card-shadow mb-4">
      <div class="card-header bg-white"><strong>Pengeluaran (OUT)</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped mb-0">
            <thead>
              <tr>
                <th class="nowrap" style="width:130px;">Tanggal</th>
                <th class="text-center" style="width:140px;">Supplier</th>
                <th class="text-center" style="width:120px;">No. PO</th>
                <th class="text-center" style="width:140px;">Style</th>
                <th class="text-center" style="width:90px;">Kode</th>
                <th class="text-center">Material</th>
                <th class="text-center" style="width:80px;">Unit</th>
                <th class="text-end" style="width:110px;">Qty</th>
                <th class="text-truncate">Catatan</th>
                @if(request('show_names'))
                  <th class="text-center" style="width:160px;">Checker Produksi</th>
                  <th class="text-center" style="width:160px;">Leader Produksi</th>
                  <th class="text-center" style="width:160px;">Checker Gudang</th>
                  <th class="text-center" style="width:160px;">Leader Gudang</th>
                  <th class="text-center" style="width:160px;">Supply Chain Head</th>
                @endif
              </tr>
            </thead>
            <tbody>
              @forelse ($outRows as $r)
                @php
                  $supplier  = $r->supplier->name ?? '—';
                  $code      = $r->stock->material_code ?? '—';
                  $unit      = $r->unit ?? ($r->stock->unit ?? '—');
                  $material  = $r->material_name ?? ($r->stock->material_name ?? '—');
                  $order     = $r->resolvedOrder;
                  $styleObj  = optional(optional($order)->purchaseOrderStyle);
                  $styleName = $styleObj->style_name
                    ?? $styleObj->name
                    ?? $styleObj->nama_style
                    ?? null;
                @endphp
                <tr>
                  <td class="nowrap">{{ optional($r->moved_at)->format('d-m-Y H:i') }}</td>
                  <td class="text-center">{{ $supplier }}</td>
                  <td class="text-center">{{ $r->po_number ?: '—' }}</td>
                  <td class="text-center">{{ $styleName ?: '—' }}</td>
                  <td class="text-center">{{ $code }}</td>
                  <td class="text-center">{{ $material }}</td>
                  <td class="text-center">{{ $unit ?: '—' }}</td>
                  <td class="text-end">{{ fmt_qty($r->quantity) }}</td>
                  <td class="text-truncate">{{ $r->notes ?: '—' }}</td>
                  @if(request('show_names'))
                    <td class="text-center">{{ optional($order)->production_name ?? '—' }}</td>
                    <td class="text-center">{{ optional($order)->production_leader_name ?? '—' }}</td>
                    <td class="text-center">{{ optional($order)->warehouse_admin_name ?? '—' }}</td>
                    <td class="text-center">{{ optional($order)->warehouse_leader_name ?? '—' }}</td>
                    <td class="text-center">{{ optional($order)->supply_chain_head_name ?? '—' }}</td>
                  @endif
                </tr>
              @empty
                <tr>
                  <td colspan="{{ request('show_names') ? 14 : 9 }}" class="text-center text-muted">
                    Tidak ada data.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- IN --}}
  @if($type !== 'out')
    <div class="card card-shadow mb-5">
      <div class="card-header bg-white"><strong>Pemasukan (IN)</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped mb-0">
            <thead>
              <tr>
                <th class="nowrap" style="width:130px;">Tanggal</th>
                <th class="text-center" style="width:140px;">Supplier</th>
                <th class="text-center" style="width:120px;">No. PO</th>
                <th class="text-center" style="width:90px;">Kode</th>
                <th class="text-center">Material</th>
                <th class="text-center" style="width:80px;">Unit</th>
                <th class="text-end" style="width:110px;">Qty</th>
                <th class="text-truncate">Catatan</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($inRows as $r)
                @php
                  $supplier = $r->supplier->name ?? '—';
                  $code     = $r->stock->material_code ?? '—';
                  $unit     = $r->unit ?? ($r->stock->unit ?? '—');
                  $material = $r->material_name ?? ($r->stock->material_name ?? '—');
                @endphp
                <tr>
                  <td class="nowrap">{{ optional($r->moved_at)->format('d-m-Y') }}</td>
                  <td class="text-center">{{ $supplier }}</td>
                  <td class="text-center">{{ $r->po_number ?: '—' }}</td>
                  <td class="text-center">{{ $code }}</td>
                  <td class="text-center">{{ $material }}</td>
                  <td class="text-center">{{ $unit ?: '—' }}</td>
                  <td class="text-end">{{ fmt_qty($r->quantity) }}</td>
                  <td class="text-truncate">{{ $r->notes ?: '—' }}</td>
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

<script>
  (function () {
    const form = document.getElementById('reportFilter');

    // Auto-submit untuk semua kontrol selain pencarian
    document.querySelectorAll('#reportFilter .js-auto').forEach(el => {
      el.addEventListener('change', () => form.requestSubmit());
    });
    document.querySelectorAll('#reportFilter .js-auto-radio').forEach(el => {
      el.addEventListener('change', () => form.requestSubmit());
    });

    // Input "Cari" => submit hanya ketika ENTER
    const q = form.querySelector('input[name="q"]');
    if (q) {
      q.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          form.requestSubmit();
        }
      });
    }
  })();
</script>
@endsection
