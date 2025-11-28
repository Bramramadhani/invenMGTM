@extends('layouts.master', ['title' => 'Edit Stok'])

@section('content')
<div class="container">

  @php
    if (!function_exists('qty_fmt')) {
      function qty_fmt($n, int $dec = 4): string {
        $s = number_format((float)$n, $dec, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
      }
    }

    $po   = optional($stock->purchaseOrder);
    $poId = $po?->id;
    $poNo = $po?->po_number;
  @endphp

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0">Edit Stok — {{ $stock->material_name }}</h4>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.stock.index', ['group' => 'flat']) }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
      </a>
      @if($poId && $poNo)
        <a href="{{ route('admin.purchase-orders.show', $poId) }}" class="btn btn-outline-primary" target="_blank">
          <i class="fas fa-file-alt"></i> Lihat PO {{ $poNo }}
        </a>
      @endif
    </div>
  </div>

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

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-light">
          <strong>Form Edit Stok Manual</strong>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.stock.update', $stock) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
              <label class="form-label">Nama Material <span class="text-danger">*</span></label>
              <input type="text"
                     name="material_name"
                     class="form-control @error('material_name') is-invalid @enderror"
                     value="{{ old('material_name', $stock->material_name) }}"
                     required>
              @error('material_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Kode Material</label>
              <input type="text"
                     name="material_code"
                     class="form-control @error('material_code') is-invalid @enderror"
                     value="{{ old('material_code', $stock->material_code) }}">
              @error('material_code')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Unit</label>
              <input type="text"
                     name="unit"
                     class="form-control @error('unit') is-invalid @enderror"
                     value="{{ old('unit', $stock->unit) }}">
              @error('unit')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Quantity <span class="text-danger">*</span></label>
              <input type="number"
                     name="quantity"
                     step="0.0001"
                     min="0"
                     class="form-control @error('quantity') is-invalid @enderror"
                     value="{{ old('quantity', qty_fmt($stock->quantity)) }}"
                     required>
              @error('quantity')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <div class="form-text">
                Nilai saat ini: <strong>{{ qty_fmt($stock->quantity) }}</strong>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Alasan Perubahan <span class="text-danger">*</span></label>
              <textarea name="reason"
                        rows="3"
                        class="form-control @error('reason') is-invalid @enderror"
                        placeholder="Contoh: Koreksi stok awal per 01/11/2025, data lama salah input.">{{ old('reason') }}</textarea>
              @error('reason')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <div class="text-muted small">
                <strong>Catatan:</strong><br>
                Edit stok manual di sini <strong>hanya</strong> mengubah tabel stok dan mencatat log di
                <code>stock_histories</code>. Purchase Receipt dan StockMovement tidak ikut berubah.
              </div>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Perubahan
              </button>
            </div>

          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-light">
          <strong>Info Stok</strong>
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-5">Supplier</dt>
            <dd class="col-7">
              {{ optional($stock->supplier)->name ?? '—' }}
            </dd>

            <dt class="col-5">NO PO</dt>
            <dd class="col-7">
              @if($poId && $poNo)
                <a href="{{ route('admin.purchase-orders.show', $poId) }}" target="_blank">
                  {{ $poNo }}
                </a>
              @else
                —
              @endif
            </dd>

            <dt class="col-5">Qty Saat Ini</dt>
            <dd class="col-7">
              <strong>{{ qty_fmt($stock->quantity) }}</strong> {{ $stock->unit ?? '' }}
            </dd>

            <dt class="col-5">Updated At</dt>
            <dd class="col-7">
              {{ optional($stock->updated_at)->format('d-m-Y H:i') ?? '—' }}
            </dd>
          </dl>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header bg-light">
          <strong>Riwayat Stok</strong>
        </div>
        <div class="card-body">
          <p class="mb-2 small text-muted">
            Lihat jejak perubahan stok (edit/hapus manual, dsb) untuk material ini.
          </p>
          <a href="{{ route('admin.stock.history', $stock) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-history"></i> Lihat Riwayat
          </a>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
