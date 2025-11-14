<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ProductionIssueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_issue_id',
        'order_item_id',
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

    /* ============ Hooks: rapikan input ============ */
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

    /* ============ Relasi ============ */
    public function issue()
    {
        return $this->belongsTo(ProductionIssue::class, 'production_issue_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    /* ============ Accessor/helper ============ */

    /** No. PO asal stok (kalau di-post dari stok per-PO). */
    public function getPoNumberAttribute(): ?string
    {
        return optional($this->stock)->last_po_number;
    }

    /** Label tampil nyaman: [KODE] Nama. */
    public function getDisplayLabelAttribute(): string
    {
        $code = $this->material_code ? '['.$this->material_code.'] ' : '';
        return $code.$this->material_name;
    }

    /* ============ Scope pencarian (opsional) ============ */
    public function scopeSearch(Builder $q, ?string $term)
    {
        if (!$term) return $q;
        $like = "%{$term}%";

        return $q->where(function (Builder $w) use ($like) {
            $w->where('material_code', 'like', $like)
              ->orWhere('material_name', 'like', $like)
              ->orWhere('unit', 'like', $like)
              ->orWhere('notes', 'like', $like)
              ->orWhereHas('supplier', fn (Builder $s) => $s->where('name', 'like', $like))
              ->orWhereHas('stock', fn (Builder $st) => $st->where('last_po_number', 'like', $like));
        });
    }
}
