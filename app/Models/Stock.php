<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Stock extends Model
{
    protected $fillable = [
        'supplier_id',
        'material_name',
        'material_code',   
        'unit',
        'quantity',
        'last_po_id',
        'last_po_number',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'last_po_id' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (self $m) {
            $m->material_name = trim((string) $m->material_name);
            $m->material_code = $m->material_code !== null ? trim((string) $m->material_code) : null;
            $m->unit          = $m->unit !== null ? trim((string) $m->unit) : null;

            // OPTIONAL: paksa kode jadi uppercase
            if ($m->material_code !== null) {
                $m->material_code = strtoupper($m->material_code);
            }
        });
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class);
    }

    public function lastPo()
    {
        return $this->belongsTo(\App\Models\PurchaseOrder::class, 'last_po_id');
    }

    /**
     * Pencarian cepat: nama, KODE material, unit, PO terakhir, nama supplier.
     */
    public function scopeSearch(Builder $q, ?string $term)
    {
        if (!$term) return $q;
        $like = "%{$term}%";

        return $q->where(function (Builder $qq) use ($like) {
            $qq->where('material_name', 'like', $like)
               ->orWhere('material_code', 'like', $like) 
               ->orWhere('unit', 'like', $like)
               ->orWhere('last_po_number', 'like', $like)
               ->orWhereHas('supplier', function (Builder $qs) use ($like) {
                   $qs->where('name', 'like', $like);
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

    // OPTIONAL: urutan default yang rapi saat listing
    public function scopeOrderNice(Builder $q)
    {
        return $q->orderBy('material_code')
                 ->orderBy('material_name')
                 ->orderBy('unit');
    }

    // OPTIONAL: label siap pakai di view
    public function getDisplayLabelAttribute(): string
    {
        $code = $this->material_code ? "[{$this->material_code}] " : '';
        return $code . $this->material_name;
    }
}
