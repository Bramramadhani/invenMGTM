<?php

namespace App\Http\Controllers\Admin;

use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $suppliers = Supplier::paginate(10);

        return view('admin.supplier.index', compact('suppliers'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SupplierRequest $request)
    {
        Supplier::create($request->all());

        return back()->with('toast_success', 'Supplier Berhasil Ditambahkan');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Supplier      $supplier
     * @return \Illuminate\Http\Response
     */
    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->all());

        return back()->with('toast_success', 'Supplier Berhasil Diubah');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Supplier  $supplier
     * @return \Illuminate\Http\Response
     */
    public function destroy(Supplier $supplier)
    {
        // 1. Cek apakah supplier sudah dipakai di tabel production_issue_items
        $dipakaiDiProductionIssue = DB::table('production_issue_items')
            ->where('supplier_id', $supplier->id)
            ->exists();

        if ($dipakaiDiProductionIssue) {
            // Kalau sudah dipakai, jangan hapus, kasih pesan manis ke user
            return back()->with(
                'toast_error',
                'Supplier tidak dapat dihapus karena sudah digunakan di transaksi Production Issue.'
            );
        }

        try {
            // 2. Kalau belum dipakai, baru dihapus
            $supplier->delete();

            return back()->with('toast_success', 'Supplier Berhasil Dihapus');
        } catch (QueryException $e) {
            // Kode 23000 = masalah relasi / foreign key
            if ($e->getCode() === '23000') {
                return back()->with(
                    'toast_error',
                    'Supplier tidak dapat dihapus karena masih berelasi dengan data lain.'
                );
            }

            // Kalau error lain, lempar lagi biar kelihatan saat debug
            throw $e;
        }
    }
}
