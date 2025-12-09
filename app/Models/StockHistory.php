<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockHistory extends Model
{
    use HasFactory;

    protected $table = 'stock_histories';

    /* =========================
     *    TYPE CONSTANTS
     * ========================= */

    /** Penerimaan barang dari PO */
    public const TYPE_PO_RECEIVE = 'po_receive';

    /** Koreksi penerimaan PO (masih termasuk pembelian) */
    public const TYPE_PO_CORRECTION = 'po_correction';

    /** Koreksi stok PO manual (bukan pembelian baru) */
    public const TYPE_MANUAL_CORRECTION = 'manual_correction';

    /** Pembelian stok FOB */
    public const TYPE_FOB_CREATE = 'fob_create';

    /** Edit / koreksi stok FOB (bukan pembelian baru) */
    public const TYPE_FOB_UPDATE = 'fob_update';

    /** Hapus stok FOB (bukan pembelian baru) */
    public const TYPE_FOB_DELETE = 'fob_delete';

    protected $fillable = [
        'stock_id',
        'purchase_order_id',
        'type',
        'old_quantity',
        'new_quantity',
        'diff_quantity',
        'material_name',
        'material_code',
        'unit',
        'reason',
        'created_by',
        'unit_price',   // NEW
        'total_price',  // NEW
    ];

    protected $casts = [
        'old_quantity'  => 'float',
        'new_quantity'  => 'float',
        'diff_quantity' => 'float',
        'unit_price'    => 'float',
        'total_price'   => 'float',
    ];

    // Relasi ke stok
    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =========================
     *         SCOPES
     * ========================= */

    /**
     * Filter untuk laporan PEMBELIAN saja.
     * Hanya include type: po_receive, po_correction, fob_create.
     * Exclude: manual_correction, fob_update, fob_delete.
     */
    public function scopeForPurchases($q)
    {
        return $q->whereIn('type', [
            self::TYPE_PO_RECEIVE,
            self::TYPE_PO_CORRECTION,
            self::TYPE_FOB_CREATE,
        ]);
    }

    /**
     * Filter untuk exclude semua jenis koreksi/adjustment stok.
     * Kebalikan dari forPurchases().
     */
    public function scopeExcludeAdjustments($q)
    {
        return $q->whereNotIn('type', [
            self::TYPE_MANUAL_CORRECTION,
            self::TYPE_FOB_UPDATE,
            self::TYPE_FOB_DELETE,
        ]);
    }

    /* =========================
     *         HELPERS
     * ========================= */

    /**
     * Helper untuk mencatat perubahan stok.
     *
     * $type (gunakan konstanta di atas):
     * - TYPE_PO_RECEIVE        → penerimaan barang dari PO (pembelian).
     * - TYPE_PO_CORRECTION     → koreksi penerimaan PO (masih pembelian).
     * - TYPE_MANUAL_CORRECTION → koreksi stok PO/FOB manual (bukan pembelian).
     * - TYPE_FOB_CREATE        → pembelian stok FOB (pembelian).
     * - TYPE_FOB_UPDATE        → edit stok FOB (bukan pembelian baru).
     * - TYPE_FOB_DELETE        → hapus stok FOB (bukan pembelian baru).
     *
     * $unitPrice & $totalPrice dipakai untuk kasus pembelian FOB
     * (bisa null untuk history lain).
     */
    public static function recordChange(
        Stock $stock,
        float $oldQty,
        float $newQty,
        string $type,
        ?string $reason = null,
        ?int $userId = null,
        ?float $unitPrice = null,
        ?float $totalPrice = null
    ): self {
        return self::create([
            'stock_id'       => $stock->id,
            'purchase_order_id' => $stock->purchase_order_id,
            'type'           => $type,
            'old_quantity'   => $oldQty,
            'new_quantity'   => $newQty,
            'diff_quantity'  => $newQty - $oldQty,
            'material_name'  => $stock->material_name,
            'material_code'  => $stock->material_code,
            'unit'           => $stock->unit,
            'reason'         => $reason,
            'created_by'     => $userId,
            'unit_price'     => $unitPrice,
            'total_price'    => $totalPrice,
        ]);
    }
}
