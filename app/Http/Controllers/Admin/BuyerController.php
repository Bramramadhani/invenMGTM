<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use Illuminate\Http\Request;

class BuyerController extends Controller
{
    /**
     * Halaman utama Buyer FOB:
     * - Kiri: daftar buyer
     * - Kanan: form tambah / edit buyer
     */
    public function index()
    {
        $buyers = Buyer::orderBy('name')->get();

        // mode "tambah" (form kosong)
        return view('admin.buyers.index', [
            'buyers' => $buyers,
            'buyer'  => null,
        ]);
    }

    /**
     * Tidak dipakai (form ada di index), redirect saja ke index.
     */
    public function create()
    {
        return redirect()->route('admin.buyers.index');
    }

    /**
     * Simpan buyer baru (nama, telp, alamat).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:191'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ], [], [
            'name'    => 'Nama buyer',
            'phone'   => 'Telp buyer',
            'address' => 'Alamat buyer',
        ]);

        Buyer::create([
            'name'    => $data['name'],
            'phone'   => $data['phone']   ?? null,
            'address' => $data['address'] ?? null,
            // kolom lain (code, contact_name, email, notes) biarkan null
        ]);

        return redirect()
            ->route('admin.buyers.index')
            ->with('success', 'Buyer FOB berhasil ditambahkan.');
    }

    /**
     * Mode edit: tetap pakai view index,
     * tapi form di kanan terisi data buyer yang dipilih.
     */
    public function edit(Buyer $buyer)
    {
        $buyers = Buyer::orderBy('name')->get();

        return view('admin.buyers.index', [
            'buyers' => $buyers,
            'buyer'  => $buyer,
        ]);
    }

    /**
     * Update buyer (nama, telp, alamat).
     */
    public function update(Request $request, Buyer $buyer)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:191'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ], [], [
            'name'    => 'Nama buyer',
            'phone'   => 'Telp buyer',
            'address' => 'Alamat buyer',
        ]);

        $buyer->update([
            'name'    => $data['name'],
            'phone'   => $data['phone']   ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return redirect()
            ->route('admin.buyers.index')
            ->with('success', 'Buyer FOB berhasil diperbarui.');
    }

    /**
     * Hapus buyer (asal tidak dipakai stok FOB).
     */
    public function destroy(Buyer $buyer)
    {
        if ($buyer->stocks()->exists()) {
            return back()->with('warning', 'Buyer masih memiliki stok FOB, tidak bisa dihapus.');
        }

        $buyer->delete();

        return redirect()
            ->route('admin.buyers.index')
            ->with('success', 'Buyer FOB berhasil dihapus.');
    }
}
