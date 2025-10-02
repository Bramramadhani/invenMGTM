<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'material_code',             // ⬅️ KODE BARANG (baru)
        'material_name',
        'ordered_quantity',
        'actual_arrived_quantity',
        'unit',
        'unit_price',
        'notes',
    ];

    // presisi untuk qty/harga
    protected $casts = [
        'ordered_quantity'         => 'decimal:4',
        'actual_arrived_quantity'  => 'decimal:4',
        'unit_price'               => 'decimal:2',
    ];

    /* ==================== HOOK: RAPIKAN INPUT ==================== */
    protected static function booted()
    {
        static::saving(function (self $m) {
            // rapiin teks
            $m->material_name = trim((string) $m->material_name);
            $m->unit          = $m->unit !== null ? trim((string) $m->unit) : null;

            // kode barang: uppercase & trim
            if (!is_null($m->material_code)) {
                $code = trim((string) $m->material_code);
                // biarkan karakter non-alfanumerik jika memang dipakai (dash/underscore)
                $m->material_code = strtoupper($code);
            }
        });
    }

    /* ==================== RELASI ==================== */

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // batch penerimaan (parsial)
    public function receiptItems()
    {
        return $this->hasMany(\App\Models\PurchaseReceiptItem::class, 'purchase_order_item_id');
    }

    /* ==================== ACCESSOR ==================== */

    // total yang sudah diterima (dari tabel receipt items)
    public function getTotalReceivedAttribute()
    {
        return (float) $this->receiptItems()->sum('received_quantity');
    }

    // sisa yang belum datang
    public function getRemainingAttribute()
    {
        $ordered  = (float) $this->ordered_quantity;
        $received = (float) $this->total_received;
        return max(0, $ordered - $received);
    }

    // label ringkas untuk tampilan: [KODE] Nama
    public function getDisplayLabelAttribute(): string
    {
        $code = $this->material_code ? '['.$this->material_code.'] ' : '';
        return $code.$this->material_name;
    }

    /* ==================== (Opsional) SCOPE ==================== */

    // contoh agregasi penerimaan terbanyak per item (bukan stok berjalan)
    public function scopeTopReceived($query, $limit = 5)
    {
        return $query->select('purchase_order_items.material_name', 'purchase_orders.supplier_id', 'purchase_order_items.unit')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('purchase_receipt_items', 'purchase_receipt_items.purchase_order_item_id', '=', 'purchase_order_items.id')
            ->selectRaw('SUM(purchase_receipt_items.received_quantity) as total_received')
            ->groupBy('purchase_orders.supplier_id', 'purchase_order_items.material_name', 'purchase_order_items.unit')
            ->orderByDesc('total_received')
            ->limit($limit);
    }
}
