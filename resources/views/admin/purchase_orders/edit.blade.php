@extends('layouts.master', ['title' => 'Edit Purchase Order'])

@section('content')
<div class="container">
  <h4 class="mb-4">Edit Purchase Order — {{ $purchaseOrder->po_number }}</h4>


  @php
    // Helper lokal: format qty tanpa nol belakang
    if (!function_exists('qty_fmt')) {
        function qty_fmt($n, $dec = 4) {
            $s = number_format((float)$n, $dec, '.', '');
            $s = rtrim(rtrim($s, '0'), '.');
            return $s === '' ? '0' : $s;
        }
    }
    // Siapkan rows awal (old() jika gagal validasi, fallback dari relasi items)
    $oldItems = old('items');
    $rows = is_array($oldItems)
      ? $oldItems
      : $purchaseOrder->items->map(function($it){
          return [
            'material_name'     => $it->material_name,
            'material_code'     => $it->material_code,   
            'unit'              => $it->unit,
            'ordered_quantity'  => qty_fmt($it->ordered_quantity),
          ];
        })->toArray();
  @endphp

  <form action="{{ route('admin.purchase-orders.update', $purchaseOrder) }}" method="POST" id="poForm">
    @csrf
    @method('PUT')

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Supplier</label>
        <select name="supplier_id" class="form-select" required>
          <option value="">— Pilih Supplier —</option>
          @foreach ($suppliers as $sup)
            <option value="{{ $sup->id }}" {{ old('supplier_id', $purchaseOrder->supplier_id) == $sup->id ? 'selected' : '' }}>
              {{ $sup->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Nomor PO</label>
        <input type="text" name="po_number" class="form-control"
               value="{{ old('po_number', $purchaseOrder->po_number) }}" maxlength="100" required>
      </div>

      <div class="col-md-2">
        <label class="form-label">Kedatangan</label>
        <input type="date" name="arrival_date" class="form-control"
               value="{{ old('arrival_date', optional($purchaseOrder->arrival_date)->toDateString()) }}">
      </div>

      <div class="col-md-2">
        <label class="form-label">Target Selesai</label>
        <input type="date" name="target_completion_date" class="form-control"
               value="{{ old('target_completion_date', optional($purchaseOrder->target_completion_date)->toDateString()) }}">
      </div>
    </div>

    <hr class="my-4">

    <div class="d-flex align-items-center justify-content-between mb-2">
      <h5 class="mb-0">Item PO</h5>
      <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow">
        <i class="fas fa-plus"></i> Tambah Baris
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered align-middle" id="itemsTable">
        <thead class="table-light">
          <tr>
            <th style="width: 30%">Material</th>
            <th style="width: 18%">Kode Barang</th> 
            <th style="width: 15%">Unit</th>
            <th class="text-end" style="width: 20%">Qty Dipesan</th>
            <th style="width: 10%">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $idx => $row)
            <tr>
              <td>
                <input type="text" class="form-control" name="items[{{ $idx }}][material_name]"
                       value="{{ old("items.$idx.material_name", $row['material_name'] ?? '') }}"
                       placeholder="Nama material" required>
              </td>
              <td>
                <input type="text" class="form-control" name="items[{{ $idx }}][material_code]"
                       value="{{ old("items.$idx.material_code", $row['material_code'] ?? '') }}"
                       placeholder="Contoh: LB-001">
              </td>
              <td>
                <input type="text" class="form-control" name="items[{{ $idx }}][unit]"
                       value="{{ old("items.$idx.unit", $row['unit'] ?? '') }}"
                       placeholder="pcs/kg/roll/..." required>
              </td>
              <td>
                <input type="number" step="0.0001" min="0.0001"
                       class="form-control text-end"
                       name="items[{{ $idx }}][ordered_quantity]"
                       value="{{ old("items.$idx.ordered_quantity", qty_fmt($row['ordered_quantity'] ?? '')) }}"
                       placeholder="0" required>
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
                  <i class="fas fa-times"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td>
                <input type="text" class="form-control" name="items[0][material_name]"
                       placeholder="Nama material" required>
              </td>
              <td>
                <input type="text" class="form-control" name="items[0][material_code]"
                       placeholder="Contoh: LB-001">
              </td>
              <td>
                <input type="text" class="form-control" name="items[0][unit]"
                       placeholder="pcs/kg/roll/..." required>
              </td>
              <td>
                <input type="number" step="0.0001" min="0.0001"
                       class="form-control text-end"
                       name="items[0][ordered_quantity]" placeholder="0" required>
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
                  <i class="fas fa-times"></i>
                </button>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Update PO
      </button>
      <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali
      </a>
    </div>
  </form>
</div>

{{-- Template baris tersembunyi untuk clone --}}
<template id="rowTemplate">
  <tr>
    <td>
      <input type="text" class="form-control" name="__INDEX__[material_name]"
             placeholder="Nama material" required>
    </td>
    <td>
      <input type="text" class="form-control" name="__INDEX__[material_code]"
             placeholder="Contoh: LB-001">
    </td>
    <td>
      <input type="text" class="form-control" name="__INDEX__[unit]"
             placeholder="pcs/kg/roll/..." required>
    </td>
    <td>
      <input type="number" step="0.0001" min="0.0001" class="form-control text-end"
             name="__INDEX__[ordered_quantity]" placeholder="0" required>
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
        <i class="fas fa-times"></i>
      </button>
    </td>
  </tr>
</template>

@push('scripts')
<script>
(function() {
  const tbl = document.getElementById('itemsTable').querySelector('tbody');
  const tpl = document.getElementById('rowTemplate').innerHTML;
  let idx = {{ count($rows) ? count($rows) : 1 }};

  document.getElementById('btnAddRow').addEventListener('click', function() {
    const html = tpl.replaceAll('__INDEX__', 'items[' + (idx++) + ']');
    const tr = document.createElement('tr');
    tr.innerHTML = html;
    tbl.appendChild(tr);
  });

  document.addEventListener('click', function(e) {
    if (e.target.closest('.btnRemoveRow')) {
      const rows = tbl.querySelectorAll('tr');
      if (rows.length <= 1) {
        const inputs = rows[0].querySelectorAll('input');
        inputs.forEach(i => i.value = '');
        return;
      }
      e.target.closest('tr').remove();
    }
  });
})();
</script>
@endpush
@endsection
