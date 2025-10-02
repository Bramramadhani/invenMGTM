@extends('layouts.master', ['title' => 'Dashboard'])

@section('content')
<x-container>
    {{-- Widgets --}}
    <div class="col-sm-6 col-xl-3">
        <x-widget title="Supplier" :subTitle="$suppliers" class="bg-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-truck" width="24" height="24"
                viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round"
                stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <circle cx="7" cy="17" r="2" />
                <circle cx="17" cy="17" r="2" />
                <path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" />
            </svg>
        </x-widget>
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-widget title="Barang" :subTitle="$products" class="bg-indigo">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-truck-loading" width="24"
                height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M2 3h1a2 2 0 0 1 2 2v10a2 2 0 0 0 2 2h15" />
                <rect x="9" y="6" width="10" height="8" rx="3" />
                <circle cx="9" cy="19" r="2" />
                <circle cx="18" cy="19" r="2" />
            </svg>
        </x-widget>
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-widget title="Barang Masuk" :subTitle="number_format($transactions, 0, ',', '.')" class="bg-cyan">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-shopping-cart-x" width="24"
                height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <circle cx="6" cy="19" r="2" />
                <circle cx="17" cy="19" r="2" />
                <path d="M17 17h-11v-14h-2" />
                <path d="M6 5l7.999 .571m5.43 4.43l-.429 2.999h-13" />
                <path d="M17 3l4 4" />
                <path d="M21 3l-4 4" />
            </svg>
        </x-widget>
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-widget title="Barang Masuk Bulan Ini" :subTitle="number_format($transactionThisMonth, 0, ',', '.')"
            class="bg-teal">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-shopping-cart-off" width="24"
                height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <circle cx="6" cy="19" r="2" />
                <path d="M17 17a2 2 0 1 0 2 2" />
                <path d="M17 17h-11v-11" />
                <path d="M9.239 5.231l10.761 .769l-1 7h-2m-4 0h-7" />
                <path d="M3 3l18 18" />
            </svg>
        </x-widget>
    </div>

    {{-- CHART MATERIAL KELUAR --}}
    <div class="col-12 col-lg-6">
        <x-card title="Chart Material Keluar">
            <form method="GET" class="mb-2 d-flex justify-content-end">
                <select name="filter_out" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="5" {{ request('filter_out', '5') == '5' ? 'selected' : '' }}>Top 5</option>
                    <option value="10" {{ request('filter_out') == '10' ? 'selected' : '' }}>Top 10</option>
                    <option value="all" {{ request('filter_out') == 'all' ? 'selected' : '' }}>All</option>
                </select>
                {{-- pertahankan filter lain --}}
                <input type="hidden" name="filter_in" value="{{ request('filter_in') }}">
                <input type="hidden" name="filter_list_out" value="{{ request('filter_list_out') }}">
                <input type="hidden" name="filter_list_po" value="{{ request('filter_list_po') }}">
            </form>
            <div style="overflow-x:auto">
                <div style="width: {{ max(600, count($outLabel ?? []) * 120) }}px;">
                    <div id="chart-material-out" class="my-3" style="height:400px;"></div>
                </div>
            </div>
        </x-card>

        <x-card title="List Material Keluar">
            <form method="GET" class="mb-2 d-flex justify-content-end">
                <select name="filter_list_out" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="5" {{ request('filter_list_out', '5') == '5' ? 'selected' : '' }}>Top 5</option>
                    <option value="10" {{ request('filter_list_out') == '10' ? 'selected' : '' }}>Top 10</option>
                    <option value="all" {{ request('filter_list_out') == 'all' ? 'selected' : '' }}>All</option>
                </select>
                {{-- pertahankan filter lain --}}
                <input type="hidden" name="filter_in" value="{{ request('filter_in') }}">
                <input type="hidden" name="filter_out" value="{{ request('filter_out') }}">
                <input type="hidden" name="filter_list_po" value="{{ request('filter_list_po') }}">
            </form>
            @if(!empty($outList))
                <ul class="list-group list-group-flush shadow-sm">
                    @foreach($outList as $it)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div class="text-truncate" style="max-width:70%">{{ $it['material_name'] }}</div>
                            <span class="badge bg-secondary ml-2">{{ $it['quantity'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted">Belum ada data barang keluar.</p>
            @endif
        </x-card>
    </div>

    {{-- CHART MATERIAL MASUK --}}
    <div class="col-lg-6">
        <x-card title="Chart Material Masuk">
            <form method="GET" class="mb-2 d-flex justify-content-end">
                <select name="filter_in" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="5" {{ request('filter_in', '5') == '5' ? 'selected' : '' }}>Top 5</option>
                    <option value="10" {{ request('filter_in') == '10' ? 'selected' : '' }}>Top 10</option>
                    <option value="all" {{ request('filter_in') == 'all' ? 'selected' : '' }}>All</option>
                </select>
                {{-- pertahankan filter lain --}}
                <input type="hidden" name="filter_out" value="{{ request('filter_out') }}">
                <input type="hidden" name="filter_list_out" value="{{ request('filter_list_out') }}">
                <input type="hidden" name="filter_list_po" value="{{ request('filter_list_po') }}">
            </form>
            <div style="overflow-x:auto">
                <div style="width: {{ max(600, count($inLabel ?? []) * 120) }}px;">
                    <div id="chart-material-in" class="my-3" style="height:400px;"></div>
                </div>
            </div>
        </x-card>

        <x-card title="List Per Kedatangan PO">
            <form method="GET" class="mb-2 d-flex justify-content-end">
                <select name="filter_list_po" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="5" {{ request('filter_list_po', '5') == '5' ? 'selected' : '' }}>Top 5</option>
                    <option value="10" {{ request('filter_list_po') == '10' ? 'selected' : '' }}>Top 10</option>
                    <option value="all" {{ request('filter_list_po') == 'all' ? 'selected' : '' }}>All</option>
                </select>
                {{-- pertahankan filter lain --}}
                <input type="hidden" name="filter_in" value="{{ request('filter_in') }}">
                <input type="hidden" name="filter_out" value="{{ request('filter_out') }}">
                <input type="hidden" name="filter_list_out" value="{{ request('filter_list_out') }}">
            </form>
            @if(!empty($charts))
                <ul class="list-group list-group-flush shadow-sm">
                    @foreach($charts as $c)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <div class="font-weight-bold">{{ $c['title'] }}</div>
                                @if(!empty($c['labels']))
                                    <small class="text-muted">{{ implode(', ', $c['labels']) }}</small>
                                @endif
                            </div>
                            <span class="badge bg-secondary">{{ array_sum($c['series'] ?? []) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted">Belum ada data penerimaan.</p>
            @endif
        </x-card>
    </div>
</x-container>
@endsection

@push('js')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // chart material keluar
        window.ApexCharts && (new ApexCharts(document.getElementById('chart-material-out'), {
            chart: { type: 'bar', fontFamily: 'inherit', height: 400, animations: { enabled: true } },
            plotOptions: { bar: { horizontal: false, columnWidth: '55%' } },
            fill: { opacity: 1 },
            series: [{ name: 'Jumlah', data: @json($outTotal ?? []) }],
            xaxis: { categories: @json($outLabel ?? []) },
            grid: { strokeDashArray: 4 },
            colors: ["#e64a19", "#ffb74d", "#8d6e63", "#9575cd", "#4db6ac"],
            legend: { show: false },
            tooltip: { y: { formatter: val => val } },
            dataLabels: { enabled: true, formatter: val => val },
        })).render();

        // chart material masuk
        window.ApexCharts && (new ApexCharts(document.getElementById('chart-material-in'), {
            chart: { type: 'bar', fontFamily: 'inherit', height: 400, animations: { enabled: true } },
            plotOptions: { bar: { horizontal: false, columnWidth: '55%' } },
            fill: { opacity: 1 },
            series: [{ name: 'Jumlah', data: @json($inTotal ?? []) }],
            xaxis: { categories: @json($inLabel ?? []) },
            grid: { strokeDashArray: 4 },
            colors: ["#206bc4", "#79a6dc", "#bfe399", "#7891b3", "#2596be"],
            legend: { show: false },
            tooltip: { y: { formatter: val => val } },
            dataLabels: { enabled: true, formatter: val => val },
        })).render();
    });
</script>
@endpush
