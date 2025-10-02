@extends('layouts.master', ['title' => 'Manajemen Permission'])

@section('content')
<x-container>
    <x-card title="Data Permission">
        <a href="{{ route('admin.permission.create') }}" class="btn btn-primary mb-3">
            <i class="fas fa-plus"></i> Tambah Permission
        </a>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Permission</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permissions as $permission)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $permission->name }}</td>
                            <td>
                                <a href="{{ route('admin.permission.edit', $permission->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                <form action="{{ route('admin.permission.destroy', $permission->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $permissions->links() }}
        </div>
    </x-card>
</x-container>
@endsection
