@extends('layouts.master', ['title' => 'Koreksi Receipt'])

@section('content')
<div class="container">

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif


  @php
    if (!function_exists('qty_fmt')) {
      function qty_fmt($n, $dec = 4) {
        $s = number_format((float)$n, $dec, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
      }
    }
  @endphp

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0">
      Koreksi Receipt — {{ $receipt->receipt_number }}
    </h4>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.purchase-orders.show', $po->id) }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Kembali ke PO
      </a>
    </div>
  </div>

  {{-- Info Receipt & PO --}}
  <div class="card mb-4">
    <div class="card-header bg-light">
      <strong>Informasi Receipt & Purchase Order</strong>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="text-muted small mb-1">NO Receipt</div>
          <div class="fw-semibold">{{ $receipt->receipt_number }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small mb-1">Tanggal Receipt</div>
          <div>{{ optional($receipt->receipt_date)->format('d-m-Y') ?? '-' }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small mb-1">Status</div>
          @if($receipt->status === \App\Models\PurchaseReceipt::STATUS_POSTED)
            <span class="badge bg-success">POSTED</span>
          @else
            <span class="badge bg-secondary">{{ strtoupper($receipt->status) }}</span>
          @endif
        </div>
      </div>

      <hr>

      <div class="row g-3">
        <div class="col-md-4">
          <div class="text-muted small mb-1">NO PO</div>
          <div>
            <a href="{{ route('admin.purchase-orders.show', $po->id) }}">
              {{ $po->po_number }}
            </a>
          </div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small mb-1">Supplier</div>
          <div class="fw-semibold">{{ optional($po->supplier)->name ?? '-' }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small mb-1">Catatan</div>
          <div>{{ $receipt->notes ?? '—' }}</div>
        </div>
      </div>

      <div class="mt-3 small text-muted">
        Koreksi ini akan:
        <ul class="mb-0">
          <li>Mengubah qty <strong>penerimaan</strong> di receipt ini.</li>
          <li>Mengupdate <strong>stok gudang</strong> (Stock, StockMovement, StockHistory).</li>
          <li>Menghitung ulang <strong>Diterima / Balance</strong> di PO dan status <strong>SELESAI / BELUM</strong>.</li>
        </ul>
      </div>
    </div>
  </div>

  {{-- Form Koreksi --}}
  <form method="POST" action="{{ route('admin.receipts.correction.update', $receipt) }}">
    @csrf
    @method('PUT')

    <div class="card mb-4">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong>Detail Item Receipt yang Dikoreksi</strong>
        <span class="small text-muted">
          Qty baru per baris tidak boleh melebihi batas maksimal (sesuai qty PO dikurangi penerimaan di receipt lain).
        </span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:45px;">No</th>
                <th>Material</th>
                <th style="width:80px;">Unit</th>
                <th class="text-end" style="width:110px;">Qty Order</th>
                <th class="text-end" style="width:140px;">Diterima di Receipt Lain</th>
                <th class="text-end" style="width:130px;">Qty Receipt Ini</th>
                <th class="text-end" style="width:140px;">Maks. Qty Receipt Ini</th>
              </tr>
            </thead>
            <tbody>
              @forelse($receipt->items as $i => $it)
                @php
                  $ordered      = (float) ($it->ordered_quantity ?? 0);
                  $totalPosted  = (float) ($it->total_posted_all ?? 0);
                  $current      = (float) $it->received_quantity;
                  $maxEditable  = (float) ($it->max_quantity_editable ?? $ordered);
                  $inputMax     = max($maxEditable, $current);
                  $postedOther  = $totalPosted - $current;
                @endphp
                <tr>
                  <td class="text-center">{{ $i + 1 }}</td>
                  <td>
                    <div class="fw-semibold">{{ $it->material_name }}</div>
                    @if(optional($it->orderItem)->material_code)
                      <div class="small text-muted">[{{ $it->orderItem->material_code }}]</div>
                    @endif
                  </td>
                  <td class="text-center">{{ $it->unit }}</td>
                  <td class="text-end">{{ qty_fmt($ordered) }}</td>
                  <td class="text-end">
                    {{ qty_fmt(max(0, $postedOther)) }}
                  </td>
                  <td class="text-end" style="width:130px;">
                    <input
                      type="number"
                      step="0.0001"
                      min="0"
                      max="{{ $inputMax }}"
                      name="items[{{ $it->id }}][received_quantity]"
                      value="{{ old('items.'.$it->id.'.received_quantity', $current) }}"
                      class="form-control form-control-sm text-end"
                    >
                  </td>
                  <td class="text-end">
                    {{ qty_fmt($maxEditable) }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center text-muted py-3">
                    Tidak ada item pada receipt ini.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Alasan Koreksi --}}
    <div class="card mb-4">
      <div class="card-header bg-light">
        <strong>Alasan Koreksi</strong>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">
            Jelaskan singkat kenapa qty penerimaan perlu dikoreksi <span class="text-danger">*</span>
          </label>
          <textarea
            name="reason"
            rows="3"
            class="form-control"
            required
          >{{ old('reason') }}</textarea>
        </div>

        @if(!empty($canForceCorrection))
          @php $forceChecked = old('force_correction') ? true : false; @endphp
          <div class="border rounded p-3 mb-3 bg-light">
            <div class="form-check mb-2">
              <input
                class="form-check-input"
                type="checkbox"
                name="force_correction"
                id="force_correction"
                value="1"
                {{ $forceChecked ? 'checked' : '' }}
              >
              <label class="form-check-label fw-semibold" for="force_correction">
                Force Correction (Super Admin Only)
              </label>
            </div>
            <div class="small text-muted mb-2">
              Gunakan hanya untuk koreksi salah input historis. Opsi ini mengizinkan stok menjadi minus sementara
              dan semua perubahan akan dicatat pada audit log.
            </div>
            <div id="force-reason-wrapper" class="{{ $forceChecked ? '' : 'd-none' }}">
              <label class="form-label mb-1">
                Alasan Force Correction <span class="text-danger">*</span>
              </label>
              <textarea
                name="force_reason"
                rows="2"
                class="form-control"
              >{{ old('force_reason') }}</textarea>
            </div>
          </div>
        @endif

        <div class="alert alert-warning mb-0 small">
          <strong>Perhatian:</strong> Setelah disimpan, stok gudang dan status PO akan mengikuti angka baru.
          Gunakan fitur ini hanya untuk membetulkan kesalahan input penerimaan, bukan untuk transaksi keluar.
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <a href="{{ route('admin.purchase-orders.show', $po->id) }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Batal
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Simpan Koreksi
      </button>
    </div>
  </form>
</div>

@if(!empty($canForceCorrection))
  <script>
    (function () {
      const toggle = document.getElementById('force_correction');
      const wrapper = document.getElementById('force-reason-wrapper');
      if (!toggle || !wrapper) return;

      const sync = () => {
        if (toggle.checked) {
          wrapper.classList.remove('d-none');
        } else {
          wrapper.classList.add('d-none');
        }
      };

      toggle.addEventListener('change', sync);
      sync();
    })();
  </script>
@endif
@endsection
