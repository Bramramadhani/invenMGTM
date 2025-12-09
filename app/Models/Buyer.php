<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Buyer extends Model
{
    use HasFactory;

    protected $table = 'buyers';

    protected $fillable = [
        'name',
        'code',
        'contact_name',
        'phone',
        'email',
        'address',
        'notes',
    ];

    /**
     * Relasi: 1 Buyer punya banyak stok (FOB).
     */
    public function stocks()
    {
        return $this->hasMany(Stock::class, 'buyer_id');
    }
}
