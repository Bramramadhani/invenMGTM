<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderReject extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_rejects';

    /**
     * Kolom yang boleh diisi mass-assignment.
     */
    protected $fillable = [
        'purchase_order_id',
        'purchase_order_item_id',
        'stock_id',
        'supplier_id',
        'material_name',
        'unit',
        'reject_quantity',
        'previous_notes',
        'new_notes',
        'reason',
        'rejected_at',
        'created_by',
    ];

    /**
     * Konversi otomatis tipe data.
     */
    protected $casts = [
        'reject_quantity' => 'decimal:4',
        'rejected_at' => 'datetime',
    ];

    /**
     * =========================
     * RELASI
     * =========================
     */

    /**
     * Relasi ke Purchase Order utama.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Relasi ke item Purchase Order.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    /**
     * Relasi ke stok material.
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * Relasi ke supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relasi ke user (checker/admin) yang membuat data reject.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * =========================
     * ACCESSORS / HELPERS
     * =========================
     */

    /**
     * Format tanggal reject agar mudah dibaca.
     */
    public function getRejectedAtFormattedAttribute(): ?string
    {
        return $this->rejected_at
            ? $this->rejected_at->format('d-m-Y H:i')
            : null;
    }

    /**
     * Format jumlah agar mudah dibaca di laporan.
     */
    public function getRejectQuantityFormattedAttribute(): string
    {
        return number_format($this->reject_quantity, 2);
    }

    /**
     * Gabungkan notes lama dan baru (untuk tampilan laporan).
     */
    public function getFullNotesAttribute(): string
    {
        $parts = [];
        if ($this->previous_notes) $parts[] = "Catatan Sebelumnya:\n" . $this->previous_notes;
        if ($this->new_notes) $parts[] = "Catatan Checker:\n" . $this->new_notes;
        return implode("\n\n", $parts);
    }
}
