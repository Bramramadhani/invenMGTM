@extends('layouts.master', ['title' => 'Buat Purchase Order'])

@section('content')
<div class="container">

 
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="mb-1">Buat Purchase Order</h4>
      <div class="text-muted small">
        Langkah: <span class="fw-semibold">1. Info PO</span> → 2. Styles → 3. Item Material
      </div>
    </div>
    <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left"></i> Kembali ke daftar
    </a>
  </div>

  @php
    $oldItems = old('items', [
      ['material_name'=>'','material_code'=>'','unit'=>'','ordered_quantity'=>null]
    ]);
    $oldStyles = old('styles', [
      ['style_name'=>'','style_quantity'=>null]
    ]);
  @endphp

  <form id="poForm" method="post" action="{{ route('admin.purchase-orders.store') }}" autocomplete="off">
    @csrf

    @php
      $oldStockSource = old('stock_source', 'po');
      $oldStockSource = $oldStockSource === 'fob_full' ? 'fob_full' : 'po';
    @endphp

    {{-- ==================== SECTION 1: INFO PO ==================== --}}
    <div class="card mb-4">
      <div class="card-header bg-light">
        <strong>1. Informasi Purchase Order</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Supplier <span class="text-danger">*</span></label>
            <select class="form-select" name="supplier_id" required>
              <option value="">-- pilih Buyer --</option>
              @foreach($suppliers as $s)
                <option value="{{ $s->id }}" @selected(old('supplier_id')==$s->id)>{{ $s->name }}</option>
              @endforeach
            </select>
            @error('supplier_id')
              <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Sumber Stok <span class="text-danger">*</span></label>
            <select class="form-select" name="stock_source" id="stockSource" required>
              <option value="po" {{ $oldStockSource === 'po' ? 'selected' : '' }}>PO (Penerimaan/Receipt)</option>
              <option value="fob_full" {{ $oldStockSource === 'fob_full' ? 'selected' : '' }}>FULL FOB (tanpa penerimaan)</option>
            </select>
            <div class="form-text">
              Jika memilih <strong>FULL FOB</strong>, PO ini tidak memakai penerimaan/receipt dan item material tidak perlu diisi.
            </div>
            @error('stock_source')
              <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Nomor PO <span class="text-danger">*</span></label>
            <input class="form-control"
                   type="text"
                   name="po_number"
                   value="{{ old('po_number') }}"
                   placeholder="Masukkan PO"
                   required>
            @error('po_number')
              <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
          </div>

          {{-- Kedatangan (opsional) DIHAPUS --}}
          <div class="col-md-4" id="colTargetDate">
            <label class="form-label">Target Selesai</label>
            <input class="form-control"
                   type="date"
                   name="target_completion_date"
                   value="{{ old('target_completion_date') }}">
            @error('target_completion_date')
              <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Catatan PO </label>
          <textarea class="form-control"
                    name="notes"
                    rows="2"
                    placeholder="Contoh: Tas delivery kampanye Q1, harap diprioritaskan.">{{ old('notes') }}</textarea>
          @error('notes')
            <div class="text-danger small mt-1">{{ $message }}</div>
          @enderror
        </div>
      </div>
    </div>

    {{-- ==================== SECTION 2: STYLES ==================== --}}
    <div class="card mb-4">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <div>
          <strong>2. Styles </strong>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddStyleRow">
          <i class="fas fa-plus"></i> Tambah Style
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0" id="stylesTable">
            <thead class="table-light">
              <tr>
                <th style="width:60px;" class="text-center">#</th>
                <th>Nama Style</th>
                <th style="width:200px;" class="text-end">Qty Tas</th>
              </tr>
            </thead>
            <tbody>
              @foreach($oldStyles as $i => $style)
                <tr>
                  <td class="text-center align-middle">{{ $i+1 }}</td>
                  <td>
                    <input class="form-control"
                           name="styles[{{ $i }}][style_name]"
                           value="{{ $style['style_name'] ?? '' }}"
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
                           value="{{ $style['style_quantity'] ?? '' }}"
                           placeholder="Qty tas">
                    @error("styles.$i.style_quantity")
                      <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                  </td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <th colspan="2" class="text-end">Total Qty Tas</th>
                <th class="text-end">
                  <span id="totalStyleQty" class="fw-semibold">0</span>
                </th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    {{-- ==================== SECTION 3: ITEM MATERIAL ==================== --}}
    <div class="card mb-4" id="cardItems">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <div>
          <strong>3. Item Material</strong>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddItemRow">
          <i class="fas fa-plus"></i> Tambah Baris Material
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0" id="itemsTable">
            <thead class="table-light">
              <tr>
                <th style="width:36px;" class="text-center">#</th>
                <th>Material <span class="text-danger">*</span></th>
                <th style="width:180px;">Kode Barang</th>
                <th style="width:140px;">Unit <span class="text-danger">*</span></th>
                <th style="width:160px;" class="text-end">Qty Dipesan <span class="text-danger">*</span></th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($oldItems as $i => $row)
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
                           placeholder="Contoh: LB-001">
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
                    <button type="button" class="btn btn-sm btn-outline-danger btnRemoveItemRow">
                      &times;
                    </button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- ==================== ACTION BUTTONS ==================== --}}
    <div class="d-flex gap-2">
      <button id="btnSubmit" class="btn btn-primary">
        <span class="btn-text">
          <i class="fas fa-save"></i> Simpan PO
        </span>
        <span class="btn-busy d-none">
          <i class="fas fa-spinner fa-spin"></i> Menyimpan...
        </span>
      </button>
      <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">
        Batal
      </a>
    </div>
  </form>
