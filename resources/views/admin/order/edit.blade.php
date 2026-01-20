@extends('layouts.master', ['title' => 'Edit Permintaan Barang'])

@section('content')
<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Edit Permintaan Barang</h4>
    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Kembali ke Detail
    </a>
  </div>

  <form method="post" action="{{ route('admin.orders.update', $order) }}" id="orderForm" novalidate>
    @csrf
    @method('PUT')

    {{-- Hidden info source_type & buyer untuk JS --}}
    <input type="hidden" id="sourceType" value="{{ $order->source_type ?? 'po' }}">
    @if(($order->source_type ?? 'po') === 'fob' && $order->buyer)
      <input type="hidden" id="urlBuyerStocks" value="{{ route('admin.orders.buyer-stocks', ['buyer' => $order->buyer->id]) }}">
    @endif

    {{-- === PO & STYLE (readonly) + Sumber Stok === --}}
    <div class="card mb-3">
      <div class="card-header bg-light fw-semibold">
        Sumber PO & Style (terkunci)
      </div>
      <div class="card-body">
        @if(!empty($po) && !empty($style))
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Buyer</label>
              <input class="form-control" value="{{ optional($po->supplier)->name }}" disabled>
            </div>
            <div class="col-md-4">
              <label class="form-label">No. PO</label>
              <input class="form-control" value="{{ $po->po_number }}" disabled>
            </div>
            <div class="col-md-4">
              <label class="form-label">Style</label>
              <input class="form-control" value="{{ $style->style_name ?? $style->name ?? ('Style #'.$style->id) }}" disabled>
            </div>
          </div>
        @else
          <div class="alert alert-warning mb-0">
            Data Style/PO untuk permintaan ini tidak ditemukan.
          </div>
        @endif

        <div class="row g-3 mt-3">
          <div class="col-md-4">
            <label class="form-label">Sumber Stok</label>
            <input class="form-control"
                   value="{{ ($order->source_type ?? 'po') === 'fob' ? 'Stok FOB (Buyer)' : 'Stok PO / Buyer' }}"
                   disabled>
          </div>
          @if(($order->source_type ?? 'po') === 'fob')
            <div class="col-md-4">
              <label class="form-label">Buyer (FOB)</label>
              <input class="form-control" value="{{ optional($order->buyer)->name }}" disabled>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- === INFORMASI PRODUKSI DAN GUDANG === --}}
    <div class="card mb-3">
      <div class="card-header bg-light fw-semibold">
        Informasi Produksi & Gudang
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Peminta (Produksi) <span class="text-danger">*</span></label>
            <input type="text" name="production_name" class="form-control"
                   value="{{ old('production_name', $order->production_name) }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Leader Produksi <span class="text-danger">*</span></label>
            <input type="text" name="production_leader_name" class="form-control"
                   value="{{ old('production_leader_name', $order->production_leader_name) }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Checker Gudang <span class="text-danger">*</span></label>
            <input type="text" name="warehouse_admin_name" class="form-control"
                   value="{{ old('warehouse_admin_name', $order->warehouse_admin_name) }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Leader Gudang <span class="text-danger">*</span></label>
            <input type="text" name="warehouse_leader_name" class="form-control"
                   value="{{ old('warehouse_leader_name', $order->warehouse_leader_name) }}" required>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-3">
            <label class="form-label">Supply Chain Head</label>
            <input type="text" name="supply_chain_head_name" class="form-control"
                   value="{{ old('supply_chain_head_name', $order->supply_chain_head_name) }}">
          </div>
        </div>
      </div>
    </div>

    {{-- === TABEL STOK === --}}
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong id="stocksTitle">
          @if(($order->source_type ?? 'po') === 'fob')
            Stok Tersedia (FOB / Buyer)
          @else
            Stok Tersedia (PO: {{ $po->po_number ?? '—' }})
          @endif
        </strong>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClear">
            <i class="fas fa-eraser"></i> Bersihkan
          </button>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btnSelectAll">
            <i class="fas fa-check-double"></i> Pilih Semua
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0" id="stocksTable">
            <thead class="table-light">
              <tr>
                <th style="width:40px" class="text-center">
                  <input type="checkbox" id="chkHeader" />
                </th>
                <th style="width:160px">Kode</th>
                <th>Material</th>
                <th style="width:100px">Unit</th>
                <th style="width:170px">Supplier / Buyer</th>
                <th style="width:170px" id="thVendor">Vendor / Toko</th>
                <th style="width:160px" id="thPoNumber">No. PO (Stok)</th>
                <th style="width:160px" class="text-end">Tersedia</th>
                <th style="width:180px" class="text-end">Qty Diminta</th>
                <th style="width:260px">Catatan Item</th>
              </tr>
            </thead>
            <tbody>
              {{-- diisi via JS --}}
            </tbody>
          </table>
        </div>
      </div>

      {{-- Ringkasan pilihan --}}
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted small">
          Dipilih: <span id="selCount">0</span> item • Total Qty: <span id="selTotal">0</span>
        </div>
        <div>
          <button class="btn btn-primary" id="btnSubmit">
            <i class="fas fa-save"></i> Simpan Perubahan
          </button>
          <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline-secondary ms-2">Batal</a>
        </div>
      </div>
    </div>

    {{-- Hidden URL for AJAX --}}
    @if(!empty($po))
      <input type="hidden" id="urlPOStocks" value="{{ route('admin.orders.po-stocks', ['purchaseOrder' => $po->id]) }}">
    @endif

  </form>
