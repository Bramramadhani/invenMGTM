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

        // NEW: agar bisa diisi saat create/store
        'requested_at',
        'requested_by',
    ];

    protected $casts = [
        'issue_date'   => 'date',
        'posted_at'    => 'datetime',

        // NEW: supaya bisa format tanggal & jam terpisah di view/pdf
        'requested_at' => 'datetime',
    ];

    // =============== RELATIONS ===============

    public function items()
    {
        return $this->hasMany(ProductionIssueItem::class);
    }

    // NEW: user yang meminta (untuk ditampilkan di PDF/Detail)
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    // (Opsional) user yang mem-posting dokumen
    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    // (Opsional) user yang mengeluarkan/issuer jika Anda pakai kolom issued_by
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    // (Opsional) relasi ke order jika dibutuhkan di UI
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
