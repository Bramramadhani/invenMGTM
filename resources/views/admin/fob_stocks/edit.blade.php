@extends('layouts.master', ['title' => 'Edit Stok FOB'])

@section('content')
<div class="container">

  <h4 class="mb-3">Edit Stok FOB — {{ $stock->material_name }}</h4>

  @php
    if (!function_exists('qty_fmt')) {
      function qty_fmt($n, $dec = 4) {
        $s = number_format((float)$n, $dec, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
      }
    }
  @endphp

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card">
    <div class="card-body">
      <form method="POST" action="{{ route('admin.fob-stocks.update', $stock) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
          <label class="form-label">Buyer <span class="text-danger">*</span></label>
          <select name="buyer_id" class="form-select" required>
            <option value="">-- Pilih Buyer --</option>
            @foreach($buyers as $b)
              <option value="{{ $b->id }}"
                {{ old('buyer_id', $stock->buyer_id) == $b->id ? 'selected' : '' }}>
                {{ $b->name }} @if($b->code) ({{ $b->code }}) @endif
              </option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Vendor / Toko</label>
          <input type="text"
                 name="vendor_name"
                 class="form-control @error('vendor_name') is-invalid @enderror"
                 value="{{ old('vendor_name', $stock->vendor_name) }}"
                 placeholder="Contoh: Toko Bahan A / Vendor X">
          @error('vendor_name')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Kode Material</label>
            <input type="text" name="material_code" class="form-control"
                   value="{{ old('material_code', $stock->material_code) }}">
          </div>
          <div class="col-md-5">
            <label class="form-label">Nama Material <span class="text-danger">*</span></label>
            <input type="text" name="material_name" class="form-control"
                   value="{{ old('material_name', $stock->material_name) }}" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Unit <span class="text-danger">*</span></label>
            <input type="text" name="unit" class="form-control"
                   value="{{ old('unit', $stock->unit) }}" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Qty <span class="text-danger">*</span></label>
            <input type="number" step="0.0001" min="0" name="quantity"
                   class="form-control text-end"
                   value="{{ old('quantity', qty_fmt($stock->quantity)) }}" required>
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-3">
            <label class="form-label">Harga Satuan (Rp)</label>
            <input
              type="number"
              name="unit_price"
              min="0"
              step="1"
              class="form-control text-end @error('unit_price') is-invalid @enderror"
              value="{{ old('unit_price', isset($initialUnitPrice) && $initialUnitPrice !== null ? qty_fmt($initialUnitPrice, 0) : '') }}"
              placeholder="Contoh: 5000">
            @error('unit_price')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
              Opsional. Jika diisi, akan mengoreksi harga pembelian FOB awal.
            </div>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Alasan Koreksi <span class="text-danger">*</span></label>
          <textarea name="reason" rows="2" class="form-control" required>{{ old('reason') }}</textarea>
        </div>

        <div class="alert alert-warning mt-3 small mb-0">
          <strong>Catatan:</strong> Perubahan Qty & harga akan tercatat di
          <em>StockMovement</em> dan <em>StockHistory</em> sebagai koreksi FOB.
          Laporan pembelian FOB akan memakai harga awal yang sudah dikoreksi.
        </div>

        <div class="mt-4 d-flex justify-content-between">
          <a href="{{ route('admin.fob-stocks.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection
