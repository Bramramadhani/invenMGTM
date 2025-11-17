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
                <input type="text" class="form-control"
                       name="items[{{ $idx }}][material_name]"
                       value="{{ old("items.$idx.material_name", $row['material_name'] ?? '') }}"
                       placeholder="Nama material" required>
              </td>
              <td>
                <input type="text" class="form-control item-material-code"
                       name="items[{{ $idx }}][material_code]"
                       value="{{ old("items.$idx.material_code", $row['material_code'] ?? '') }}"
                       placeholder="Contoh: LB-001">
              </td>
              <td>
                <input type="text" class="form-control"
                       name="items[{{ $idx }}][unit]"
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
                <input type="text" class="form-control"
                       name="items[0][material_name]"
                       placeholder="Nama material" required>
              </td>
              <td>
                <input type="text" class="form-control item-material-code"
                       name="items[0][material_code]"
                       placeholder="Contoh: LB-001">
              </td>
              <td>
                <input type="text" class="form-control"
                       name="items[0][unit]"
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

<script>
  // Hitung index awal: kalau ada rows dari DB/old(), mulai dari count($rows), kalau kosong mulai 1 (karena index 0 sudah dipakai di fallback row)
  let nextIdx = {{ count($rows) ? count($rows) : 1 }};
  const tbody = document.querySelector('#itemsTable tbody');

  // Tambah baris baru
  document.getElementById('btnAddRow').addEventListener('click', function () {
    const i = nextIdx++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <input type="text" class="form-control"
               name="items[${i}][material_name]"
               placeholder="Nama material" required>
      </td>
      <td>
        <input type="text" class="form-control item-material-code"
               name="items[${i}][material_code]"
               placeholder="Kode Barang">
      </td>
      <td>
        <input type="text" class="form-control"
               name="items[${i}][unit]"
               placeholder="pcs/Kg/Meter" required>
      </td>
      <td>
        <input type="number" step="0.0001" min="0.0001"
               class="form-control text-end"
               name="items[${i}][ordered_quantity]"
               placeholder="0" required>
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
          <i class="fas fa-times"></i>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  // Hapus baris (minimal selalu ada 1 baris: kalau tinggal 1, cukup kosongkan input)
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btnRemoveRow');
    if (!btn) return;

    const rows = tbody.querySelectorAll('tr');
    if (rows.length <= 1) {
      rows[0].querySelectorAll('input').forEach(i => i.value = '');
      return;
    }

    btn.closest('tr').remove();
  });

  // Auto-uppercase Kode Barang (sama seperti di halaman create)
  document.addEventListener('input', function(e){
    if (e.target && e.target.classList.contains('item-material-code')) {
      e.target.value = e.target.value.toUpperCase();
    }
  });
</script>
@endsection
