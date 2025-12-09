@extends('layouts.master', ['title' => 'Buyer FOB'])

@section('content')
<div class="container">

  @php
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Buyer[] $buyers */
    $isEdit = isset($buyer) && $buyer instanceof \App\Models\Buyer;
  @endphp

  <div class="row">
    {{-- DAFTAR BUYER --}}
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h4 class="mb-0">DAFTAR BUYER FOB</h4>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th style="width:60px;" class="text-center">#</th>
                  <th>NAMA BUYER</th>
                  <th style="width:140px;">TELP</th>
                  <th>ALAMAT</th>
                  <th style="width:120px;" class="text-center">AKSI</th>
                </tr>
              </thead>
              <tbody>
                @forelse($buyers as $i => $row)
                  <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->phone ?: '-' }}</td>
                    <td>{{ $row->address ?: '-' }}</td>
                    <td class="text-center">
                      <a href="{{ route('admin.buyers.edit', $row) }}"
                         class="btn btn-sm btn-primary">
                        <i class="fas fa-edit"></i>
                      </a>
                      <form action="{{ route('admin.buyers.destroy', $row) }}"
                            method="POST"
                            class="d-inline"
                            onsubmit="return confirm('Yakin hapus buyer ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger">
                          <i class="fas fa-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-3">
                      Belum ada data buyer FOB.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- FORM TAMBAH / EDIT BUYER --}}
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h4 class="mb-0">
            {{ $isEdit ? 'EDIT BUYER FOB' : 'TAMBAH BUYER FOB' }}
          </h4>
        </div>
        <div class="card-body">
          <form
            method="POST"
            action="{{ $isEdit
              ? route('admin.buyers.update', $buyer)
              : route('admin.buyers.store') }}"
          >
            @csrf
            @if($isEdit)
              @method('PUT')
            @endif

            <div class="mb-3">
              <label for="buyer-name" class="form-label">Nama Buyer</label>
              <input type="text"
                     id="buyer-name"
                     name="name"
                     class="form-control @error('name') is-invalid @enderror"
                     value="{{ old('name', $isEdit ? $buyer->name : '') }}"
                     required>
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="buyer-phone" class="form-label">Telp Buyer</label>
              <input type="text"
                     id="buyer-phone"
                     name="phone"
                     class="form-control @error('phone') is-invalid @enderror"
                     value="{{ old('phone', $isEdit ? $buyer->phone : '') }}">
              @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="buyer-address" class="form-label">Alamat Buyer</label>
              <input type="text"
                     id="buyer-address"
                     name="address"
                     class="form-control @error('address') is-invalid @enderror"
                     value="{{ old('address', $isEdit ? $buyer->address : '') }}">
              @error('address')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i>
              {{ $isEdit ? 'Update' : 'Simpan' }}
            </button>

            @if($isEdit)
              <a href="{{ route('admin.buyers.index') }}"
                 class="btn btn-secondary ms-2">
                Batal
              </a>
            @endif
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
