<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /**
     * Halaman stok.
     * - group = supplier-po  → tampil per Supplier ➜ PO (tanpa pagination baris)
     * - group = flat         → tabel flat dengan pagination
     */
    public function index(Request $request)
    {
        $term  = trim((string) $request->get('q', ''));
        $group = (string) $request->get('group', 'supplier-po');

        $query = Stock::with(['supplier', 'purchaseOrder'])
            ->search($term)
            ->orderBy('material_code')
            ->orderBy('material_name')
            ->orderBy('unit');

        if ($group === 'supplier-po') {
            // Ambil semua baris stok untuk digroup per Supplier+PO di view
            $stocks = $query->get();
        } else {
            // Mode flat: pakai pagination biasa
            $stocks = $query->paginate(15)->withQueryString();
        }

        return view('admin.stock.index', [
            'stocks' => $stocks,
            'term'   => $term,
            'group'  => $group,
        ]);
    }
}
