<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptCorrectionAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_receipt_id',
        'purchase_receipt_item_id',
        'purchase_order_id',
        'purchase_order_item_id',
        'stock_id',
        'material_name',
        'material_code',
        'unit',
        'old_received_qty',
        'new_received_qty',
        'delta_received_qty',
        'stock_old_qty',
        'stock_new_qty',
        'is_forced',
        'reason',
        'force_reason',
        'created_by',
    ];

    protected $casts = [
        'old_received_qty'   => 'float',
        'new_received_qty'   => 'float',
        'delta_received_qty' => 'float',
        'stock_old_qty'      => 'float',
        'stock_new_qty'      => 'float',
        'is_forced'          => 'boolean',
    ];

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function receiptItem()
    {
        return $this->belongsTo(PurchaseReceiptItem::class, 'purchase_receipt_item_id');
    }
}

