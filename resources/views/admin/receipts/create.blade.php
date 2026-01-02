@extends('layouts.master', ['title' => 'Terima Parsial — '.$purchaseOrder->po_number])

@section('content')
<div class="container">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Terima Parsial — {{ $purchaseOrder->po_number }}</h4>
    <a href="{{ route('admin.purchase-orders.show', $purchaseOrder) }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Kembali ke PO
    </a>
  </div>

  @if ($errors->any())
  <div class="alert alert-danger">
    <div class="fw-bold mb-1">Periksa kembali isian Anda:</div>
    <ul class="mb-0">
      @foreach ($errors->all() as $err)
      <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  @php
  if (!function_exists('qty_fmt')) {
  function qty_fmt($n, int $dec = 4): string {
  $s = number_format((float)$n, $dec, '.', '');
  $s = rtrim(rtrim($s, '0'), '.');
  return $s === '' ? '0' : $s;
  }
  }
  @endphp

  <form method="post" action="{{ route('admin.receipts.store', $purchaseOrder) }}">
    @csrf
    @isset($idempotencyToken)
    <input type="hidden" name="idempotency_token" value="{{ $idempotencyToken }}">
    @endisset

    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Tanggal Penerimaan <span class="text-danger">*</span></label>
            <input type="date" name="receipt_date" class="form-control"
              value="{{ old('receipt_date', now()->toDateString()) }}" required autocomplete="off">
            @error('receipt_date')
            <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
          </div>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:60px">NO</th>
            <th>MATERIAL</th>
            <th class="text-center" style="width:80px">UNIT</th>
            <th class="text-center" style="width:150px">DIPESAN</th>
            <th class="text-center" style="width:150px">SUDAH DITERIMA</th>
            <th class="text-center" style="width:200px">QTY DITERIMA</th>
            <th style="width:200px">CATATAN</th>
          </tr>
        </thead>
        <tbody>
          @forelse($purchaseOrder->items as $row)
          @php
          $ordered = (float) $row->ordered_quantity;
          $received = (float) ($row->received_total ?? 0);
          $remaining = max(0, $ordered - $received);
          $oldQtyRaw = old("items.{$loop->index}.received_quantity");
          $oldQty = $oldQtyRaw === null ? '' : qty_fmt((float)$oldQtyRaw);
          $oldNotes = old("items.{$loop->index}.notes");
          @endphp
          <tr data-index="{{ $loop->index }}">
            <input type="hidden" name="items[{{ $loop->index }}][purchase_order_item_id]" value="{{ $row->id }}">

            <td class="text-center">{{ $loop->iteration }}</td>
            <td>
              <div class="fw-semibold">{{ $row->material_name }}</div>
            </td>
            <td class="text-center align-middle">{{ $row->unit }}</td>
            <td class="text-center align-middle">{{ qty_fmt($ordered) }}</td>
            <td class="text-center align-middle">
              {{ qty_fmt($received) }}
              @if($received > $ordered)
              <span class="badge bg-danger ms-2">Over</span>
              @endif
            </td>

            <td class="align-middle">
              <div class="input-group">
                <input
                  type="number" step="0.0001" min="0"
                  class="form-control text-center"
                  name="items[{{ $loop->index }}][received_quantity]"
                  value="{{ $oldQty }}"
                  placeholder="0"
                  aria-describedby="help-qty-{{ $loop->index }}">
                <span class="input-group-text">{{ $row->unit }}</span>
              </div>
              <small id="help-qty-{{ $loop->index }}" class="text-muted d-block text-center">
                Sisa (berdasar PO): {{ qty_fmt($remaining) }}
              </small>
              @error("items.{$loop->index}.received_quantity")
              <div class="text-danger small mt-1">{{ $message }}</div>
              @enderror
            </td>

            <td class="align-middle">
              <input
                type="text" class="form-control"
                name="items[{{ $loop->index }}][notes]"
                value="{{ $oldNotes ?? '' }}"
                placeholder="Catatan per item (opsional)">
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center text-muted">Tidak ada item pada PO ini.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-primary">
        <i class="fas fa-save"></i> Simpan DRAFT
      </button>
      <a href="{{ route('admin.purchase-orders.show', $purchaseOrder) }}" class="btn btn-outline-secondary">Batal</a>
    </div>
  </form>
</div>
@endsection