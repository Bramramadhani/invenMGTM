<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class OutgoingController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $movs = StockMovement::query()
            ->with([
                'supplier:id,name',
                'stock:id,material_code',
                // NEW: ikutkan order & style supaya tidak N+1
                'order.purchaseOrderStyle',
            ])
            ->where('direction', StockMovement::DIR_OUT)
            ->search($q)
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString(); // bawa ?q= di pagination

        return view('admin.outgoing.index', [
            'movs' => $movs,
            'q'    => $q,
        ]);
    }
}
