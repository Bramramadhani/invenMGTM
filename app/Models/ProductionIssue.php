<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_date',
        'issue_number',
        'notes',
        'status',
        'issued_by',
        'posted_at',
        'posted_by',
        'order_id',

        // sudah kamu tambahkan sebelumnya
        'requested_at',
        'requested_by',

        // NEW: relasi ke style PO
        'purchase_order_style_id',
    ];

    protected $casts = [
        'issue_date'   => 'date',
        'posted_at'    => 'datetime',
        'requested_at' => 'datetime',
    ];

    // =============== RELATIONS ===============

    public function items()
    {
        return $this->hasMany(ProductionIssueItem::class);
    }

    // user yang meminta (opsional)
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    // user yang mem-posting dokumen
    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    // user yang mengeluarkan/issuer jika pakai kolom issued_by
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    // relasi ke order permintaan barang
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // NEW: relasi ke Style PO
    public function style()
    {
        return $this->belongsTo(PurchaseOrderStyle::class, 'purchase_order_style_id');
    }
}
