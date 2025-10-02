@extends('layouts.master', ['title' => 'Buat Purchase Order'])

@section('content')
<div class="container">

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
      </ul>
    </div>
  @endif

  <h4 class="mb-3">Buat Purchase Order</h4>

  <form id="poForm" method="post" action="{{ route('admin.purchase-orders.store') }}" autocomplete="off">
    @csrf

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Supplier <span class="text-danger">*</span></label>
        <select class="form-select" name="supplier_id" required>
          <option value="">-- pilih --</option>
          @foreach($suppliers as $s)
            <option value="{{ $s->id }}" @selected(old('supplier_id')==$s->id)>{{ $s->name }}</option>
          @endforeach
        </select>
        @error('supplier_id')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
      </div>

      <div class="col-md-3">
        <label class="form-label">No. PO <span class="text-danger">*</span></label>
        <input class="form-control" type="text" name="po_number" value="{{ old('po_number') }}" placeholder="PO123" required>
        @error('po_number')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
      </div>

      <div class="col-md-3">
        <label class="form-label">Target Selesai</label>
        <input class="form-control" type="date" name="target_completion_date" value="{{ old('target_completion_date') }}">
        @error('target_completion_date')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
      </div>
    </div>

    <div class="row mt-3">
      <div class="col-12">
        <label class="form-label">Catatan PO </label>
        <textarea class="form-control" name="notes" rows="2" placeholder="Catatan untuk PO">{{ old('notes') }}</textarea>
        @error('notes')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
      </div>
    </div>

    <hr class="my-4">

    <div class="d-flex align-items-center justify-content-between">
      <h5 class="mb-2">Item</h5>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">
          <i class="fas fa-plus"></i> Tambah Baris
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered align-middle" id="itemsTable">
        <thead class="table-light">
          <tr>
            <th style="width: 36px">#</th>
            <th>Material <span class="text-danger">*</span></th>
            <th style="width: 180px">Kode Barang</th>
            <th style="width: 140px">Unit <span class="text-danger">*</span></th>
            <th style="width: 180px" class="text-end">Qty Dipesan <span class="text-danger">*</span></th>
            <th style="width: 60px"></th>
          </tr>
        </thead>
        <tbody>
          @php
            $old = old('items', [['material_name'=>'','material_code'=>'','unit'=>'','ordered_quantity'=>null]]);
          @endphp
          @foreach($old as $i=>$row)
            <tr>
              <td class="text-center align-middle">{{ $i+1 }}</td>
              <td>
                <input class="form-control"
                       name="items[{{ $i }}][material_name]"
                       value="{{ $row['material_name'] ?? '' }}"
                       required
                       placeholder="Nama material">
                @error("items.$i.material_name")
                  <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </td>
              <td>
                <input class="form-control item-material-code"
                       name="items[{{ $i }}][material_code]"
                       value="{{ $row['material_code'] ?? '' }}"
                       maxlength="64"
                       placeholder="Kode Barang">
                @error("items.$i.material_code")
                  <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </td>
              <td>
                <input class="form-control"
                       type="text"
                       name="items[{{ $i }}][unit]"
                       value="{{ $row['unit'] ?? '' }}"
                       placeholder="pcs/kg/meter"
                       required>
                @error("items.$i.unit")
                  <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </td>
              <td>
                <input class="form-control text-end"
                       type="number"
                       inputmode="decimal"
                       step="0.0001"
                       min="0.0001"
                       name="items[{{ $i }}][ordered_quantity]"
                       value="{{ $row['ordered_quantity'] ?? '' }}"
                       required
                       placeholder="0">
                @error("items.$i.ordered_quantity")
                  <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">&times;</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button id="btnSubmit" class="btn btn-primary">
        <span class="btn-text"><i class="fas fa-save"></i> Simpan</span>
        <span class="btn-busy d-none"><i class="fas fa-spinner fa-spin"></i> Menyimpan...</span>
      </button>
      <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">Batal</a>
    </div>
  </form>
</div>

<script>
let nextIdx = (() => {
  const rows = document.querySelectorAll('#itemsTable tbody tr').length;
  return rows;
})();

function addRow() {
  const tbody = document.querySelector('#itemsTable tbody');
  const i = nextIdx++;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="text-center align-middle"></td>
    <td>
      <input class="form-control" name="items[${i}][material_name]" required placeholder="Nama material">
    </td>
    <td>
      <input class="form-control item-material-code" name="items[${i}][material_code]" maxlength="64" placeholder="Contoh: LB-001">
    </td>
    <td>
      <input class="form-control" type="text" name="items[${i}][unit]" placeholder="pcs/kg/meter" required>
    </td>
    <td>
      <input class="form-control text-end" type="number" inputmode="decimal" step="0.0001" min="0.0001"
             name="items[${i}][ordered_quantity]" required placeholder="0">
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">&times;</button>
    </td>
  `;
  tbody.appendChild(tr);
  renumber();
}

function removeRow(btn) {
  const tr = btn.closest('tr');
  tr.remove();
  renumber();
}

function renumber() {
  document.querySelectorAll('#itemsTable tbody tr').forEach((row, i) => {
    row.cells[0].innerText = i + 1;
  });
}

// Auto-uppercase Kode Barang
document.addEventListener('input', function(e){
  if (e.target && e.target.classList.contains('item-material-code')) {
    e.target.value = e.target.value.toUpperCase();
  }
});

// Anti double-submit
document.getElementById('poForm').addEventListener('submit', function(e){
  const btn = document.getElementById('btnSubmit');
  const text = btn.querySelector('.btn-text');
  const busy = btn.querySelector('.btn-busy');
  btn.disabled = true;
  text.classList.add('d-none');
  busy.classList.remove('d-none');
});
</script>
@endsection
