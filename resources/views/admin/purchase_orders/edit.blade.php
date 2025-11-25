@extends('layouts.master', ['title' => 'Edit Purchase Order'])

@section('content')
<div class="container">
  <h4 class="mb-4">Edit Purchase Order — {{ $purchaseOrder->po_number }}</h4>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
      </ul>
    </div>
  @endif

  @php
    // Helper lokal: format qty tanpa nol belakang
    if (!function_exists('qty_fmt')) {
        function qty_fmt($n, $dec = 4) {
            $s = number_format((float)$n, $dec, '.', '');
            $s = rtrim(rtrim($s, '0'), '.');
            return $s === '' ? '0' : $s;
        }
    }

    $hasPostedReceipts = $purchaseOrder->hasPostedReceipt();

    // Siapkan rows awal item (old() jika gagal validasi, fallback dari relasi items)
    $oldItems = old('items');
    $rows = is_array($oldItems)
      ? $oldItems
      : $purchaseOrder->items->map(function($it){
          return [
            'id'                => $it->id,
            'material_name'     => $it->material_name,
            'material_code'     => $it->material_code,
            'unit'              => $it->unit,
            'ordered_quantity'  => qty_fmt($it->ordered_quantity),
          ];
        })->toArray();

    // Siapkan rows awal styles (old() jika gagal validasi, fallback dari relasi styles)
    $oldStyles = old('styles');
    $styleRows = is_array($oldStyles)
      ? $oldStyles
      : $purchaseOrder->styles->map(function($st){
          return [
            'style_name'      => $st->style_name,
            'style_quantity'  => $st->style_quantity,
          ];
        })->toArray();

    if (empty($styleRows)) {
        $styleRows = [
          ['style_name' => '', 'style_quantity' => null],
        ];
    }
  @endphp

  @if($hasPostedReceipts)
    <div class="alert alert-warning">
      PO ini <strong>sudah memiliki penerimaan berstatus POSTED</strong>.<br>
      Anda masih bisa <strong>menambah item baru</strong> atau
      <strong>mengubah Qty PO</strong>, tetapi:
      <ul class="mb-0">
        <li>Item yang sudah pernah diterima <strong>tidak boleh dihapus</strong>.</li>
        <li>Qty PO <strong>tidak boleh lebih kecil</strong> dari total yang sudah diterima.</li>
      </ul>
    </div>
  @endif

  <form action="{{ route('admin.purchase-orders.update', $purchaseOrder) }}" method="POST" id="poForm">
    @csrf
    @method('PUT')

    {{-- HEADER PO --}}
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

    <div class="row mt-3">
      <div class="col-12">
        <label class="form-label">Catatan PO</label>
        <textarea class="form-control" name="notes" rows="2" placeholder="Catatan untuk PO">{{ old('notes', $purchaseOrder->notes) }}</textarea>
      </div>
    </div>

    <hr class="my-4">

    {{-- ITEM PO (MATERIAL) --}}
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
              {{-- hidden ID untuk item lama (jika ada) --}}
              <input type="hidden"
                     name="items[{{ $idx }}][id]"
                     value="{{ old("items.$idx.id", $row['id'] ?? '') }}">

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

    {{-- STYLES PO --}}
    <hr class="my-4">

    <div class="d-flex align-items-center justify-content-between mb-2">
      <h5 class="mb-0">Styles (Pembagian Style Tas)</h5>
      <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddStyleRow">
        <i class="fas fa-plus"></i> Tambah Style
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered align-middle" id="stylesTable">
        <thead class="table-light">
          <tr>
            <th style="width: 36px">#</th>
            <th>Nama Style</th>
            <th style="width: 180px" class="text-end">Qty Style</th>
            <th style="width: 60px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($styleRows as $i => $style)
            <tr>
              <td class="text-center align-middle">{{ $i + 1 }}</td>
              <td>
                <input class="form-control"
                       name="styles[{{ $i }}][style_name]"
                       value="{{ old("styles.$i.style_name", $style['style_name'] ?? '') }}"
                       placeholder="Contoh: STYLE A">
                @error("styles.$i.style_name")
                  <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </td>
              <td>
                <input class="form-control text-end"
                       type="number"
                       min="1"
                       name="styles[{{ $i }}][style_quantity]"
                       value="{{ old("styles.$i.style_quantity", $style['style_quantity'] ?? '') }}"
                       placeholder="Qty">
                @error("styles.$i.style_quantity")
                  <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btnRemoveStyleRow">
                  <i class="fas fa-times"></i>
                </button>
              </td>
            </tr>
          @endforeach
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
  // === Item PO (material) ===
  let nextIdx = {{ count($rows) ? count($rows) : 1 }};
  const itemsTbody = document.querySelector('#itemsTable tbody');

  document.getElementById('btnAddRow').addEventListener('click', function () {
    const i = nextIdx++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <input type="hidden" name="items[${i}][id]" value="">
      <td>
        <input type="text" class="form-control"
               name="items[${i}][material_name]"
               placeholder="Nama material" required>
      </td>
      <td>
        <input type="text" class="form-control item-material-code"
               name="items[${i}][material_code]"
               placeholder="Contoh: LB-001">
      </td>
      <td>
        <input type="text" class="form-control"
               name="items[${i}][unit]"
               placeholder="pcs/kg/roll/..." required>
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
    itemsTbody.appendChild(tr);
  });

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btnRemoveRow');
    if (!btn) return;

    const rows = itemsTbody.querySelectorAll('tr');
    if (rows.length <= 1) {
      rows[0].querySelectorAll('input').forEach(i => {
        if (i.type === 'hidden') {
          i.value = '';
        } else {
          i.value = '';
        }
      });
      return;
    }

    btn.closest('tr').remove();
  });

  // === Styles PO ===
  let nextStyleIdx = {{ count($styleRows) ? count($styleRows) : 1 }};
  const stylesTbody = document.querySelector('#stylesTable tbody';

  function renumberStyles() {
    stylesTbody.querySelectorAll('tr').forEach((row, i) => {
      row.cells[0].innerText = i + 1;
    });
  }

  document.getElementById('btnAddStyleRow').addEventListener('click', function () {
    const i = nextStyleIdx++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="text-center align-middle"></td>
      <td>
        <input class="form-control"
               name="styles[${i}][style_name]"
               placeholder="Contoh: STYLE A">
      </td>
      <td>
        <input class="form-control text-end"
               type="number"
               min="1"
               name="styles[${i}][style_quantity]"
               placeholder="Qty">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveStyleRow">
          <i class="fas fa-times"></i>
        </button>
      </td>
    `;
    stylesTbody.appendChild(tr);
    renumberStyles();
  });

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btnRemoveStyleRow');
    if (!btn) return;

    const rows = stylesTbody.querySelectorAll('tr');
    if (rows.length <= 1) {
      rows[0].querySelectorAll('input').forEach(i => i.value = '');
      return;
    }

    btn.closest('tr').remove();
    renumberStyles();
  });

  // Auto-uppercase Kode Barang
  document.addEventListener('input', function(e){
    if (e.target && e.target.classList.contains('item-material-code')) {
      e.target.value = e.target.value.toUpperCase();
    }
  });
</script>
@endsection
