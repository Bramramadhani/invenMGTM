<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class StockMovement extends Model
{
    use HasFactory;

    /** Direction constants */
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
        'order_id',       // optional (kalau kolom belum ada, tidak akan dikirim saat insert)
        'order_item_id',  // optional
        'moved_at',
    ];

    protected $casts = [
        'quantity'      => 'decimal:4',
        'moved_at'      => 'datetime',
        'stock_id'      => 'integer',
        'supplier_id'   => 'integer',
        'order_id'      => 'integer',
        'order_item_id' => 'integer',
    ];

    /* =========================
     *          RELATIONS
     * ========================= */

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Helper: tentukan Order terkait untuk baris movement ini.
     * - Prioritas: kolom order_id
     * - Alternatif: order_item_id -> order
     */
    public function getResolvedOrderAttribute()
    {
        if ($this->relationLoaded('order') && $this->order) {
            return $this->order;
        }
        if ($this->relationLoaded('orderItem') && $this->orderItem) {
            return $this->orderItem->relationLoaded('order')
                ? $this->orderItem->order
                : $this->orderItem()->with('order')->first()?->order;
        }

        if (!empty($this->order_id)) {
            return $this->order()->first();
        }
        if (!empty($this->order_item_id)) {
            return $this->orderItem()->with('order')->first()?->order;
        }
        return null;
    }

    /* =========================
     *         ACCESSORS
     * ========================= */

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

    /* =========================
     *           SCOPES
     * ========================= */

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

    /**
     * Eager-load standar untuk halaman laporan — termasuk order (agar 3 nama bisa ditarik).
     */
    public function scopeWithReportRelations($q)
    {
        return $q->with([
            'supplier:id,name',
            'stock:id,material_code,material_name,unit,last_po_number',
            'order:id,production_name,warehouse_admin_name,warehouse_leader_name,supply_chain_head_name',
            'orderItem.order:id,production_name,warehouse_admin_name,warehouse_leader_name,supply_chain_head_name',
        ]);
    }

    /* =========================
     *       RECORD HELPERS
     * ========================= */

    public static function recordIn(
        ?int $stockId,
        int $supplierId,
        string $material,
        ?string $unit,
        float $qty,
        ?string $poNumber = null,
        ?string $notes = null,
        ?\DateTimeInterface $movedAt = null
    ): self {
        $data = [
            'stock_id'      => $stockId,
            'supplier_id'   => $supplierId,
            'material_name' => $material,
            'unit'          => $unit,
            'po_number'     => $poNumber,
            'direction'     => self::DIR_IN,
            'quantity'      => $qty,
            'notes'         => $notes,
            'moved_at'      => $movedAt ?? now(),
        ];

        return self::create($data);
    }

    /**
     * Catat movement OUT.
     *
     * - $movedAt sengaja TANPA typehint agar kompatibel dengan pemanggilan lama
     *   (yang dulu salah kirim string seperti 'production_issues' di posisi ini).
     * - Akan otomatis “menggeser” argumen legacy dan fallback kalau kolom order_id/order_item_id
     *   belum ada di database (tanpa melempar error Unknown column).
     */
    public static function recordOut(
        ?int $stockId,
        int $supplierId,
        string $material,
        ?string $unit,
        float $qty,
        ?string $poNumber = null,
        ?string $notes = null,
        $movedAt = null,          // ← fleksibel: bisa DateTimeInterface|null|string (legacy)
        ?int $orderId = null,
        ?int $orderItemId = null
    ): self {
        // ====== KOMPATIBILITAS PEMANGGILAN LAMA ======
        // Pola lama: recordOut(..., $poNumber, $notes, 'production_issues', $issueId, now())
        // Sehingga $movedAt berisi string, $orderId berisi int issueId, $orderItemId berisi DateTimeInterface
        if (!($movedAt instanceof \DateTimeInterface) && $movedAt !== null) {
            if ($orderItemId instanceof \DateTimeInterface) {
                // Geser argumen ke posisi benar
                $movedAt     = $orderItemId; // now()
                $orderId     = null;         // abaikan ref_id legacy
                $orderItemId = null;
            } else {
                // Tidak ada timestamp valid → pakai sekarang
                $movedAt = now();
            }
        }
        if ($movedAt === null) {
            $movedAt = now();
        }

        // Data dasar
        $data = [
            'stock_id'      => $stockId,
            'supplier_id'   => $supplierId,
            'material_name' => $material,
            'unit'          => $unit,
            'po_number'     => $poNumber,
            'direction'     => self::DIR_OUT,
            'quantity'      => $qty,
            'notes'         => $notes,
            'moved_at'      => $movedAt,
        ];

        // Tambahkan referensi order hanya jika kolomnya memang ada
        if (Schema::hasColumn('stock_movements', 'order_id')) {
            $data['order_id'] = $orderId;
        }
        if (Schema::hasColumn('stock_movements', 'order_item_id')) {
            $data['order_item_id'] = $orderItemId;
        }

        // Coba insert; jika DB ternyata belum punya kolom (beda DB / migrasi belum jalan),
        // lakukan retry tanpa kolom referensi order agar proses tidak terblokir.
        try {
            return self::create($data);
        } catch (\Illuminate\Database\QueryException $e) {
            $msg = $e->getMessage();
            $unknownOrderCol =
                (stripos($msg, 'Unknown column') !== false)
                && (stripos($msg, 'order_id') !== false || stripos($msg, 'order_item_id') !== false);

            if ($unknownOrderCol) {
                unset($data['order_id'], $data['order_item_id']);
                return self::create($data);
            }

            throw $e;
        }
    }
}
