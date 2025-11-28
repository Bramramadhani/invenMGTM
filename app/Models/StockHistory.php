<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockHistory extends Model
{
    protected $fillable = [
        'stock_id',
        'purchase_order_id',
        'type',
        'old_quantity',
        'new_quantity',
        'diff_quantity',
        'material_name',
        'material_code',
        'unit',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'stock_id'          => 'integer',
        'purchase_order_id' => 'integer',
        'old_quantity'      => 'decimal:4',
        'new_quantity'      => 'decimal:4',
        'diff_quantity'     => 'decimal:4',
        'created_by'        => 'integer',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Helper untuk mencatat perubahan stok.
     *
     * @param \App\Models\Stock $stock
     * @param float             $oldQty
     * @param float             $newQty
     * @param string            $type       contoh: 'manual_edit', 'manual_delete'
     * @param string|null       $reason
     * @param int|null          $userId
     */
    public static function recordChange(Stock $stock, float $oldQty, float $newQty, string $type, ?string $reason = null, ?int $userId = null): self
    {
        $diff = $newQty - $oldQty;

        return self::create([
            'stock_id'          => $stock->id,
            'purchase_order_id' => $stock->purchase_order_id,
            'type'              => $type,
            'old_quantity'      => $oldQty,
            'new_quantity'      => $newQty,
            'diff_quantity'     => $diff,
            'material_name'     => $stock->material_name,
            'material_code'     => $stock->material_code,
            'unit'              => $stock->unit,
            'reason'            => $reason,
            'created_by'        => $userId ?? auth()->id(),
        ]);
    }
}
