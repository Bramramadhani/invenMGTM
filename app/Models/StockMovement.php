<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    public const DIR_IN  = 'IN';
    public const DIR_OUT = 'OUT';
    public const DIR_ADJ = 'ADJ';

    protected $fillable = [
        'stock_id',
        'supplier_id',
        'material_name',
        'unit',
        'po_number',
        'direction',
        'quantity',
        'notes',
        'ref_type',
        'ref_id',
        'moved_at',
    ];

    protected $casts = [
        'quantity'    => 'decimal:4',
        'moved_at'    => 'datetime',
        'stock_id'    => 'integer',
        'supplier_id' => 'integer',
    ];

    /** Relasi */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    protected $appends = [
        'signed_quantity',
        'material_code', 
    ];

    public function getSignedQuantityAttribute(): float
    {
        $q = (float) $this->quantity;
        return $this->direction === self::DIR_OUT ? -$q : $q;
    }

    public function getMaterialCodeAttribute(): ?string
    {
        return optional($this->stock)->material_code;
    }


    public function scopeDirection($q, ?string $dir)
    {
        if (in_array($dir, [self::DIR_IN, self::DIR_OUT, self::DIR_ADJ], true)) {
            $q->where('direction', $dir);
        }
        return $q;
    }

    public function scopeBetween($q, ?string $startDate, ?string $endDate)
    {
        if ($startDate) $q->whereDate('moved_at', '>=', $startDate);
        if ($endDate)   $q->whereDate('moved_at', '<=', $endDate);
        return $q;
    }

    public function scopeSearch($q, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') return $q;

        $like = "%{$term}%";

        return $q->where(function ($w) use ($like) {
            $w->where('material_name', 'like', $like)
              ->orWhere('unit', 'like', $like)
              ->orWhere('po_number', 'like', $like)
              ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', $like))
              ->orWhereHas('stock', fn($st) => $st->where('material_code', 'like', $like));
        });
    }

    public static function recordIn(
        ?int $stockId,
        int $supplierId,
        string $material,
        ?string $unit,
        float $qty,
        ?string $poNumber = null,
        ?string $notes = null,
        ?string $refType = null,
        ?int $refId = null,
        ?\DateTimeInterface $movedAt = null
    ): self {
        return self::create([
            'stock_id'      => $stockId,
            'supplier_id'   => $supplierId,
            'material_name' => $material,
            'unit'          => $unit,
            'po_number'     => $poNumber,
            'direction'     => self::DIR_IN,
            'quantity'      => $qty,
            'notes'         => $notes,
            'ref_type'      => $refType,
            'ref_id'        => $refId,
            'moved_at'      => $movedAt ?? now(),
        ]);
    }

    /** Catat pergerakan OUT */
    public static function recordOut(
        ?int $stockId,
        int $supplierId,
        string $material,
        ?string $unit,
        float $qty,
        ?string $poNumber = null,
        ?string $notes = null,
        ?string $refType = null,
        ?int $refId = null,
        ?\DateTimeInterface $movedAt = null
    ): self {
        return self::create([
            'stock_id'      => $stockId,
            'supplier_id'   => $supplierId,
            'material_name' => $material,
            'unit'          => $unit,
            'po_number'     => $poNumber,
            'direction'     => self::DIR_OUT,
            'quantity'      => $qty,
            'notes'         => $notes,
            'ref_type'      => $refType,
            'ref_id'        => $refId,
            'moved_at'      => $movedAt ?? now(),
        ]);
    }
}
