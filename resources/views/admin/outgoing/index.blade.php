@extends('layouts.master', ['title' => 'Barang Keluar'])

@section('content')
<div class="container">

  {{-- STYLE KHUSUS TABEL BARANG KELUAR --}}
  <style>
    .table-outgoing th {
      font-size: .8rem;
      text-transform: uppercase;
      letter-spacing: .03em;
      white-space: nowrap;
    }
    .table-outgoing td {
      vertical-align: middle;
      font-size: .9rem;
    }

    .table-outgoing .col-date     { width: 110px; text-align: center; }
    .table-outgoing .col-time     { width: 80px;  text-align: center; }
    .table-outgoing .col-code     { width: 110px; text-align: center; }
    .table-outgoing .col-unit     { width: 80px;  text-align: center; }
    .table-outgoing .col-supplier { width: 140px; }
    .table-outgoing .col-buyer    { width: 140px; }
    .table-outgoing .col-po       { width: 120px; text-align: center; }
    .table-outgoing .col-style    { width: 130px; text-align: center; }
    .table-outgoing .col-qty      { width: 120px; text-align: right; }
    .table-outgoing .col-notes    { min-width: 180px; }

    .table-outgoing .material-cell {
      font-weight: 600;
    }
  </style>

  {{-- FLASH MESSAGE --}}
  @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Barang Keluar</h4>
  </div>

  {{-- Search --}}
  <form method="get" action="{{ route('admin.outgoing.index') }}" class="card card-body mb-3">
    <label class="form-label">Cari</label>
    <div class="input-group">
      <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
      <input
        type="text"
        name="q"
        class="form-control"
        value="{{ $q }}"
        placeholder="Kode / Material / Supplier / Nomor PO / Style"
        autocomplete="off"
      >
      <button class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
      <a href="{{ route('admin.outgoing.index') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
  </form>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 table-outgoing">
          <thead class="table-light">
            <tr>
              <th class="col-date">Tanggal</th>
              <th class="col-time">Jam</th>
              <th class="col-code">Kode</th>
              <th class="text-start">Material</th>
              <th class="col-unit">Unit</th>
              <th class="col-supplier">Supplier</th>
              <th class="col-buyer">Buyer</th>
              <th class="col-po">Nomor PO</th>
              <th class="col-style">Style</th>
              <th class="col-qty">Qty OUT</th>
              <th class="col-notes">Catatan</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($movs as $m)
              @php
                // Tanggal / jam
                $dt = optional($m->moved_at);

                // Stock
                $stock = $m->stock;

                // Supplier: prioritas dari movement, fallback dari stock
                $supplierName = optional($m->supplier)->name
                    ?? optional(optional($stock)->supplier)->name
                    ?? null;

                // Buyer: dari stock (untuk FOB)
                $buyerName = optional(optional($stock)->buyer)->name ?? null;

                // Cari style lewat relasi Order / OrderItem -> Order -> PurchaseOrderStyle
                $order = $m->order ?? optional($m->orderItem)->order ?? null;
                $style = optional(optional($order)->purchaseOrderStyle);
                $styleName = $style->style_name
                    ?? $style->name
                    ?? $style->nama_style
                    ?? null;
              @endphp

              <tr>
                {{-- TANGGAL --}}
                <td class="col-date text-center">
                  {{ $dt ? $dt->format('d-m-Y') : '—' }}
                </td>

                {{-- JAM --}}
                <td class="col-time text-center">
                  {{ $dt ? $dt->format('H:i') : '—' }}
                </td>

                {{-- KODE --}}
                <td class="col-code fw-semibold">
                  {{ $m->material_code ?? '—' }}
                </td>

                {{-- MATERIAL --}}
                <td class="text-start material-cell">
                  {{ $m->material_name }}
                </td>

                {{-- UNIT --}}
                <td class="col-unit">
                  {{ $m->unit ?? '—' }}
                </td>

                {{-- SUPPLIER --}}
                <td class="col-supplier text-start">
                  {{ $supplierName ?? '—' }}
                </td>

                {{-- BUYER --}}
                <td class="col-buyer text-start">
                  {{ $buyerName ?? '—' }}
                </td>

                {{-- NOMOR PO --}}
                <td class="col-po">
                  {{ $m->po_number ?? '—' }}
                </td>

                {{-- STYLE --}}
                <td class="col-style">
                  {{ $styleName ?? '—' }}
                </td>

                {{-- QTY OUT --}}
                <td class="col-qty fw-semibold">
                  {{ fmt_number($m->quantity) }}
                </td>

                {{-- CATATAN --}}
                <td class="col-notes text-start">
                  {{ $m->notes ?: '—' }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="11" class="text-center text-muted">Tidak ada data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer">
      {{ $movs->withQueryString()->links() }}
    </div>
  </div>
</div>
@endsection
