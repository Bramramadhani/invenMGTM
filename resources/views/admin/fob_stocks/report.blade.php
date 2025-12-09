@extends('layouts.master', ['title' => 'Laporan Pembelian FOB'])

@section('content')
<div class="container-fluid">

  @php
    use Carbon\Carbon;
  @endphp

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Laporan Pembelian Stok FOB</h4>

    <div class="d-flex gap-2">
      {{-- Download Excel, ikut semua query (range_type, date, month) --}}
      <a href="{{ route('admin.fob-stocks.purchase-report.export', request()->query()) }}"
         class="btn btn-success btn-sm">
        <i class="fas fa-file-excel"></i> Download Excel
      </a>

      <a href="{{ route('admin.fob-stocks.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Kembali
      </a>
    </div>
  </div>

  {{-- Filter Periode --}}
  <form method="get" id="filterForm" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
      <label for="rangeType" class="form-label mb-0 small">Tipe Periode</label>
      <select name="range_type" id="rangeType" class="form-select form-select-sm">
        <option value="day"   {{ ($rangeType ?? 'day') === 'day' ? 'selected' : '' }}>Per Tanggal</option>
        <option value="month" {{ ($rangeType ?? 'day') === 'month' ? 'selected' : '' }}>Per Bulan</option>
      </select>
    </div>

    {{-- Filter Tanggal --}}
    <div class="col-auto"
         id="wrapperDate"
         style="{{ ($rangeType ?? 'day') === 'month' ? 'display:none;' : '' }}">
      <label for="filterDate" class="form-label mb-0 small">Tanggal</label>
      <input type="date"
             id="filterDate"
             name="date"
             value="{{ $date ?? now()->toDateString() }}"
             class="form-control form-control-sm">
    </div>

    {{-- Filter Bulan --}}
    <div class="col-auto"
         id="wrapperMonth"
         style="{{ ($rangeType ?? 'day') === 'day' ? 'display:none;' : '' }}">
      <label for="filterMonth" class="form-label mb-0 small">Bulan</label>
      <input type="month"
             id="filterMonth"
             name="month"
             value="{{ $month ?? now()->format('Y-m') }}"
             class="form-control form-control-sm">
    </div>

    {{-- Tombol hanya sebagai fallback (disembunyikan) --}}
    <div class="col-auto d-none">
      <button class="btn btn-primary btn-sm mt-3">
        <i class="fas fa-search"></i> Tampilkan
      </button>
    </div>
  </form>

  @if ($histories->isEmpty())
    <div class="alert alert-info">
      @if(($rangeType ?? 'day') === 'month')
        Belum ada pembelian FOB pada bulan
        <strong>{{ Carbon::createFromFormat('Y-m', $month ?? now()->format('Y-m'))->format('m-Y') }}</strong>.
      @else
        Belum ada pembelian FOB pada tanggal
        <strong>{{ Carbon::parse($date ?? now()->toDateString())->format('d-m-Y') }}</strong>.
      @endif
    </div>
  @else
    <div class="card mb-3">
      <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
        <span>Daftar Pembelian FOB</span>
        <span class="small text-muted">
          Periode:
          @if(($rangeType ?? 'day') === 'month')
            {{ Carbon::createFromFormat('Y-m', $month ?? now()->format('Y-m'))->format('m-Y') }}
          @else
            {{ Carbon::parse($date ?? now()->toDateString())->format('d-m-Y') }}
          @endif
        </span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:120px">TANGGAL</th>
                <th>BUYER</th>
                <th style="width:120px">KODE</th>
                <th>MATERIAL</th>
                <th style="width:80px">UNIT</th>
                <th style="width:120px" class="text-end">QTY</th>
                <th style="width:140px" class="text-end">HARGA SATUAN</th>
                <th style="width:160px" class="text-end">TOTAL</th>
                <th>CATATAN</th>
              </tr>
            </thead>
            <tbody>
              @php $grandTotal = 0; @endphp
              @foreach ($histories as $row)
                @php
                  $stock      = $row->stock;
                  $buyer      = optional(optional($stock)->buyer)->name;
                  $qty        = (float) $row->diff_quantity;
                  $price      = (float) ($row->unit_price ?? 0);
                  $lineTotal  = $qty * $price;
                  $grandTotal += $lineTotal;
                  $code       = $stock->material_code ?? null;
                @endphp
                <tr>
                  {{-- hanya tanggal, tanpa jam --}}
                  <td>{{ optional($row->created_at)->format('d-m-Y') }}</td>
                  <td>{{ $buyer ?: '—' }}</td>
                  <td>{{ $code ?: '—' }}</td>
                  <td>{{ $stock->material_name ?? '—' }}</td>
                  <td>{{ $stock->unit ?? '' }}</td>
                  {{-- Qty tanpa desimal --}}
                  <td class="text-end">{{ number_format($qty, 0, ',', '.') }}</td>
                  <td class="text-end">Rp {{ number_format($price, 0, ',', '.') }}</td>
                  <td class="text-end">Rp {{ number_format($lineTotal, 0, ',', '.') }}</td>
                  <td>{{ $row->reason }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr class="table-light">
                <th colspan="7" class="text-end">TOTAL PEMBELIAN</th>
                <th class="text-end">
                  Rp {{ number_format($grandTotal, 0, ',', '.') }}
                </th>
                <th></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  @endif

</div>
@endsection

@push('js')
<script>
(function() {
  const form       = document.getElementById('filterForm');
  const rangeType  = document.getElementById('rangeType');
  const dateInput  = document.getElementById('filterDate');
  const monthInput = document.getElementById('filterMonth');
  const wrapperDate  = document.getElementById('wrapperDate');
  const wrapperMonth = document.getElementById('wrapperMonth');

  function toggleInputs() {
    if (!rangeType) return;
    if (rangeType.value === 'month') {
      if (wrapperDate)  wrapperDate.style.display  = 'none';
      if (wrapperMonth) wrapperMonth.style.display = '';
    } else {
      if (wrapperDate)  wrapperDate.style.display  = '';
      if (wrapperMonth) wrapperMonth.style.display = 'none';
    }
  }

  function autoSubmit() {
    if (form) form.submit();
  }

  if (rangeType) {
    rangeType.addEventListener('change', () => {
      toggleInputs();
      autoSubmit();
    });
  }
  if (dateInput) {
    dateInput.addEventListener('change', autoSubmit);
  }
  if (monthInput) {
    monthInput.addEventListener('change', autoSubmit);
  }

  // Init state saat pertama load
  toggleInputs();
})();
</script>
@endpush
