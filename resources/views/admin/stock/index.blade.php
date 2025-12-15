@extends('layouts.master', ['title' => 'Stok Barang'])

@section('content')
<div class="container">

  {{-- Flash --}}
  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Stok Barang</h4>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
      <i class="fas fa-home"></i> Dashboard
    </a>
  </div>

  @php
    if (!function_exists('qty_fmt')) {
      function qty_fmt($n, int $dec = 4): string {
        $s = number_format((float)$n, $dec, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
      }
    }

    // group dikirim dari controller, fallback ke request jika perlu
    $group = $group ?? request('group', 'supplier-po');
    $term  = $term ?? request('q', '');
  @endphp

  {{-- Filter --}}
  <form method="get" action="{{ route('admin.stock.index') }}" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label">Cari</label>
        <div class="input-group">
          <span class="input-group-text bg-white">
            <i class="fas fa-search"></i>
          </span>
          <input
            type="text"
            name="q"
            class="form-control"
            value="{{ $term }}"
            placeholder="Ketik kode / material / unit / Buyer / NO PO"
            autocomplete="off"
          >
        </div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Tampilan</label>
        <select name="group" class="form-select">
          <option value="supplier-po" @selected($group==='supplier-po')>Per Buyer</option>
          <option value="flat"        @selected($group==='flat')>Tabel</option>
        </select>
      </div>

      <div class="col-md-3 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary">
          <i class="fas fa-search"></i> Cari
        </button>
        <a href="{{ route('admin.stock.index') }}" class="btn btn-outline-secondary">Reset</a>
      </div>
    </div>
  </form>

  @if($group === 'supplier-po')
    {{-- =========================
         TAMPILAN KELOMPOK:
         Supplier ➜ NO PO ➜ Item
       ========================= --}}
    @php
      /** @var \Illuminate\Support\Collection $stocks */
      $grouped = $stocks
        ->groupBy(function($row) {
          return optional($row->supplier)->name ?: 'Tanpa Supplier';
        })
        ->map(function($rowsBySupplier) {
          return $rowsBySupplier->groupBy(function($row) {
            $poNumber = optional($row->purchaseOrder)->po_number ?? 'Tanpa PO';
            return $poNumber;
          });
        });
    @endphp

    @forelse($grouped as $supplierName => $byPo)
      <div class="card mb-4">
        <div class="card-header">
          <strong>Buyer</strong>&nbsp;:&nbsp;<span>{{ $supplierName }}</span>
        </div>

        <div class="card-body">
          <div class="d-flex flex-column gap-3">
            @foreach($byPo as $poNumber => $rows)
              @php
                /** @var \App\Models\Stock|null $first */
                $first    = $rows->first();
                $poModel  = optional($first)->purchaseOrder;
                $poId     = $poModel?->id;  // STRICT: tanpa fallback last_po_id
                $totalQty = $rows->sum(fn($r) => (float)$r->quantity);
              @endphp

              <div class="bg-light border rounded p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">
                    <span>NO PO</span>&nbsp;:&nbsp;
                    <span>
                      @if($poId && $poNumber && $poNumber !== 'Tanpa PO')
                        <a href="{{ route('admin.purchase-orders.show', $poId) }}">{{ $poNumber }}</a>
                      @else
                        {{ $poNumber }}
                      @endif
                    </span>
                    <span class="text-muted ms-2">({{ $rows->count() }} baris)</span>
                  </h6>
                  <div class="small text-muted">
                    Total Qty: <strong>{{ qty_fmt($totalQty) }}</strong>
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                      <tr>
                        <th style="width:60px">No</th>
                        <th style="width:180px">Kode</th>
                        <th>Material</th>
                        <th style="width:120px">Unit</th>
                        <th class="text-end" style="width:160px">Quantity</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($rows->values() as $i => $row)
                        @php
                          $qty    = (float)$row->quantity;
                          $isZero = $qty <= 0;
                          $isLow  = !$isZero && $qty <= 10;
                        @endphp
                        <tr>
                          <td>{{ $i + 1 }}</td>
                          <td>{{ $row->material_code ?: '—' }}</td>
                          <td class="fw-semibold">{{ $row->material_name }}</td>
                          <td>{{ $row->unit ?? '—' }}</td>
                          <td class="text-end">
                            {{ qty_fmt($qty) }}
                            @if($isZero)
                              <span class="badge bg-danger ms-1">Habis</span>
                            @elseif($isLow)
                              <span class="badge bg-warning text-dark ms-1">Menipis</span>
                            @endif
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @empty
      <div class="card">
        <div class="card-body">
          <div class="alert alert-info mb-0">Tidak ada stok.</div>
        </div>
      </div>
    @endforelse

  @else
    {{-- =========================
         TAMPILAN FLAT (tabel)
       ========================= --}}
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:60px">No</th>
                <th style="width:180px">Kode</th>
                <th>Material</th>
                <th style="width:120px">Unit</th>
                <th>Buyer</th>
                <th class="text-end" style="width:180px">Quantity</th>
                <th style="width:160px">NO PO</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($stocks as $i => $st)
                @php
                  $qty    = (float) $st->quantity;
                  $isZero = $qty <= 0;
                  $isLow  = !$isZero && $qty <= 10;

                  $po   = optional($st->purchaseOrder);
                  $poId = $po->id;
                  $poNo = $po->po_number;
                @endphp
                <tr>
                  <td>{{ $stocks->firstItem() + $i }}</td>
                  <td>{{ $st->material_code ?: '—' }}</td>
                  <td class="fw-semibold">{{ $st->material_name }}</td>
                  <td>{{ $st->unit ?? '—' }}</td>
                  <td>{{ optional($st->supplier)->name ?? '—' }}</td>
                  <td class="text-end">
                    {{ qty_fmt($qty) }}
                    @if($isZero)
                      <span class="badge bg-danger ms-1">Habis</span>
                    @elseif($isLow)
                      <span class="badge bg-warning text-dark ms-1">Menipis</span>
                    @endif
                  </td>
                  <td>
                    @if($poId && $poNo)
                      <a href="{{ route('admin.purchase-orders.show', $poId) }}">{{ $poNo }}</a>
                    @else
                      —
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center text-muted">Tidak ada data stok.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card-footer">
        {{ $stocks->withQueryString()->links() }}
      </div>
    </div>
  @endif

</div>
@endsection
