<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    /**
     * Kolom yang boleh diisi mass-assignment.
     */
    protected $fillable = [
        'supplier_id',
        'po_number',
        'arrival_date',
        'target_completion_date',
        'is_completed',
        'notes',
    ];

    /**
     * Nilai default atribut.
     */
    protected $attributes = [
        'is_completed' => false,
    ];

    /**
     * Casting tipe data otomatis.
     */
    protected $casts = [
        'arrival_date'            => 'date',
        'target_completion_date'  => 'date',
        'is_completed'            => 'boolean',
    ];

    /* ========================= RELASI ========================= */

    /**
     * Relasi ke Supplier.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relasi ke item-item PO.
     */
    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class)
                    ->orderBy('material_name');
    }

    /**
     * Relasi ke batch penerimaan (Purchase Receipts).
     */
    public function receipts()
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    /**
     * Relasi ke Styles (Style Tas per PO).
     */
    public function styles()
    {
        return $this->hasMany(PurchaseOrderStyle::class);
    }

    /* ========================== SCOPE ========================= */

    /**
     * PO yang belum selesai.
     */
    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * PO yang sudah selesai.
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * PO yang mendekati jatuh tempo target_completion_date (<= $days hari).
     */
    public function scopeDueSoon($query, int $days = 3)
    {
        return $query->where('is_completed', false)
                     ->whereDate('target_completion_date', '<=', now()->addDays($days));
    }

    /**
     * Cari berdasarkan nomor PO (like).
     */
    public function scopeSearchNumber($query, ?string $term)
    {
        if (!$term) return $query;
        return $query->where('po_number', 'like', "%{$term}%");
    }
}
