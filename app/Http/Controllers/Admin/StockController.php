<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /**
     * Halaman stok: pencarian sederhana (material/unit/supplier), urut nama.
     */
    public function index(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        // use the Stock::scopeSearch to cover material_name, material_code, unit,
        // last_po_number and supplier name in one place
        $stocks = Stock::with('supplier')
            ->search($term)
            ->orderBy('material_name')
            ->orderBy('unit')
            ->paginate(15)
            ->withQueryString();

        return view('admin.stock.index', [
            'stocks' => $stocks,
            'term'   => $term,
        ]);
    }

    /**
     * Nonaktif: kita jaga stok melalui Posting IN/OUT,
     * bukan edit manual dari halaman stok.
     */
    public function update()
    {
        abort(404);
    }
}