</div>

<script>
  // ====== Inisialisasi index ======
  let styleIdx = {{ count($oldStyles) }};
  let itemIdx  = {{ count($oldItems) }};

  const stylesTbody = document.querySelector('#stylesTable tbody');
  const itemsTbody  = document.querySelector('#itemsTable tbody');
  const totalStyleQtyEl = document.getElementById('totalStyleQty');

  // ====== Helper: renumber no urut ======
  function renumberRows(tbody) {
    tbody.querySelectorAll('tr').forEach((row, i) => {
      const cell = row.querySelector('td');
      if (cell) cell.innerText = i + 1;
    });
  }

  // ====== Hitung total qty style ======
  function recalcTotalStyleQty() {
    let total = 0;
    stylesTbody.querySelectorAll('input[name*="[style_quantity]"]').forEach(inp => {
      const v = parseFloat(inp.value);
      if (!isNaN(v)) total += v;
    });
    totalStyleQtyEl.textContent = total.toLocaleString('id-ID');
  }

  // ====== Tambah baris STYLE ======
  document.getElementById('btnAddStyleRow').addEventListener('click', function () {
    const i = styleIdx++;
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
               placeholder="Qty tas">
      </td>
    `;
    stylesTbody.appendChild(tr);
    renumberRows(stylesTbody);
  });

  // ====== Tambah baris ITEM ======
  document.getElementById('btnAddItemRow').addEventListener('click', function () {
    const i = itemIdx++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="text-center align-middle"></td>
      <td>
        <input class="form-control"
               name="items[${i}][material_name]"
               required
               placeholder="Nama material">
      </td>
      <td>
        <input class="form-control item-material-code"
               name="items[${i}][material_code]"
               maxlength="64"
               placeholder="Contoh: LB-001">
      </td>
      <td>
        <input class="form-control"
               type="text"
               name="items[${i}][unit]"
               placeholder="pcs/kg/meter"
               required>
      </td>
      <td>
        <input class="form-control text-end"
               type="number"
               inputmode="decimal"
               step="0.0001"
               min="0.0001"
               name="items[${i}][ordered_quantity]"
               required
               placeholder="0">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveItemRow">
          &times;
        </button>
      </td>
    `;
    itemsTbody.appendChild(tr);
    renumberRows(itemsTbody);
  });

  // ====== Hapus baris ITEM ======
  document.addEventListener('click', function(e){
    const btnItem = e.target.closest('.btnRemoveItemRow');
    if (btnItem) {
      const rows = itemsTbody.querySelectorAll('tr');
      if (rows.length <= 1) {
        rows[0].querySelectorAll('input').forEach(inp => inp.value = '');
      } else {
        btnItem.closest('tr').remove();
        renumberRows(itemsTbody);
      }
    }
  });

  // Hitung ulang total qty style saat input berubah
  document.addEventListener('input', function(e){
    if (e.target && e.target.name && e.target.name.includes('[style_quantity]')) {
      recalcTotalStyleQty();
    }
    // Auto uppercase untuk kode material
    if (e.target && e.target.classList.contains('item-material-code')) {
      e.target.value = e.target.value.toUpperCase();
    }
  });

  recalcTotalStyleQty();
  renumberRows(stylesTbody);
  renumberRows(itemsTbody);

  // Anti double-submit
  document.getElementById('poForm').addEventListener('submit', function(){
    const btn = document.getElementById('btnSubmit');
    const text = btn.querySelector('.btn-text');
    const busy = btn.querySelector('.btn-busy');
    btn.disabled = true;
    text.classList.add('d-none');
    busy.classList.remove('d-none');
  });

  // ====== Toggle UI untuk mode FULL FOB ======
  const stockSourceEl  = document.getElementById('stockSource');
  const itemsCardEl    = document.getElementById('cardItems');
  const targetDateCol  = document.getElementById('colTargetDate');
  const addItemBtn     = document.getElementById('btnAddItemRow');

  function setItemsRequired(enabled) {
    if (!itemsTbody) return;
    itemsTbody.querySelectorAll('input').forEach(inp => {
      // field wajib: material_name, unit, ordered_quantity
      if (!inp.name) return;
      if (
        inp.name.includes('[material_name]') ||
        inp.name.includes('[unit]') ||
        inp.name.includes('[ordered_quantity]')
      ) {
        inp.required = enabled;
      }
    });
  }

  function setStylesRequired(enabled) {
    if (!stylesTbody) return;
    stylesTbody.querySelectorAll('input').forEach(inp => {
      if (!inp.name) return;
      if (inp.name.includes('[style_name]') || inp.name.includes('[style_quantity]')) {
        inp.required = enabled;
      }
    });
  }

  function applyStockSourceUI() {
    const isFullFob = (stockSourceEl && stockSourceEl.value === 'fob_full');

    if (itemsCardEl) itemsCardEl.style.display = isFullFob ? 'none' : '';
    if (targetDateCol) targetDateCol.style.display = isFullFob ? 'none' : '';
    if (addItemBtn) addItemBtn.disabled = isFullFob;

    // Browser validation: jangan blok submit saat FULL FOB
    setItemsRequired(!isFullFob);
    setStylesRequired(isFullFob);
  }

  if (stockSourceEl) {
    stockSourceEl.addEventListener('change', applyStockSourceUI);
  }
  applyStockSourceUI();
</script>
@endsection
