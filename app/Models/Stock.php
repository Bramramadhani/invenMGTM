<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Stock extends Model
{
    protected $fillable = [
        'purchase_order_id',  // batch ini milik PO mana (untuk stok normal)
        'supplier_id',        // supplier (stok normal)
        'buyer_id',           // buyer (stok FOB) - boleh null
        'material_name',
        'material_code',
        'unit',
        'quantity',
        // NOTE: kolom legacy dihapus dari fillable agar tidak terisi lagi secara tidak sengaja
        // 'last_po_id',
        // 'last_po_number',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'purchase_order_id' => 'integer',
        'supplier_id'       => 'integer',
        'buyer_id'          => 'integer',
        // 'last_po_id'      => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (self $m) {
            $m->material_name = trim((string) $m->material_name);
            $m->material_code = $m->material_code !== null ? trim((string) $m->material_code) : null;
            $m->unit          = $m->unit !== null ? trim((string) $m->unit) : null;

            if ($m->material_code !== null) {
                $m->material_code = strtoupper($m->material_code);
            }
        });
    }

    // ===================== RELASI =====================

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(\App\Models\PurchaseOrder::class);
    }

    /**
     * Buyer (untuk stok FOB).
     */
    public function buyer()
    {
        return $this->belongsTo(\App\Models\Buyer::class, 'buyer_id');
    }

    /**
     * Riwayat perubahan stok (manual_edit, receipt_correction, fob_edit, dll).
     */
    public function histories()
    {
        return $this->hasMany(\App\Models\StockHistory::class);
    }

    // ===================== SCOPES =====================

    /**
     * Pencarian cepat: nama, KODE material, unit, NO PO, nama supplier, nama/kode buyer.
     */
    public function scopeSearch(Builder $q, ?string $term)
    {
        if (!$term) return $q;
        $like = "%{$term}%";

        return $q->where(function (Builder $qq) use ($like) {
            $qq->where('material_name', 'like', $like)
               ->orWhere('material_code', 'like', $like)
               ->orWhere('unit', 'like', $like)
               // Cari via Supplier
               ->orWhereHas('supplier', function (Builder $qs) use ($like) {
                   $qs->where('name', 'like', $like);
               })
               // Cari via Purchase Order
               ->orWhereHas('purchaseOrder', function (Builder $qs) use ($like) {
                   $qs->where('po_number', 'like', $like);
               })
               // Cari via Buyer (untuk stok FOB)
               ->orWhereHas('buyer', function (Builder $qb) use ($like) {
                   $qb->where('name', 'like', $like)
                      ->orWhere('code', 'like', $like);
               });
        });
    }

    public function scopeLowStock(Builder $q, $threshold = 10)
    {
        return $q->where('quantity', '<=', $threshold);
    }

    public function scopeForSupplier(Builder $q, $supplierId)
    {
        return $q->when($supplierId, fn (Builder $qq) => $qq->where('supplier_id', $supplierId));
    }

    /**
     * Filter by Buyer (untuk laporan / tampilan FOB).
     */
    public function scopeForBuyer(Builder $q, $buyerId)
    {
        return $q->when($buyerId, fn (Builder $qq) => $qq->where('buyer_id', $buyerId));
    }

    /**
     * Stok normal (dari PO/supplier) → buyer_id = NULL.
     */
    public function scopeRegular(Builder $q)
    {
        return $q->whereNull('buyer_id');
    }

    /**
     * Stok FOB (punya Buyer) → buyer_id TIDAK NULL.
     */
    public function scopeFob(Builder $q)
    {
        return $q->whereNotNull('buyer_id');
    }

    public function scopeOrderNice(Builder $q)
    {
        return $q->orderBy('material_code')
                 ->orderBy('material_name')
                 ->orderBy('unit');
    }

    // ===================== ACCESSOR =====================

    public function getDisplayLabelAttribute(): string
    {
        $code = $this->material_code ? "[{$this->material_code}] " : '';
        return $code . $this->material_name;
    }
}
