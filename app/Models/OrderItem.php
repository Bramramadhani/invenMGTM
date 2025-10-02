<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'stock_id',
        'supplier_id',
        'material_code',   
        'material_name',
        'unit',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'stock_id' => 'integer',
    ];

    /* -------- Hooks: rapikan input -------- */
    protected static function booted()
    {
        static::saving(function (self $m) {
            $m->material_name = trim((string) $m->material_name);
            $m->unit          = $m->unit !== null ? trim((string) $m->unit) : null;

            if (!is_null($m->material_code)) {
                $code = trim((string) $m->material_code);
                $m->material_code = strtoupper($code);
            }
        });
    }

    /* -------- Relasi -------- */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /* -------- Accessor/helper -------- */
    public function getPoNumberAttribute(): ?string
    {
        return optional($this->stock)->last_po_number;
    }

    public function getAvailableAttribute(): float
    {
        return (float) (optional($this->stock)->quantity ?? 0);
    }

    /** Label tampil: [KODE] Nama */
    public function getDisplayLabelAttribute(): string
    {
        $code = $this->material_code ? '['.$this->material_code.'] ' : '';
        return $code.$this->material_name;
    }

    /* -------- Scope pencarian (opsional) -------- */
    public function scopeSearch(Builder $q, ?string $term)
    {
        if (!$term) return $q;
        $like = "%{$term}%";

        return $q->where(function (Builder $w) use ($like) {
            $w->where('material_code', 'like', $like)
              ->orWhere('material_name', 'like', $like)
              ->orWhere('unit', 'like', $like)
              ->orWhereHas('supplier', fn (Builder $s) => $s->where('name', 'like', $like))
              ->orWhereHas('stock', fn (Builder $st) => $st->where('last_po_number', 'like', $like));
        });
    }
}
