<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceiptItem extends Model
{
    protected $fillable = [
        'purchase_receipt_id',
        'purchase_order_item_id',
        'supplier_id',
        'material_name',
        'unit',
        'received_quantity',
        'unit_price',
        'notes',
    ];

    // relasi ke parent receipt
    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    // relasi ke baris purchase order item
    public function orderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    // relasi ke supplier
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
