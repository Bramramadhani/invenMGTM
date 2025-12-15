@extends('layouts.master', ['title' => 'Detail Stok FOB'])

@section('content')
<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Detail Stok FOB — {{ $stock->material_name }}</h4>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.fob-stocks.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Kembali
      </a>
      <a href="{{ route('admin.fob-stocks.edit', $stock) }}" class="btn btn-primary">
        <i class="fas fa-edit"></i> Edit Stok
      </a>
    </div>
  </div>

  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif

  @php
    if (!function_exists('qty_fmt')) {
      function qty_fmt($n, $dec = 4) {
        $s = number_format((float)$n, $dec, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
      }
    }
  @endphp

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header bg-light"><strong>Info Stok</strong></div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-4">FOB</dt>
            <dd class="col-8">{{ optional($stock->buyer)->name ?? '—' }}</dd>

            <dt class="col-4">Vendor / Toko</dt>
            <dd class="col-8">{{ $stock->vendor_name ?? '—' }}</dd>

            <dt class="col-4">Kode Material</dt>
            <dd class="col-8">{{ $stock->material_code ?? '—' }}</dd>

            <dt class="col-4">Nama Material</dt>
            <dd class="col-8">{{ $stock->material_name }}</dd>

            <dt class="col-4">Unit</dt>
            <dd class="col-8">{{ $stock->unit ?? '—' }}</dd>

            <dt class="col-4">Qty Saat Ini</dt>
            <dd class="col-8"><strong>{{ qty_fmt($stock->quantity) }}</strong></dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-12">
      <div class="card">
        <div class="card-header bg-light"><strong>Riwayat Perubahan Stok</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:50px;" class="text-center">No</th>
                  <th style="width:160px;">Tanggal</th>
                  <th style="width:120px;">Tipe</th>
                  <th class="text-end" style="width:120px;">Qty Lama</th>
                  <th class="text-end" style="width:120px;">Qty Baru</th>
                  <th class="text-end" style="width:120px;">Selisih</th>
                  <th style="width:140px;">User</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                @forelse($histories as $i => $h)
                  @php
                    $diff = (float) $h->diff_quantity;
                    $diffClass = $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted');
                  @endphp
                  <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>
                      {{ optional($h->created_at)->format('d-m-Y') }}<br>
                      <small class="text-muted">{{ optional($h->created_at)->format('H:i') }}</small>
                    </td>
                    <td>
                      @if($h->type === \App\Models\StockHistory::TYPE_FOB_CREATE)
                        <span class="badge bg-success">CREATE</span>
                      @elseif($h->type === \App\Models\StockHistory::TYPE_FOB_UPDATE)
                        <span class="badge bg-warning text-dark">UPDATE</span>
                      @elseif($h->type === \App\Models\StockHistory::TYPE_FOB_DELETE)
                        <span class="badge bg-danger">DELETE</span>
                      @else
                        <span class="badge bg-secondary">{{ strtoupper($h->type) }}</span>
                      @endif
                    </td>
                    <td class="text-end">{{ qty_fmt($h->old_quantity) }}</td>
                    <td class="text-end">{{ qty_fmt($h->new_quantity) }}</td>
                    <td class="text-end {{ $diffClass }}">{{ $diff > 0 ? '+' : '' }}{{ qty_fmt($diff) }}</td>
                    <td>{{ optional($h->creator)->name ?? 'System' }}</td>
                    <td>{{ $h->reason ?? '—' }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="text-center text-muted py-3">
                      Belum ada riwayat perubahan stok untuk material ini.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
