@extends('layouts.master', ['title' => 'Edit Buyer'])

@section('content')
<div class="container">

  <h4 class="mb-3">Edit Buyer — {{ $buyer->name }}</h4>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card">
    <div class="card-body">
      <form method="POST" action="{{ route('admin.buyers.update', $buyer) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
          <label class="form-label">Nama Buyer <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="{{ old('name', $buyer->name) }}" required>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Kode</label>
            <input type="text" name="code" class="form-control" value="{{ old('code', $buyer->code) }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Nama Kontak</label>
            <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $buyer->contact_name) }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">No. Telp</label>
            <input type="text" name="phone" class="form-control" value="{{ old('phone', $buyer->phone) }}">
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $buyer->email) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Alamat</label>
            <textarea name="address" rows="2" class="form-control">{{ old('address', $buyer->address) }}</textarea>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Catatan</label>
          <textarea name="notes" rows="2" class="form-control">{{ old('notes', $buyer->notes) }}</textarea>
        </div>

        <div class="mt-4 d-flex justify-content-between">
          <a href="{{ route('admin.buyers.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection
