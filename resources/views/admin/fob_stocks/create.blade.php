@extends('layouts.master', ['title' => 'Tambah Stok FOB (Pembelian FOB)'])

@section('content')
<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Tambah Stok FOB (Pembelian)</h4>
    <a href="{{ route('admin.fob-stocks.index') }}" class="btn btn-secondary btn-sm">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">Perhatian! Terdapat beberapa kesalahan input:</div>
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card">
    <div class="card-header bg-light fw-semibold">
      Input Pembelian FOB Baru
    </div>
    <div class="card-body">
      <form method="post" action="{{ route('admin.fob-stocks.store') }}" novalidate>
        @csrf

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Buyer (FOB) <span class="text-danger">*</span></label>
            <select name="buyer_id" class="form-select @error('buyer_id') is-invalid @enderror" required>
              <option value="">— Pilih Buyer —</option>
              @foreach ($buyers as $buyer)
                <option value="{{ $buyer->id }}" {{ old('buyer_id') == $buyer->id ? 'selected' : '' }}>
                  {{ $buyer->name }}
                </option>
              @endforeach
            </select>
            @error('buyer_id')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Kode Material</label>
            <input type="text" name="material_code"
                   value="{{ old('material_code') }}"
                   class="form-control @error('material_code') is-invalid @enderror"
                   placeholder="Misal: YARN-001">
            @error('material_code')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Nama Material <span class="text-danger">*</span></label>
            <input type="text" name="material_name"
                   value="{{ old('material_name') }}"
                   class="form-control @error('material_name') is-invalid @enderror"
                   placeholder="Misal: Benang X 20s">
            @error('material_name')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-2">
            <label class="form-label">Unit <span class="text-danger">*</span></label>
            <input type="text" name="unit"
                   value="{{ old('unit') }}"
                   class="form-control @error('unit') is-invalid @enderror"
                   placeholder="pcs / roll / kg">
            @error('unit')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Qty (Stok Masuk) <span class="text-danger">*</span></label>
            <input type="number" name="quantity" id="qtyInput"
                   value="{{ old('quantity') }}"
                   min="0" step="0.0001"
                   class="form-control text-end @error('quantity') is-invalid @enderror"
                   placeholder="0">
            @error('quantity')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Harga Satuan (Rp) <span class="text-danger">*</span></label>
            <input type="number" name="unit_price" id="unitPriceInput"
                   value="{{ old('unit_price') }}"
                   min="0" step="0.01"
                   class="form-control text-end @error('unit_price') is-invalid @enderror"
                   placeholder="contoh: 5000">
            @error('unit_price')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
              Harga per 1 {{ old('unit') ?: 'unit' }} (contoh: 5000)
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Total (otomatis)</label>
            <div class="input-group">
              <span class="input-group-text">Rp</span>
              <input type="text"
                     id="totalPriceDisplay"
                     class="form-control text-end"
                     readonly
                     placeholder="0">
            </div>
            <div class="form-text">
              Total = Qty × Harga Satuan (hanya tampilan, disimpan di history).
            </div>
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-12">
            <label class="form-label">Catatan (opsional)</label>
            <textarea name="reason" rows="2"
                      class="form-control @error('reason') is-invalid @enderror"
                      placeholder="Misal: Pembelian harian FOB Buyer A dari Supplier X">{{ old('reason') }}</textarea>
            @error('reason')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <div class="mt-4 d-flex justify-content-end gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Simpan Pembelian FOB
          </button>
          <a href="{{ route('admin.fob-stocks.index') }}" class="btn btn-outline-secondary">
            Batal
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('js')
<script>
  (function () {
    const qtyEl   = document.getElementById('qtyInput');
    const priceEl = document.getElementById('unitPriceInput');
    const totalEl = document.getElementById('totalPriceDisplay');

    function toNumber(v) {
      if (!v) return 0;
      v = v.toString().replace(/\s/g, '').replace(',', '.');
      const n = parseFloat(v);
      return isNaN(n) ? 0 : n;
    }

    function formatRupiah(n) {
      if (!isFinite(n) || n <= 0) return '0';
      return n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    function updateTotal() {
      const qty   = toNumber(qtyEl.value);
      const price = toNumber(priceEl.value);
      const tot   = qty * price;
      totalEl.value = formatRupiah(tot);
    }

    if (qtyEl)   qtyEl.addEventListener('input', updateTotal);
    if (priceEl) priceEl.addEventListener('input', updateTotal);

    // init nilai awal (misal dari old())
    updateTotal();
  })();
</script>
@endpush
