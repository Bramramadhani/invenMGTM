<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'name',
        'notes',
        'production_name',
        'production_leader_name',   
        'warehouse_admin_name',
        'warehouse_leader_name',
        'supply_chain_head_name',
        'purchase_order_style_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(\App\Models\OrderItem::class, 'order_id');
    }

    /**
     * Style PO yang menjadi dasar permintaan ini.
     * (tabel: purchase_order_styles)
     */
    public function purchaseOrderStyle()
    {
        return $this->belongsTo(PurchaseOrderStyle::class, 'purchase_order_style_id');
    }

    // (opsional) accessor image kalau masih dipakai
    public function getImageAttribute($image)
    {
        return $image ? asset('storage/orders/' . $image) : null;
    }

    public function getStatusLabelAttribute(): string
    {
        $raw = (string) $this->attributes['status'] ?? '';
        $map = [
            'pending'  => 'Menunggu Konfirmasi',
            'verified' => 'Terverifikasi',
            'success'  => 'Selesai',
        ];
        return $map[strtolower($raw)] ?? $raw;
    }
}
