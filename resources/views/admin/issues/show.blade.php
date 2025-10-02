@extends('layouts.master', ['title' => 'Detail Pengeluaran'])

@section('content')
<div class="container">
  {{-- Flash messages --}}
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Detail Issue — {{ $issue->issue_number }}</h4>

    @if($issue->status === 'draft')
      <form method="post" action="{{ route('admin.issues.post', $issue) }}"
            onsubmit="return confirm('Posting pengeluaran ini? Stok akan dikurangi.');">
        @csrf
        <button class="btn btn-danger">
          <i class="fas fa-paper-plane"></i> Posting
        </button>
      </form>
    @else
      <span class="badge bg-success">Sudah Posted</span>
    @endif
  </div>

  {{-- Info header --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="text-muted small">Nomor</div>
          <div class="fw-semibold">{{ $issue->issue_number }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small">Tanggal</div>
          <div class="fw-semibold">
            {{ \Carbon\Carbon::parse($issue->issue_date)->format('d-m-Y') }}
          </div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small">Status</div>
          <div class="fw-semibold">
            @if($issue->status === 'draft')
              <span class="badge bg-warning text-dark">DRAFT</span>
            @elseif($issue->status === 'posted')
              <span class="badge bg-success">POSTED</span>
            @else
              {{ strtoupper($issue->status) }}
            @endif
          </div>
        </div>
      </div>

      @if($issue->notes)
        <div class="mt-3">
          <div class="text-muted small">Catatan</div>
          <div>{{ $issue->notes }}</div>
        </div>
      @endif
    </div>
  </div>

  {{-- Tabel item --}}
  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:60px">#</th>
          <th>Material</th>
          <th>Supplier</th>
          <th style="width:120px">Unit</th>
          <th class="text-end" style="width:160px">Qty Keluar</th>
          <th>Catatan</th>
        </tr>
      </thead>
      <tbody>
        @forelse($issue->items as $row)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $row->material_name }}</td>
            <td>{{ optional($row->supplier)->name ?? '-' }}</td>
            <td>{{ $row->unit }}</td>
            <td class="text-end">{{ number_format((float)$row->quantity, 4) }}</td>
            <td>{{ $row->notes }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center text-muted">Tidak ada item.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary mt-3">
    <i class="fas fa-arrow-left"></i> Kembali
  </a>
</div>
@endsection
