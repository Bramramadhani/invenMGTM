@extends('layouts.master', ['title' => 'Dashboard'])

@section('content')
<x-container>

    {{-- ====== STYLE: samakan tinggi panel (tabel & chart) ====== --}}
    <style>
      :root { --dash-card-h: 420px; }                /* default desktop */
      @media (max-width: 1199.98px){ :root { --dash-card-h: 380px; } }
      @media (max-width: 991.98px) { :root { --dash-card-h: 340px; } }
      /* scroll area tabel PO Progress */
      .equal-body{ max-height: var(--dash-card-h); overflow-y: auto; overflow-x: hidden; }
      /* kanvas chart dan wrapper-nya */
      .equal-chart-wrap{ overflow-x: auto; overflow-y: hidden; }
      .equal-chart     { height: var(--dash-card-h); }
      /* rapikan spacing antar elemen di card chart */
      .chart-title{ font-weight: 600; margin-bottom: .5rem; }
    </style>

    @php
      // helper angka umum (bisa dipakai ulang di bawah)
      $fmtInt = $fmtInt ?? function($n){ return number_format((float)$n, 0, ',', '.'); };
    @endphp

    {{-- Widgets --}}
    <div class="col-sm-6 col-xl-3">
        <x-widget title="Buyer" :subTitle="$suppliers" class="bg-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-truck" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"/></svg>
        </x-widget>
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-widget title="Barang" :subTitle="$products" class="bg-indigo">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-truck-loading" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M2 3h1a2 2 0 0 1 2 2v10a2 2 0 0 0 2 2h15"/><rect x="9" y="6" width="10" height="8" rx="3"/><circle cx="9" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>
        </x-widget>
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-widget title="Barang Masuk" :subTitle="number_format($transactions, 0, ',', '.')" class="bg-cyan">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-shopping-cart-x" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="6" cy="19" r="2"/><circle cx="17" cy="19" r="2"/><path d="M17 17h-11v-14h-2"/><path d="M6 5l7.999 .571m5.43 4.43l-.429 2.999h-13"/><path d="M17 3l4 4"/><path d="M21 3l-4 4"/></svg>
        </x-widget>
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-widget title="Barang Masuk Bulan Ini" :subTitle="number_format($transactionThisMonth, 0, ',', '.')" class="bg-teal">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-shopping-cart-off" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="6" cy="19" r="2"/><path d="M17 17a2 2 0 1 0 2 2"/><path d="M17 17h-11v-11"/><path d="M9.239 5.231l10.761 .769l-1 7h-2m-4 0h-7"/><path d="M3 3l18 18"/></svg>
        </x-widget>
    </div>

    {{-- KOLUMEN KIRI --}}
    <div class="col-12 col-lg-6">
        {{-- PO Progress Panel --}}
        <x-card title="PO Progress">
            @php
                $statusBadge = function($status) {
                    return match ($status) {
                        'Complete' => 'bg-success',
                        'Over'     => 'bg-warning',
                        'Partial'  => 'bg-info',
                        default    => 'bg-secondary',
                    };
                };
                $barClass = fn($pct,$status) => $status === 'Over' ? 'bg-warning' : ($pct >= 100 ? 'bg-success' : 'bg-info');
            @endphp

            <form method="GET" class="mb-3">
                @foreach(request()->except(['po_supplier_id','po_limit']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-6">
                        <label class="form-label mb-1 small d-block text-center">Buyer</label>
                        <select name="po_supplier_id" class="form-select" onchange="this.form.submit()" aria-label="Filter Supplier">
                            <option value="">— Semua Buyer —</option>
                            @foreach($poSuppliers as $s)
                                <option value="{{ $s->id }}" {{ (int)$poSupplierId === (int)$s->id ? 'selected' : '' }}>
                                    {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label mb-1 small d-block text-center">Tampilkan</label>
                        @php $poLimitSel = $poLimit ?? request('po_limit', '20'); @endphp
                        <select name="po_limit" class="form-select" onchange="this.form.submit()">
                            <option value="10"  {{ $poLimitSel === '10'  ? 'selected' : '' }}>10</option>
                            <option value="20"  {{ $poLimitSel === '20'  ? 'selected' : '' }}>20</option>
                            <option value="50"  {{ $poLimitSel === '50'  ? 'selected' : '' }}>50</option>
                            <option value="all" {{ $poLimitSel === 'all' ? 'selected' : '' }}>All</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 d-flex justify-content-md-end">
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-link px-0 px-md-2">Reset</a>
                    </div>
                </div>
            </form>

            @if(!empty($poProgress))
                <div class="table-responsive equal-body">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>PO No</th>
                            <th>Buyer</th>
                            <th class="text-end">Qty PO</th>
                            <th class="text-end">Qty IN</th>
                            <th class="text-center">% Diterima</th>
                            <th class="text-center">Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($poProgress as $po)
                            <tr>
                                <td class="text-truncate" title="{{ $po['number'] ?? ('#'.$po['id']) }}">{{ $po['number'] ?? ('#'.$po['id']) }}</td>
                                <td class="text-truncate" title="{{ $po['supplier'] ?? '-' }}">{{ $po['supplier'] ?? '-' }}</td>
                                <td class="text-end">{{ $fmtInt($po['ordered'] ?? 0) }}</td>
                                <td class="text-end">{{ $fmtInt($po['received'] ?? 0) }}</td>
                                <td style="width: 220px;">
                                    <div class="d-flex flex-column">
                                        <div class="progress" style="height:10px;">
                                            <div class="progress-bar {{ $barClass($po['pct'] ?? 0, $po['status'] ?? '') }}"
                                                 role="progressbar"
                                                 style="width: {{ min(100, (float)($po['pct'] ?? 0)) }}%;"
                                                 aria-valuenow="{{ (float)($po['pct'] ?? 0) }}"
                                                 aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted text-center mt-1">
                                            {{ number_format((float)($po['pct'] ?? 0), 2, ',', '.') }}%
                                        </small>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $statusBadge($po['status'] ?? 'Pending') }}">{{ $po['status'] ?? 'Pending' }}</span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted mb-0">Tidak ada PO sesuai filter.</p>
            @endif
        </x-card>

        {{-- LIST MATERIAL KELUAR (transaksi OUT terbaru + scroll) --}}
        <x-card title="List Material Keluar">
            <form method="GET" class="mb-2 d-flex justify-content-end align-items-center gap-2">
                @foreach(request()->except(['filter_list_out']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <label class="form-label mb-0 small" for="filter_list_out">Tampilkan</label>
                <select id="filter_list_out" name="filter_list_out" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="5"  {{ request('filter_list_out', '5') === '5'  ? 'selected' : '' }}>Top 5</option>
                    <option value="10" {{ request('filter_list_out') === '10' ? 'selected' : '' }}>Top 10</option>
                    <option value="all"{{ request('filter_list_out') === 'all'? 'selected' : '' }}>All</option>
                </select>
            </form>

            @php
              $qtyfmt = function($n){ $s = number_format((float)$n, 4, '.', ''); return rtrim(rtrim($s,'0'),'.') ?: '0'; };
            @endphp

            @if(!empty($outList) && count($outList) > 0)
              <div class="table-responsive" style="max-height: 360px; overflow:auto;">
                <table class="table table-sm mb-0">
                  <thead class="table-light" style="position: sticky; top: 0; z-index:1;">
                    <tr>
                      <th style="width:130px">Waktu</th>
                      <th style="width:90px">Kode</th>
                      <th>Material</th>
                      <th class="text-end" style="width:110px">Qty</th>
                      <th style="width:90px">Unit</th>
                      <th>Buyer</th>
                      <th style="width:140px">NO PO</th>
                      <th style="width:180px">Catatan</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($outList as $r)
                      @php
                        $code     = optional($r->stock)->material_code ?? '—';
                        $name     = $r->material_name ?? (optional($r->stock)->material_name ?? '—');
                        $unit     = $r->unit ?? (optional($r->stock)->unit ?? '—');
                        $supplier = optional($r->supplier)->name ?? '—';
                        $poNo     = $r->po_number;
                      @endphp
                      <tr>
                        <td class="text-nowrap">{{ optional($r->moved_at)->format('d-m-Y H:i') }}</td>
                        <td>{{ $code }}</td>
                        <td class="text-truncate" title="{{ $name }}">{{ $name }}</td>
                        <td class="text-end">{{ $qtyfmt($r->quantity) }}</td>
                        <td>{{ $unit }}</td>
                        <td class="text-truncate" title="{{ $supplier }}">{{ $supplier }}</td>
                        <td>{{ $poNo ?: '—' }}</td>
                        <td class="text-truncate" title="{{ $r->notes }}">{{ $r->notes ?: '—' }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <p class="text-muted mb-0">Belum ada data barang keluar.</p>
            @endif
        </x-card>
    </div>

    {{-- KOLUMEN KANAN --}}
    <div class="col-lg-6">
        {{-- Kedatangan Per PO: Chart batang interaktif + auto-fit + scroll horizontal --}}
        <x-card title="Kedatangan Per PO">
            <form method="GET" class="mb-2 d-flex justify-content-end align-items-center gap-2">
                @foreach(request()->except(['filter_list_po']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach

                <label class="form-label mb-0 small" for="filter_list_po">Tampilkan</label>
                <select id="filter_list_po" name="filter_list_po" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="5"  {{ request('filter_list_po', '5') === '5'  ? 'selected' : '' }}>Top 5</option>
                    <option value="10" {{ request('filter_list_po') === '10' ? 'selected' : '' }}>Top 10</option>
                    <option value="all"{{ request('filter_list_po') === 'all'? 'selected' : '' }}>All</option>
                </select>

                @if(!empty($poChart) && !empty($poChart['labels']))
                  <button type="button" id="resetZoomPO" class="btn btn-sm btn-outline-secondary ms-2">Reset Zoom</button>
                @endif
            </form>

            @if(!empty($poChart) && !empty($poChart['labels']))
              <div class="border rounded p-2 equal-chart-wrap">
                <div id="po-scroll-wrapper" class="pe-2">
                  <div class="chart-title">{{ $poChart['title'] }}</div>
                  <canvas id="po-chart" class="equal-chart"></canvas>
                </div>
              </div>
            @else
              <p class="text-muted mb-0">Belum ada data penerimaan untuk ditampilkan.</p>
            @endif
        </x-card>

        {{-- Material Keluar: Chart batang (rapi & seragam canvas) --}}
        <x-card title="Material Keluar">
            <form method="GET" class="mb-2 d-flex justify-content-end align-items-center gap-2">
                @foreach(request()->except(['filter_out']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach

                <label class="form-label mb-0 small" for="filter_out">Tampilkan</label>
                <select id="filter_out" name="filter_out" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="5"  {{ request('filter_out', '5') === '5'  ? 'selected' : '' }}>Top 5</option>
                    <option value="10" {{ request('filter_out') === '10' ? 'selected' : '' }}>Top 10</option>
                    <option value="all"{{ request('filter_out') === 'all'? 'selected' : '' }}>All</option>
                </select>

                @if(!empty($outChart) && !empty($outChart['labels']))
                  <button type="button" id="resetZoomOUT" class="btn btn-sm btn-outline-secondary ms-2">Reset Zoom</button>
                @endif
            </form>

            @if(!empty($outChart) && !empty($outChart['labels']))
              <div class="border rounded p-2 equal-chart-wrap">
                <div id="out-scroll-wrapper" class="pe-2">
                  <div class="chart-title">{{ $outChart['title'] }}</div>
                  <canvas id="out-chart" class="equal-chart"></canvas>
                </div>
              </div>
            @else
              <p class="text-muted mb-0">Belum ada data material keluar untuk ditampilkan.</p>
            @endif
        </x-card>
    </div>

</x-container>

{{-- Chart.js + plugin zoom & init (aktif kalau salah satu chart tersedia) --}}
@if( (!empty($poChart) && !empty($poChart['labels'])) || (!empty($outChart) && !empty($outChart['labels'])) )
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.umd.min.js" crossorigin="anonymous"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    if (!window.Chart) return;

    // ===== helper umum =====
    function makeGradient(ctx, height, top, bottom){
      const g = ctx.createLinearGradient(0, 0, 0, height || 400);
      g.addColorStop(0, top);
      g.addColorStop(1, bottom);
      return g;
    }
    const BAR_THICKNESS = 48;
    const GAP_PER_BAR   = 28;
    const DESIRED_H     = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--dash-card-h')) || 400;

    function computeWidth(labelsCount, wrapper){
      const minWidth = Math.max((wrapper?.clientWidth || 0), 720);
      const scrollW  = labelsCount * (BAR_THICKNESS + GAP_PER_BAR);
      return Math.max(minWidth, scrollW);
    }

    function buildChart(canvasId, wrapperId, dataLabels, dataSeries, colorPair, resetBtnId){
      const canvas  = document.getElementById(canvasId);
      const wrapper = document.getElementById(wrapperId);
      if (!canvas || !wrapper) return null;

      const ctx = canvas.getContext('2d');
      const gradient = makeGradient(ctx, canvas.height, colorPair[0], colorPair[1]);

      const labels = (dataLabels || []).map(l => (l || '').split(' — '));
      const values = (dataSeries || []).map(v => Number(v) || 0);

      const chart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Quantity',
            data: values,
            backgroundColor: gradient,
            borderColor: colorPair[0].replace('0.90','1').replace('0.85','1'),
            borderWidth: 1,
            borderRadius: 10,
            borderSkipped: false,
            barThickness: BAR_THICKNESS,
            maxBarThickness: BAR_THICKNESS,
            categoryPercentage: 1.0,
            barPercentage: 0.72
          }]
        },
        options: {
          responsive: false,
          maintainAspectRatio: false,
          layout: { padding: { top: 8, right: 8, bottom: 4, left: 8 } },
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: 'rgba(0,0,0,0.85)',
              callbacks: {
                title: (items) => (items[0]?.label || '').toString().replaceAll(',', ' / '),
                label: (ctx) => ' ' + (ctx.parsed.y ?? 0).toLocaleString('id-ID')
              }
            },
            zoom: {
              pan:  { enabled: true, mode: 'x' },
              zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'x' }
            }
          },
          scales: {
            x: {
              ticks: { maxRotation: 0, minRotation: 0, autoSkip: false, font: { size: 12 } },
              grid: { display: false }
            },
            y: {
              beginAtZero: true,
              ticks: { precision: 0, callback: v => Number(v).toLocaleString('id-ID') }
            }
          },
          animation: { duration: 450, easing: 'easeOutQuart' }
        }
      });

      function reflow(){
        const width = computeWidth(labels.length, wrapper);
        canvas.width  = width;
        canvas.height = DESIRED_H;
        chart.resize(width, DESIRED_H);
      }
      reflow();

      if ('ResizeObserver' in window) {
        const ro = new ResizeObserver(() => reflow());
        ro.observe(wrapper);
      } else {
        window.addEventListener('resize', reflow);
      }

      if (resetBtnId) {
        const btn = document.getElementById(resetBtnId);
        if (btn) btn.addEventListener('click', () => chart.resetZoom());
      }
      return chart;
    }

    // ===== inisiasi chart PO (jika ada) =====
    @if(!empty($poChart) && !empty($poChart['labels']))
      buildChart(
        'po-chart',
        'po-scroll-wrapper',
        @json($poChart['labels']),
        @json($poChart['series']),
        ['rgba(54, 162, 235, 0.90)', 'rgba(54, 162, 235, 0.35)'],   // biru
        'resetZoomPO'
      );
    @endif

    // ===== inisiasi chart Material OUT (jika ada) =====
    @if(!empty($outChart) && !empty($outChart['labels']))
      buildChart(
        'out-chart',
        'out-scroll-wrapper',
        @json($outChart['labels']),
        @json($outChart['series']),
        ['rgba(255, 159, 64, 0.90)', 'rgba(255, 159, 64, 0.35)'],   // oranye
        'resetZoomOUT'
      );
    @endif
  });
  </script>
@endif
@endsection