</div>

@push('css')
<style>
  /* Vendor/Toko hanya relevan untuk stok FOB */
  #stocksTable.mode-po th:nth-child(6),
  #stocksTable.mode-po td:nth-child(6) { display: none; }
</style>
@endpush

@push('js')
<script>
(function(){
  const stocksTable    = document.getElementById('stocksTable');
  const thPoNumber     = document.getElementById('thPoNumber');
  const tblBody        = document.querySelector('#stocksTable tbody');
  const chkHeader      = document.getElementById('chkHeader');
  const btnSelectAll   = document.getElementById('btnSelectAll');
  const btnClear       = document.getElementById('btnClear');
  const form           = document.getElementById('orderForm');

  const urlPOStocksEl  = document.getElementById('urlPOStocks');
  const urlPOStocks    = urlPOStocksEl ? urlPOStocksEl.value : null;
  const sourceTypeEl   = document.getElementById('sourceType');
  const sourceType     = sourceTypeEl ? (sourceTypeEl.value || 'po') : 'po';
  const urlBuyerStocksEl = document.getElementById('urlBuyerStocks');
  const urlBuyerStocks = urlBuyerStocksEl ? urlBuyerStocksEl.value : null;
  const targetPoNumber = {!! json_encode($po->po_number ?? '') !!};

  const selCountEl = document.getElementById('selCount');
  const selTotalEl = document.getElementById('selTotal');

  // map existing items: stock_id => {qty, notes}
  const existing = {
    @foreach($order->items as $it)
      @php
        $unitRaw = strtolower(trim((string) $it->unit));
        $qtyBase = in_array($unitRaw, ['lusin','lusinan','dozen','dz'], true)
          ? ((float) $it->quantity * 12)
          : (float) $it->quantity;
      @endphp
      {{ (int)$it->stock_id }}: {
        qty: {{ $qtyBase }},
        notes: {!! json_encode((string)($it->notes ?? '')) !!}
      },
    @endforeach
  };

  function resetTable() {
    tblBody.innerHTML = '';
    chkHeader.checked = false;
    updateSummary();
  }

  // load stocks sesuai source_type
  async function loadStocks(){
    resetTable();

    // Vendor/Toko hanya untuk mode FOB
    if (stocksTable) {
      stocksTable.classList.toggle('mode-fob', sourceType === 'fob');
      stocksTable.classList.toggle('mode-po', sourceType !== 'fob');
    }
    if (thPoNumber) {
      thPoNumber.textContent = sourceType === 'fob' ? 'No. PO (Target)' : 'No. PO (Stok)';
    }

    let res;
    if (sourceType === 'fob') {
      if (!urlBuyerStocks) {
        alert('URL stok FOB Buyer tidak tersedia.');
        return;
      }
      res = await fetch(urlBuyerStocks);
    } else {
      if (!urlPOStocks) {
        alert('URL stok PO tidak tersedia.');
        return;
      }
      res = await fetch(urlPOStocks);
    }

    if (!res.ok) return alert('Gagal memuat stok');
    const data  = await res.json();
    const items = data.items || [];

    // Untuk mode FOB: tampilkan PO TARGET di kolom "No. PO (Stok)"
    if (sourceType === 'fob' && targetPoNumber) {
      items.forEach(it => { it.po_number = targetPoNumber; });
    }

    items.forEach((row, idx) => {
      const tr = document.createElement('tr');
      const sourceLabel = row.source_label || row.supplier || row.buyer || '—';

      tr.innerHTML = `
        <td class="text-center">
          <input type="checkbox" class="chkRow">
          <input type="hidden" class="hidStockId">
        </td>
        <td>${row.material_code ? row.material_code : '—'}</td>
        <td class="fw-semibold">${row.material_name}</td>
        <td>${row.unit ?? ''}</td>
        <td class="text-start">${sourceLabel}</td>
        <td class="text-start">${row.vendor_name ? row.vendor_name : '—'}</td>
        <td class="text-center">${row.po_number ?? '—'}</td>
        <td class="text-end availCell">${formatNumber(row.available)}</td>
        <td>
          <input type="number" min="0" step="0.0001" class="form-control text-end qtyInput" placeholder="0" disabled data-avail="${row.available}">
        </td>
        <td>
          <input type="text" class="form-control notesInput" placeholder="Catatan item (opsional)" disabled>
        </td>
      `;

      const chk   = tr.querySelector('.chkRow');
      const hidId = tr.querySelector('.hidStockId');
      const qty   = tr.querySelector('.qtyInput');
      const note  = tr.querySelector('.notesInput');

      // preselect if exists
      const ex = existing[row.stock_id];
      if (ex) {
        chk.checked = true;
        qty.disabled = false;
        note.disabled = false;
        qty.required = true;

        hidId.name = `items[${idx}][stock_id]`;
        qty.name   = `items[${idx}][quantity]`;
        note.name  = `items[${idx}][notes]`;
        hidId.value = row.stock_id;

        qty.value  = cleanDecimal(ex.qty);
        note.value = ex.notes || '';
      }

      chk.addEventListener('change', () => {
        const sel = chk.checked;
        qty.disabled  = !sel;
        note.disabled = !sel;
        qty.required  = sel;

        if (sel) {
          hidId.name = `items[${idx}][stock_id]`;
          qty.name   = `items[${idx}][quantity]`;
          note.name  = `items[${idx}][notes]`;
          hidId.value = row.stock_id;

          if (!qty.value || Number(qty.value) <= 0) {
            qty.value = ex ? cleanDecimal(ex.qty) : cleanDecimal(row.available);
          }
        } else {
          hidId.name = '';
          qty.name   = '';
          note.name  = '';
          qty.value  = '';
          note.value = '';
        }
        updateSummary();
      });

      qty.addEventListener('input', () => {
        clampQty(qty);
        updateSummary();
      });

      tblBody.appendChild(tr);
    });

    updateSummary();
  }

  chkHeader.addEventListener('change', () => {
    tblBody.querySelectorAll('.chkRow').forEach(chk => {
      if (chk.checked !== chkHeader.checked) chk.click();
    });
    updateSummary();
  });

  btnSelectAll.addEventListener('click', () => {
    chkHeader.checked = true;
    chkHeader.dispatchEvent(new Event('change'));
  });

  btnClear.addEventListener('click', () => {
    chkHeader.checked = false;
    chkHeader.dispatchEvent(new Event('change'));
  });

  form.addEventListener('submit', (e) => {
    const picked = tblBody.querySelectorAll('.hidStockId[name^="items["]').length;
    if (!picked) {
      e.preventDefault();
      alert('Pilih minimal satu item stok.');
      return;
    }
    tblBody.querySelectorAll('.qtyInput[name^="items["]').forEach(q => clampQty(q));
  });

  function clampQty(input) {
    const avail = Number(input.dataset.avail || 0);
    let val = Number((input.value || '0').toString().replace(',', '.'));
    if (!isFinite(val)) val = 0;
    if (val < 0) val = 0;
    if (val > avail) val = avail;
    input.value = cleanDecimal(val);
  }

  function updateSummary() {
    const qtyInputs = tblBody.querySelectorAll('.qtyInput[name^="items["]');
    let count = 0, total = 0;
    qtyInputs.forEach(q => {
      const v = Number((q.value || '0').toString().replace(',', '.'));
      if (v > 0) {
        count++;
        total += v;
      }
    });
    selCountEl.textContent = count;
    selTotalEl.textContent = cleanDecimal(total);
  }

  function formatNumber(val) {
    if (val == null) return '0';
    const n = Number(val);
    return cleanDecimal(n);
  }
  function cleanDecimal(x) {
    let s = (Number(x)).toFixed(4);
    s = s.replace(/\.?0+$/, '');
    return s === '' ? '0' : s;
  }

  // init
  loadStocks();
})();
</script>
@endpush
@endsection
