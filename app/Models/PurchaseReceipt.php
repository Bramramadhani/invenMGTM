<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseReceipt extends Model
{
    use HasFactory;

    // Status constants (opsional, memudahkan reuse)
    public const STATUS_DRAFT  = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_VOID   = 'void';

    protected $fillable = [
        'purchase_order_id',
        'receipt_date',
        'receipt_number',
        'notes',
        'supplier_do_number',
        'status',
        'idempotency_token',
        'received_by',
        'posted_at',
        'posted_by',
        'voided_at',
        'voided_by',
        'edited_at',
    ];

    // CHANGED: tambahkan cast integer & edited_at biar konsisten
    protected $casts = [
        'purchase_order_id' => 'integer',
        'posted_by'         => 'integer',
        'voided_by'         => 'integer',
        'receipt_date'      => 'date',
        'posted_at'         => 'datetime',
        'voided_at'         => 'datetime',
        'edited_at'         => 'datetime',
    ];

    // CHANGED: default status saat create (aman, tidak override update)
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    // CHANGED: bila receipt berubah, sentuh updated_at di PO juga (berguna buat sinkron tampilan)
    protected $touches = ['purchaseOrder'];

    // Relations
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    // CHANGED: helper scopes (memudahkan query di controller/report)
    public function scopeDraft($q)
    {
        return $q->where('status', self::STATUS_DRAFT);
    }

    public function scopePosted($q)
    {
        return $q->where('status', self::STATUS_POSTED);
    }

    public function scopeBetweenDates($q, $start, $end)
    {
        return $q->whereBetween('receipt_date', [$start, $end]);
    }

    public function scopeOnDate($q, $date)
    {
        return $q->whereDate('receipt_date', $date);
    }

    // CHANGED: accessor kecil (quality of life)
    public function getIsPostedAttribute(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }
}
