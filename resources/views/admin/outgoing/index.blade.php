@extends('layouts.master', ['title' => 'Barang Keluar'])

@section('content')
<div class="container">
  @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>
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
        placeholder="Kode / Material / Unit / Supplier / Nomor PO"
        autocomplete="off"
      >
      <button class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
      <a href="{{ route('admin.outgoing.index') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
  </form>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:160px">Tanggal</th>
              <th style="width:160px">Kode</th>
              <th class="text-center">Material</th>
              <th style="width:120px" class="text-center">Unit</th>
              <th>Supplier</th>
              <th style="width:160px">Nomor PO</th>
              <th class="text-center" style="width:140px">Qty OUT</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($movs as $m)
              <tr>
                <td>{{ optional($m->moved_at)->format('d-m-Y H:i') }}</td>

                {{-- Kode --}}
                <td>{{ $m->material_code ?? '—' }}</td>

                {{-- Material rata tengah --}}
                <td class="text-center fw-semibold">{{ $m->material_name }}</td>

                {{-- Unit rata tengah --}}
                <td class="text-center">{{ $m->unit ?? '—' }}</td>

                <td>{{ optional($m->supplier)->name ?? '—' }}</td>
                <td>{{ $m->po_number ?? '—' }}</td>

                {{-- Qty Out rata tengah --}}
                <td class="text-center">{{ fmt_number($m->quantity) }}</td>

                {{-- Catatan hanya tampil jika ada --}}
                <td>{{ $m->notes ?: '' }}</td>
              </tr>
            @empty
              <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
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
