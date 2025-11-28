@extends('layouts.master', ['title' => 'Riwayat Stok'])

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
    <h4 class="mb-0">Riwayat Stok — {{ $stock->material_name }}</h4>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('admin.stock.index', ['group' => 'flat']) }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali ke Stok
      </a>
      <a href="{{ route('admin.stock.edit', $stock) }}" class="btn btn-outline-primary">
        <i class="fas fa-edit"></i> Edit Stok
      </a>
      @if($poId && $poNo)
        <a href="{{ route('admin.purchase-orders.show', $poId) }}" class="btn btn-outline-secondary" target="_blank">
          <i class="fas fa-file-alt"></i> Lihat PO {{ $poNo }}
        </a>
      @endif
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header bg-light">
      <strong>Info Stok</strong>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="text-muted small mb-1">Material</div>
          <div class="fw-semibold">
            {{ $stock->material_name }}
            @if($stock->material_code)
              <span class="text-muted">[{{ $stock->material_code }}]</span>
            @endif
          </div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small mb-1">Supplier</div>
          <div>{{ optional($stock->supplier)->name ?? '—' }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small mb-1">NO PO</div>
          <div>
            @if($poId && $poNo)
              <a href="{{ route('admin.purchase-orders.show', $poId) }}" target="_blank">{{ $poNo }}</a>
            @else
              —
            @endif
          </div>
        </div>
      </div>

      <div class="row g-3 mt-2">
        <div class="col-md-4">
          <div class="text-muted small mb-1">Qty Saat Ini</div>
          <div><strong>{{ qty_fmt($stock->quantity) }}</strong> {{ $stock->unit ?? '' }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small mb-1">Unit</div>
          <div>{{ $stock->unit ?? '—' }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small mb-1">Terakhir Diubah</div>
          <div>{{ optional($stock->updated_at)->format('d-m-Y H:i') ?? '—' }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
      <strong>Riwayat Perubahan Stok</strong>
      <span class="small text-muted">Total {{ $histories->total() }} log</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:60px;">No</th>
              <th style="width:160px;">Tanggal</th>
              <th style="width:120px;">User</th>
              <th style="width:140px;">Tipe</th>
              <th class="text-end" style="width:120px;">Qty Lama</th>
              <th class="text-end" style="width:120px;">Qty Baru</th>
              <th class="text-end" style="width:120px;">Selisih</th>
              <th>Alasan</th>
            </tr>
          </thead>
          <tbody>
            @forelse($histories as $i => $h)
              @php
                $diff = (float) $h->diff_quantity;
                $diffClass = $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted');
              @endphp
              <tr>
                <td>{{ $histories->firstItem() + $i }}</td>
                <td>{{ optional($h->created_at)->format('d-m-Y H:i') }}</td>
                <td>{{ optional($h->user)->name ?? 'System' }}</td>
                <td>
                  @if($h->type === 'manual_edit')
                    <span class="badge bg-info text-dark">Manual Edit</span>
                  @elseif($h->type === 'manual_delete')
                    <span class="badge bg-danger">Manual Delete</span>
                  @else
                    <span class="badge bg-secondary">{{ $h->type }}</span>
                  @endif
                </td>
                <td class="text-end">{{ qty_fmt($h->old_quantity) }}</td>
                <td class="text-end">{{ qty_fmt($h->new_quantity) }}</td>
                <td class="text-end {{ $diffClass }}">
                  {{ $diff > 0 ? '+' : '' }}{{ qty_fmt($diff) }}
                </td>
                <td>{{ $h->reason ?? '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center text-muted py-3">
                  Belum ada riwayat perubahan stok.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer">
      {{ $histories->links() }}
    </div>
  </div>

</div>
@endsection
