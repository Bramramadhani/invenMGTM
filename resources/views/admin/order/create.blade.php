@extends('layouts.master', ['title' => 'Buat Permintaan Barang'])

@section('content')
<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Buat Permintaan Barang</h4>
    <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>
  </div>

  <form method="post" action="{{ route('admin.orders.store') }}" id="orderForm" novalidate>
    @csrf

    {{-- === SUMBER STOK, PO & STYLE === --}}
    <div class="card mb-3">
      <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
        <span>Sumber PO & Style</span>
        <div class="d-flex align-items-center gap-2">
          <span class="small text-muted">Sumber Stok:</span>
          @php
            $oldSourceType = old('source_type', 'po');
            $oldSourceType = $oldSourceType === 'fob' ? 'fob' : 'po';
          @endphp
          <select name="source_type" id="sourceType" class="form-select form-select-sm" style="width:auto;">
            <option value="po"  {{ $oldSourceType === 'po'  ? 'selected' : '' }}>Stok PO / Buyer</option>
            <option value="fob" {{ $oldSourceType === 'fob' ? 'selected' : '' }}>Stok FOB </option>
          </select>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4" id="colSupplier">
            <label class="form-label">Buyer <span class="text-danger">*</span></label>
            {{-- hanya untuk AJAX, tidak dikirim ke server --}}
            <select id="supplierSelect" class="form-select" required>
              <option value="">— Pilih Buyer —</option>
              @foreach ($suppliers as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-4" id="colPO">
            <label class="form-label">No. PO <span class="text-danger">*</span></label>
            {{-- hanya untuk AJAX, tidak dikirim ke server --}}
            <select id="poSelect" class="form-select" disabled required>
              <option value="">— Pilih PO —</option>
            </select>
          </div>

          <div class="col-md-4" id="colStyle">
            <label class="form-label">Style <span class="text-danger">*</span></label>
            {{-- ini yang akan dikirim ke server --}}
            <select id="styleSelect" name="purchase_order_style_id" class="form-select" disabled required>
              <option value="">— Pilih Style —</option>
            </select>
            <div class="form-text" id="styleHelp">
              Pilih PO terlebih dahulu untuk melihat daftar style.
            </div>
          </div>
        </div>

        {{-- Buyer hanya untuk mode FOB --}}
        <div class="row g-3 mt-3" id="rowBuyer" style="display:none;">
          <div class="col-md-4">
            <label class="form-label">
              Buyer (FOB)
              <span class="text-danger" id="buyerRequiredMark" style="display:none;">*</span>
            </label>
            <select id="buyerSelect" name="buyer_id" class="form-select">
              <option value="">— Pilih Buyer —</option>
              @foreach ($buyers as $b)
                <option value="{{ $b->id }}" {{ (string)old('buyer_id') === (string)$b->id ? 'selected' : '' }}>
                  {{ $b->name }}
                </option>
              @endforeach
            </select>
            <div class="form-text" id="buyerHelp">
              Pilih <strong>Buyer</strong> jika sumber stok adalah FOB.
            </div>
          </div>
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
            <label class="form-label">Checker Produksi <span class="text-danger">*</span></label>
            <input type="text" name="production_name" class="form-control"
                   value="{{ old('production_name') }}" placeholder="Masukkan nama produksi" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Leader Produksi <span class="text-danger">*</span></label>
            <input type="text" name="production_leader_name" class="form-control"
                   value="{{ old('production_leader_name') }}" placeholder="Masukkan nama leader produksi" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Checker Gudang <span class="text-danger">*</span></label>
            <input type="text" name="warehouse_admin_name" class="form-control"
                   value="{{ old('warehouse_admin_name') }}" placeholder="Masukkan nama admin gudang" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Leader Gudang <span class="text-danger">*</span></label>
            <input type="text" name="warehouse_leader_name" class="form-control"
                   value="{{ old('warehouse_leader_name') }}" placeholder="Masukkan nama leader gudang" required>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-3">
            <label class="form-label">Supply Chain Head</label>
            <input type="text" name="supply_chain_head_name" class="form-control"
                   value="{{ old('supply_chain_head_name') }}" placeholder="Masukkan nama Supply Chain Head">
          </div>
        </div>
      </div>
    </div>

    {{-- === TABEL STOK === --}}
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong id="stocksTitle">Stok Tersedia (per-PO)</strong>
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
                <th style="width:170px">Buyer / FOB</th>
                <th style="width:170px" class="col-vendor" id="thVendor">Vendor / Toko</th>
                <th style="width:160px" id="thPoNumber">No. PO (Stok)</th>
                <th style="width:160px" class="text-end">Tersedia</th>
                <th style="width:180px" class="text-end">Qty Diminta</th>
                <th style="width:260px">Catatan Item</th>
              </tr>
            </thead>
            <tbody>
              {{-- baris diisi via JS --}}
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
            <i class="fas fa-save"></i> Simpan Permintaan
          </button>
          <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
        </div>
      </div>
    </div>
  </form>
</div>

{{-- URL template untuk AJAX --}}
<input type="hidden" id="urlSupplierPOs" value="{{ route('admin.orders.supplier-pos', ['supplier' => '__ID__']) }}">
<input type="hidden" id="urlPOStocks"   value="{{ route('admin.orders.po-stocks', ['purchaseOrder' => '__ID__']) }}">
<input type="hidden" id="urlPOStyles"   value="{{ route('admin.orders.po-styles', ['purchaseOrder' => '__ID__']) }}">
<input type="hidden" id="urlBuyerStocks" value="{{ route('admin.orders.buyer-stocks', ['buyer' => '__ID__']) }}">

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
  const sourceTypeSelect  = document.getElementById('sourceType');
  const supplierSelect    = document.getElementById('supplierSelect');
  const poSelect          = document.getElementById('poSelect');
  const styleSelect       = document.getElementById('styleSelect');
  const styleHelp         = document.getElementById('styleHelp');
  const colSupplier       = document.getElementById('colSupplier');
  const colPO             = document.getElementById('colPO');
  const colStyle          = document.getElementById('colStyle');
  const rowBuyer          = document.getElementById('rowBuyer');
  const buyerSelect       = document.getElementById('buyerSelect');
  const buyerRequiredMark = document.getElementById('buyerRequiredMark');
  const buyerHelp         = document.getElementById('buyerHelp');
  const stocksTitle       = document.getElementById('stocksTitle');
  const thPoNumber        = document.getElementById('thPoNumber');
  const stocksTable       = document.getElementById('stocksTable');

  const tblBody           = document.querySelector('#stocksTable tbody');
  const chkHeader         = document.getElementById('chkHeader');
  const btnSelectAll      = document.getElementById('btnSelectAll');
  const btnClear          = document.getElementById('btnClear');
  const form              = document.getElementById('orderForm');

  const urlSupplierPOsTpl = document.getElementById('urlSupplierPOs').value;
  const urlPOStocksTpl    = document.getElementById('urlPOStocks').value;
  const urlPOStylesTpl    = document.getElementById('urlPOStyles').value;
  const urlBuyerStocksTpl = document.getElementById('urlBuyerStocks').value;

  const selCountEl        = document.getElementById('selCount');
  const selTotalEl        = document.getElementById('selTotal');

  let targetPoNumber = '';

  function getSourceType() {
    return sourceTypeSelect ? (sourceTypeSelect.value || 'po') : 'po';
  }

  function applySourceTypeUI() {
    const type = getSourceType();

    const isFob = (type === 'fob');
    const isFobFull = (type === 'fob_full'); // legacy (opsi ini sudah dihapus dari UI)

    // Buyer (FOB)
    if (rowBuyer) rowBuyer.style.display = isFob ? '' : 'none';
    if (buyerRequiredMark) buyerRequiredMark.style.display = isFob ? '' : 'none';
    if (buyerHelp) {
      buyerHelp.textContent = isFob
        ? 'Pilih Buyer yang stok-nya akan dipakai (FOB).'
        : 'Pilih Buyer jika sumber stok adalah FOB.';
    }
    if (!isFob && buyerSelect) buyerSelect.value = '';

    // Vendor/Toko hanya untuk mode FOB
    if (stocksTable) {
      stocksTable.classList.toggle('mode-fob', isFob);
      stocksTable.classList.toggle('mode-po', !isFob);
    }

    // Label kolom PO: untuk FOB tampilkan target PO (bukan PO stok)
    if (thPoNumber) {
      thPoNumber.textContent = isFob ? 'No. PO (Target)' : 'No. PO (Stok)';
    }

    // PO & Style controls: untuk FULL FOB disembunyikan & tidak wajib
    if (colSupplier) colSupplier.style.display = isFobFull ? 'none' : '';
    if (colPO) colPO.style.display = isFobFull ? 'none' : '';
    if (colStyle) colStyle.style.display = isFobFull ? 'none' : '';

    if (supplierSelect) {
      supplierSelect.disabled = isFobFull;
      supplierSelect.required = !isFobFull;
      if (isFobFull) supplierSelect.value = '';
    }
    if (poSelect) {
      if (isFobFull) {
        poSelect.disabled = true;
        poSelect.required = false;
        poSelect.innerHTML = '<option value="">— Pilih PO —</option>';
      } else {
        poSelect.required = true;
      }
    }
    if (styleSelect) {
      if (isFobFull) {
        styleSelect.value = '';
        styleSelect.disabled = true;
        styleSelect.required = false;
      }
    }
    if (styleHelp) {
      styleHelp.style.display = isFobFull ? 'none' : '';
    }

    if (stocksTitle) {
      stocksTitle.textContent = isFob
        ? 'Stok Tersedia (FOB / Buyer)'
        : 'Stok Tersedia (per-PO)';
    }
  }

  function resetTable() {
    tblBody.innerHTML = '';
    chkHeader.checked = false;
    updateSummary();
  }

  function resetStyles() {
    if (!styleSelect) return;
    styleSelect.innerHTML = '<option value="">— Pilih Style —</option>';
    styleSelect.disabled = true;
    styleSelect.required = false;
    if (styleHelp) {
      styleHelp.textContent = 'Pilih PO terlebih dahulu untuk melihat daftar style.';
    }
  }

  // Load PO list saat supplier berubah
  supplierSelect.addEventListener('change', async function(){
    const supplierId = this.value;
    poSelect.innerHTML = '<option value="">— Pilih PO —</option>';
    poSelect.disabled = true;
    resetTable();
    resetStyles();

    if (!supplierId) return;

    const type = getSourceType();
    let url = urlSupplierPOsTpl.replace('__ID__', encodeURIComponent(supplierId));

    if (type === 'fob') {
      // Untuk FOB, tambahkan mode=fob supaya server mengembalikan semua PO supplier tsb (target Style)
      url += (url.indexOf('?') === -1 ? '?' : '&') + 'mode=fob';
    }

    const res = await fetch(url);
    if (!res.ok) return alert('Gagal memuat daftar PO');
    const data = await res.json();

    (data.pos || []).forEach(po => {
      const opt = document.createElement('option');
      opt.value = po.id;
      opt.textContent = po.po_number;
      poSelect.appendChild(opt);
    });
    poSelect.disabled = false;
  });

  // Load stok & style saat PO berubah
  poSelect.addEventListener('change', async function(){
    const poId = this.value;
    targetPoNumber = this.options?.[this.selectedIndex]?.textContent?.trim() || '';
    resetTable();
    resetStyles();
    if (!poId) return;

    const type = getSourceType();

    // Mode PO: stok diambil dari stok per-PO
    if (type === 'po') {
      await loadPoStocks(poId);
    }

    // Kedua mode: Style tetap mengikuti PO sebagai target produksi
    await loadPoStyles(poId);
  });

  // Load stok FOB saat Buyer berubah (hanya jika mode FOB)
  if (buyerSelect) {
    buyerSelect.addEventListener('change', async function(){
      resetTable();
      const buyerId = this.value;
      const type = getSourceType();
      if (type !== 'fob' || !buyerId) return;
      await loadBuyerStocks(buyerId);
    });
  }

  async function loadPoStocks(poId) {
    const url = urlPOStocksTpl.replace('__ID__', encodeURIComponent(poId));
    const res = await fetch(url);
    if (!res.ok) {
      alert('Gagal memuat stok PO');
      return;
    }
    const data = await res.json();
    renderStockRows(data.items || []);
  }

  async function loadBuyerStocks(buyerId) {
    const url = urlBuyerStocksTpl.replace('__ID__', encodeURIComponent(buyerId));
    const res = await fetch(url);
    if (!res.ok) {
      alert('Gagal memuat stok FOB Buyer');
      return;
    }
    const data = await res.json();
    // Sumber stok FOB tidak punya PO di stock-nya; tampilkan PO TARGET (yang dipilih di atas)
    (data.items || []).forEach(it => {
      it.po_number = targetPoNumber;
    });
    renderStockRows(data.items || []);
  }

  async function loadPoStyles(poId) {
    const url = urlPOStylesTpl.replace('__ID__', encodeURIComponent(poId));
    const res = await fetch(url);
    if (!res.ok) {
      alert('Gagal memuat daftar style untuk PO ini');
      return;
    }
    const data   = await res.json();
    const styles = data.styles || [];

    if (!styles.length) {
      if (styleHelp) {
        styleHelp.textContent = 'PO ini belum memiliki data style. Tambahkan style di menu Purchase Order sebelum membuat permintaan.';
      }
      styleSelect.disabled = true;
      styleSelect.required = false;
      return;
    }

    styles.forEach(st => {
      const opt = document.createElement('option');
      opt.value = st.id;
      opt.textContent = st.name || ('Style #' + st.id);
      styleSelect.appendChild(opt);
    });

    styleSelect.disabled = false;
    styleSelect.required = true;
    if (styleHelp) {
      styleHelp.textContent = 'Pilih style tas yang sedang jalan produksi.';
    }
  }

  function renderStockRows(items) {
    resetTable();

    const type = getSourceType();

    items.forEach((row, idx) => {
      const tr = document.createElement('tr');
      const poDisplay = type === 'fob'
        ? (targetPoNumber || row.po_number || '—')
        : (row.po_number || '—');
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
          if (!qty.value || Number(qty.value) <= 0) qty.value = cleanDecimal(row.available);
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
    const type = getSourceType();

    if (styleSelect && styleSelect.required && !styleSelect.value) {
      e.preventDefault();
      alert('Silakan pilih Style PO terlebih dahulu.');
      styleSelect.focus();
      return;
    }

    if (type === 'fob') {
      if (!buyerSelect || !buyerSelect.value) {
        e.preventDefault();
        alert('Silakan pilih Buyer untuk permintaan dari stok FOB.');
        if (buyerSelect) buyerSelect.focus();
        return;
      }
    }

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

  // Saat pertama kali load
  applySourceTypeUI();

  if (sourceTypeSelect) {
    sourceTypeSelect.addEventListener('change', () => {
      // Ganti mode → reset pilihan
      supplierSelect.value = '';
      poSelect.innerHTML   = '<option value="">— Pilih PO —</option>';
      poSelect.disabled    = true;
      resetStyles();
      resetTable();
      applySourceTypeUI();
    });
  }
})();
</script>
@endpush
@endsection
