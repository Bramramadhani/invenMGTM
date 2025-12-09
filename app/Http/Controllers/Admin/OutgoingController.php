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
            ->direction(StockMovement::DIR_OUT)
            ->where(function ($wr) {
                $wr->whereNotNull('order_id')
                   ->orWhereNotNull('order_item_id');
            })
            ->with([
                // Supplier direct (dari stock_movements.supplier_id)
                'supplier:id,name',

                // Stock dengan info lengkap + relasi supplier & buyer
                'stock:id,material_code,material_name,unit,supplier_id,buyer_id',
                'stock.supplier:id,name',
                'stock.buyer:id,name,code',

                // Order + purchaseOrderStyle
                'order:id,production_name,production_leader_name,warehouse_admin_name,warehouse_leader_name,supply_chain_head_name,purchase_order_style_id',
                'order.purchaseOrderStyle',

                // OrderItem + order + purchaseOrderStyle (untuk fallback)
                'orderItem.order:id,production_name,production_leader_name,warehouse_admin_name,warehouse_leader_name,supply_chain_head_name,purchase_order_style_id',
                'orderItem.order.purchaseOrderStyle',
            ])
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
