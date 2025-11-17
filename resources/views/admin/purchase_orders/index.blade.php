@extends('layouts.master', ['title' => 'Purchase Orders'])

@section('content')
<div class="container">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Daftar Purchase Order</h4>
    <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary">
      <i class="fas fa-plus"></i> Buat PO
    </a>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:80px;">#</th>
              <th style="min-width:140px;">No. PO</th>
              <th>Supplier</th>
              {{-- Kolom target selesai: center + nowrap supaya rapi --}}
              <th class="text-center" style="min-width:180px;">Target Selesai</th>
              <th class="text-center" style="width:120px;">Item</th>
              <th class="text-center" style="width:160px;">Status</th>
              <th class="text-end" style="min-width:260px;">Aksi</th>
            </tr>
          </thead>

          <tbody>
          @forelse ($purchaseOrders as $po)
            <tr>
              <td>
                {{ $loop->iteration + ($purchaseOrders->currentPage() - 1) * $purchaseOrders->perPage() }}
              </td>

              <td class="fw-semibold">
                {{ $po->po_number ?? '—' }}
              </td>

              <td>
                {{ $po->supplier?->name ?? '—' }}
              </td>

              {{-- Target selesai = estimasi selesai barang jadi per-PO --}}
              <td class="text-center text-nowrap">
                {{ optional($po->target_completion_date)->format('d-m-Y') ?? '—' }}
              </td>

              <td class="text-center">
                {{ $po->items?->count() ?? 0 }}
              </td>

              <td class="text-center">
                @if($po->is_completed)
                  <span class="badge bg-success">SELESAI</span>
                @else
                  <span class="badge bg-warning text-dark">BELUM</span>
                @endif
              </td>

              <td class="text-end align-middle">
                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi Purchase Order">
                  {{-- Detail --}}
                  <a href="{{ route('admin.purchase-orders.show', $po->id) }}"
                     class="btn btn-outline-secondary">
                    <i class="fas fa-eye"></i> Detail
                  </a>

                  {{-- Edit --}}
                  <a href="{{ route('admin.purchase-orders.edit', $po->id) }}"
                     class="btn btn-outline-primary">
                    <i class="fas fa-edit"></i> Edit
                  </a>

                  {{-- Terima Parsial (buat Receipt Draft) --}}
                  <a href="{{ route('admin.receipts.create', $po) }}"
                     class="btn btn-outline-success">
                    <i class="fas fa-inbox"></i> Terima
                  </a>

                  {{-- Hapus --}}
                  <form action="{{ route('admin.purchase-orders.destroy', $po->id) }}"
                        method="post"
                        class="d-inline m-0"
                        onsubmit="return confirm('Hapus PO ini? Tindakan tidak dapat dibatalkan.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                      <i class="fas fa-trash"></i> Hapus
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">
                Belum ada Purchase Order.
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if ($purchaseOrders->hasPages())
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="small text-muted">
          Menampilkan {{ $purchaseOrders->firstItem() }}–{{ $purchaseOrders->lastItem() }}
          dari {{ $purchaseOrders->total() }} PO
        </div>
        {{ $purchaseOrders->onEachSide(1)->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
