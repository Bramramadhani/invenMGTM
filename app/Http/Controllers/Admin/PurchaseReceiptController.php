<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReject;
use App\Models\PurchaseReceipt;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class PurchaseReceiptController extends Controller
{
    /**
     * Form buat draft penerimaan baru dari PO.
     */
    public function create(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['items' => function ($q) {
            $q->withSum(['receiptItems as received_total' => function ($r) {
                $r->whereHas('receipt', fn ($rec) => $rec->where('status', PurchaseReceipt::STATUS_POSTED));
            }], 'received_quantity');
        }]);

        $purchaseOrder->items->transform(function ($item) {
            $received = (float) ($item->received_total ?? 0);
            $item->remaining = max(0, (float) $item->ordered_quantity - $received);
            return $item;
        });

        $idempotencyToken = (string) Str::uuid();

        return view('admin.receipts.create', compact('purchaseOrder', 'idempotencyToken'));
    }

    /**
     * Simpan draft penerimaan (status: draft).
     */
    public function store(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'receipt_date'         => ['required', 'date'],
            'idempotency_token'    => ['nullable', 'string', 'max:100'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.received_quantity'      => ['nullable', 'numeric', 'min:0'],
            'items.*.notes'                  => ['nullable', 'string', 'max:500'],
        ]);

        // Filter item yang jumlahnya > 0
        $filtered = [];
        foreach ($data['items'] as $idx => $row) {
            $qty = isset($row['received_quantity']) ? (float) $row['received_quantity'] : 0.0;
            if ($qty > 0) {
                $filtered[] = [
                    'purchase_order_item_id' => (int) $row['purchase_order_item_id'],
                    'received_quantity'      => $qty,
                    'notes'                  => $row['notes'] ?? null,
                    'index'                  => $idx,
                ];
            }
        }

        if (empty($filtered)) {
            throw ValidationException::withMessages([
                'items' => 'Masukkan jumlah diterima (>0) pada minimal satu item.',
            ]);
        }

        $itemIds = array_column($filtered, 'purchase_order_item_id');
        $validIds = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)
            ->whereIn('id', $itemIds)
            ->pluck('id')
            ->all();

        if (count($validIds) !== count($itemIds)) {
            throw ValidationException::withMessages([
                'items' => 'Terdapat item yang tidak termasuk pada Purchase Order ini.',
            ]);
        }

        // Ambil total penerimaan sebelumnya (receipt sudah POSTED)
        $posted = DB::table('purchase_receipt_items as pri')
            ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
            ->where('pr.status', PurchaseReceipt::STATUS_POSTED)
            ->whereIn('pri.purchase_order_item_id', $itemIds)
            ->groupBy('pri.purchase_order_item_id')
            ->selectRaw('pri.purchase_order_item_id, SUM(pri.received_quantity) AS received_total')
            ->pluck('received_total', 'purchase_order_item_id');

        $ordered = PurchaseOrderItem::whereIn('id', $itemIds)
            ->pluck('ordered_quantity', 'id');

        // Validasi sisa qty
        $errors = [];
        foreach ($filtered as $row) {
            $id  = $row['purchase_order_item_id'];
            $qty = (float) $row['received_quantity'];

            $ord      = (float) ($ordered[$id] ?? 0);
            $recSoFar = (float) ($posted[$id] ?? 0);
            $remaining = max(0, $ord - $recSoFar);

            if ($remaining <= 0) {
                $errors["items.{$row['index']}.received_quantity"] = "Item #{$id} sudah terpenuhi (sisa 0).";
            } elseif ($qty > $remaining) {
                $errors["items.{$row['index']}.received_quantity"] = "Qty ({$qty}) melebihi sisa ({$remaining}) untuk item #{$id}.";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($purchaseOrder, $data, $filtered) {
            // Lock PO
            DB::table('purchase_orders')->where('id', $purchaseOrder->id)->lockForUpdate()->value('id');

            // Idempotency
            if (!empty($data['idempotency_token'])) {
                $existing = PurchaseReceipt::where('purchase_order_id', $purchaseOrder->id)
                    ->where('status', PurchaseReceipt::STATUS_DRAFT)
                    ->where('idempotency_token', $data['idempotency_token'])
                    ->first();
                if ($existing) {
                    return;
                }
            }

            // Auto increment RC-XXX-YYY
            $maxSuffix = (int) DB::table('purchase_receipts')
                ->where('purchase_order_id', $purchaseOrder->id)
                ->lockForUpdate()
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED)), 0) AS max_suf")
                ->value('max_suf');

            $next   = $maxSuffix + 1;
            $number = 'RC-' . $purchaseOrder->po_number . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);

            $rc = PurchaseReceipt::create([
                'purchase_order_id' => $purchaseOrder->id,
                'receipt_date'      => $data['receipt_date'],
                'receipt_number'    => $number,
                'status'            => PurchaseReceipt::STATUS_DRAFT,
                'idempotency_token' => $data['idempotency_token'] ?? null,
            ]);

            foreach ($filtered as $row) {
                $poi = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)
                    ->findOrFail($row['purchase_order_item_id']);

                $rc->items()->create([
                    'purchase_order_item_id' => $poi->id,
                    'supplier_id'            => $purchaseOrder->supplier_id,
                    'material_name'          => $poi->material_name,
                    'unit'                   => $poi->unit,
                    'received_quantity'      => (float) $row['received_quantity'],
                    'notes'                  => $row['notes'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('admin.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Penerimaan parsial tersimpan sebagai DRAFT.');
    }

    /**
     * PDF satu receipt (tunggal) + catatan reject.
     */
    public function pdf(PurchaseReceipt $receipt)
    {
        $receipt->load(['purchaseOrder.supplier', 'items']);

        // Gabungkan catatan reject ke notes
        foreach ($receipt->items as $it) {
            $rejects = PurchaseOrderReject::where('purchase_order_item_id', $it->purchase_order_item_id)
                ->orderByDesc('rejected_at')
                ->get(['reject_quantity', 'new_notes', 'rejected_at']);

            if ($rejects->isNotEmpty()) {
                $rejectLines = $rejects->map(function ($r) use ($it) {
                    $dt = optional($r->rejected_at)->format('d/m/Y H:i');
                    return "--- Reject: {$r->reject_quantity} {$it->unit}"
                        . ($r->new_notes ? " ({$r->new_notes}, {$dt})" : " ({$dt})");
                })->toArray();

                $it->notes = trim(($it->notes ?? '') . "\n" . implode("\n", $rejectLines));
            }
        }

        $filename = 'Receipt_' . ($receipt->receipt_number ?: $receipt->id) . '.pdf';

        return PDF::loadView('admin.receipts.pdf', compact('receipt'))
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * PDF gabungan semua receipt POSTED untuk satu PO.
     */
    public function pdfMerged(PurchaseOrder $purchaseOrder)
    {
        $receipts = PurchaseReceipt::with(['items', 'purchaseOrder.supplier'])
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('status', PurchaseReceipt::STATUS_POSTED)
            ->orderBy('receipt_date')
            ->orderBy('id')
            ->get();

        if ($receipts->isEmpty()) {
            return back()->with('warning', 'Belum ada receipt POSTED untuk PO ini.');
        }

        $agg = [];
        foreach ($receipts as $rc) {
            foreach ($rc->items as $it) {
                $key = (string) $it->purchase_order_item_id;

                if (!isset($agg[$key])) {
                    $agg[$key] = [
                        'material_name' => $it->material_name,
                        'unit'          => $it->unit,
                        'quantity'      => 0.0,
                        'notes'         => [],
                    ];
                }

                $agg[$key]['quantity'] += (float) $it->received_quantity;
                if (!empty($it->notes)) {
                    $agg[$key]['notes'][] = $it->notes;
                }

                // Tambahkan catatan reject
                $rejects = PurchaseOrderReject::where('purchase_order_item_id', $it->purchase_order_item_id)
                    ->orderByDesc('rejected_at')
                    ->get(['reject_quantity', 'new_notes', 'rejected_at']);

                foreach ($rejects as $r) {
                    $dt = optional($r->rejected_at)->format('d/m/Y H:i');
                    $agg[$key]['notes'][] = "--- Reject: {$r->reject_quantity} {$it->unit}"
                        . ($r->new_notes ? " ({$r->new_notes}, {$dt})" : " ({$dt})");
                }
            }
        }

        $items = collect($agg)->map(function ($row) {
            $row['notes'] = empty($row['notes'])
                ? ''
                : implode("\n", array_unique($row['notes']));

            return (object) $row;
        })->sortBy('material_name')->values();

        $datesSummary = $receipts->groupBy(fn ($r) => optional($r->receipt_date)->format('Y-m-d'))
            ->map(fn ($group, $ymd) => [
                'date'     => $ymd,
                'receipts' => $group->count(),
                'rows'     => $group->sum(fn ($r) => $r->items->count()),
                'qty'      => $group->reduce(
                    fn ($s, $r) => $s + (float) $r->items->sum('received_quantity'),
                    0
                ),
            ])->values();

        $meta = (object) [
            'receipt_number' => 'RC-' . $purchaseOrder->po_number . '-MERGED',
            'status'         => PurchaseReceipt::STATUS_POSTED,
            'receipt_date'   => optional($receipts->min('receipt_date')),
            'posted_at'      => optional($receipts->max('posted_at')),
            'purchaseOrder'  => $purchaseOrder->load('supplier'),
            'notes'          => 'Gabungan semua receipt yang sudah diposting untuk PO ini.',
        ];

        $pdf = PDF::loadView('admin.receipts.pdf_merged', [
            'po'           => $purchaseOrder,
            'receipt'      => $meta,
            'items'        => $items,
            'datesSummary' => $datesSummary,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('RC-' . $purchaseOrder->po_number . '-MERGED.pdf');
    }

    /**
     * Form koreksi untuk PurchaseReceipt yang sudah POSTED.
     */
    public function editCorrection(PurchaseReceipt $receipt)
    {
        if ($receipt->status !== PurchaseReceipt::STATUS_POSTED) {
            return back()->with('warning', 'Hanya receipt berstatus POSTED yang bisa dikoreksi.');
        }

        $receipt->load(['purchaseOrder.supplier', 'items.orderItem']);

        $po = $receipt->purchaseOrder;
        if (!$po) {
            return back()->with('warning', 'Receipt ini tidak terkait Purchase Order.');
        }

        // Ambil total posted per item (SEMUA receipt POSTED)
        $poiIds = $receipt->items->pluck('purchase_order_item_id')->all();

        $postedTotals = DB::table('purchase_receipt_items as pri')
            ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
            ->where('pr.status', PurchaseReceipt::STATUS_POSTED)
            ->whereIn('pri.purchase_order_item_id', $poiIds)
            ->groupBy('pri.purchase_order_item_id')
            ->selectRaw('pri.purchase_order_item_id, SUM(pri.received_quantity) AS total_posted')
            ->pluck('total_posted', 'purchase_order_item_id');

        // Tambahkan info order & batas maksimum per item
        $receipt->items->transform(function ($item) use ($postedTotals) {
            $ordered        = (float) optional($item->orderItem)->ordered_quantity;
            $totalPostedAll = (float) ($postedTotals[$item->purchase_order_item_id] ?? 0);
            $current        = (float) $item->received_quantity;
            $postedOther    = $totalPostedAll - $current;
            $maxForThis     = max(0, $ordered - $postedOther);

            $item->ordered_quantity      = $ordered;
            $item->total_posted_all      = $totalPostedAll;
            $item->max_quantity_editable = $maxForThis;

            return $item;
        });

        return view('admin.receipts.correction', [
            'receipt' => $receipt,
            'po'      => $po,
        ]);
    }

    /**
     * Simpan koreksi untuk PurchaseReceipt POSTED.
     *
     * - Update qty di PurchaseReceiptItem
     * - Sesuaikan stok (Stock + StockMovement + StockHistory)
     * - Recalc actual_arrived_quantity + is_completed
     * - Simpan alasan koreksi ke notes receipt
     */
    public function updateCorrection(Request $request, PurchaseReceipt $receipt)
    {
        if ($receipt->status !== PurchaseReceipt::STATUS_POSTED) {
            return back()->with('warning', 'Hanya receipt berstatus POSTED yang bisa dikoreksi.');
        }

        $data = $request->validate([
            'items'                     => ['required', 'array'],
            'items.*.received_quantity' => ['required', 'numeric', 'min:0'],
            'reason'                    => ['required', 'string', 'max:500'],
        ]);

        $reason = $data['reason'];

        DB::transaction(function () use ($receipt, $data, $reason) {
            // Lock receipt + relasi penting
            $receipt = PurchaseReceipt::with(['items.orderItem', 'purchaseOrder.items'])
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($receipt->status !== PurchaseReceipt::STATUS_POSTED) {
                throw ValidationException::withMessages([
                    'receipt' => 'Receipt sudah tidak berstatus POSTED.',
                ]);
            }

            $po = $receipt->purchaseOrder;
            if (!$po) {
                throw ValidationException::withMessages([
                    'receipt' => 'Receipt ini tidak terkait Purchase Order.',
                ]);
            }

            // Lock PO untuk hindari race
            DB::table('purchase_orders')->where('id', $po->id)->lockForUpdate()->value('id');

            $itemsInput = $data['items']; // key = receipt_item_id

            // Validasi id item: harus milik receipt ini
            $validIds = $receipt->items->pluck('id')->all();
            foreach ($itemsInput as $idKey => $row) {
                $idInt = (int) $idKey;
                if (!in_array($idInt, $validIds, true)) {
                    throw ValidationException::withMessages([
                        'items' => "Item receipt #{$idInt} tidak valid.",
                    ]);
                }
            }

            // Total posted dari receipt lain (SELURUH receipt POSTED, KECUALI receipt ini)
            $poiIds = $receipt->items->pluck('purchase_order_item_id')->all();

            $postedOther = DB::table('purchase_receipt_items as pri')
                ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                ->where('pr.status', PurchaseReceipt::STATUS_POSTED)
                ->whereIn('pri.purchase_order_item_id', $poiIds)
                ->where('pri.purchase_receipt_id', '!=', $receipt->id)
                ->groupBy('pri.purchase_order_item_id')
                ->selectRaw('pri.purchase_order_item_id, SUM(pri.received_quantity) AS total_posted')
                ->pluck('total_posted', 'purchase_order_item_id');

            $supplierId = $po->supplier_id;
            $poId       = $po->id;
            $poNumber   = $po->po_number;

            // Proses tiap item yang dikoreksi
            foreach ($itemsInput as $receiptItemId => $row) {
                /** @var \App\Models\PurchaseReceiptItem|null $rcItem */
                $rcItem = $receipt->items->firstWhere('id', (int) $receiptItemId);
                if (!$rcItem) {
                    continue;
                }

                $oldQty = (float) $rcItem->received_quantity;
                $newQty = (float) $row['received_quantity'];

                // Kalau tidak berubah, lewati
                if (abs($newQty - $oldQty) < 0.0000001) {
                    continue;
                }

                $poi = $rcItem->orderItem;
                if (!$poi) {
                    throw ValidationException::withMessages([
                        'items' => "Order item untuk material '{$rcItem->material_name}' tidak ditemukan.",
                    ]);
                }

                $ordered     = (float) $poi->ordered_quantity;
                $otherPosted = (float) ($postedOther[$poi->id] ?? 0.0);

                // Validasi total jangan melebihi qty PO
                if ($otherPosted + $newQty > $ordered + 0.0000001) {
                    throw ValidationException::withMessages([
                        'items' => "Koreksi untuk '{$poi->material_name}' melebihi qty PO. "
                            . "Ordered: {$ordered}, sudah diterima di receipt lain: {$otherPosted}, "
                            . "qty baru di receipt ini: {$newQty}.",
                    ]);
                }

                $delta = $newQty - $oldQty; // plus = tambah stok, minus = kurangi stok

                // Ambil / buat stok
                $materialName = $rcItem->material_name;
                $unit         = $rcItem->unit;

                $materialCode = $poi && $poi->material_code !== null
                    ? strtoupper(trim((string) $poi->material_code))
                    : null;

                /** @var \App\Models\Stock $stock */
                $stock = Stock::where('purchase_order_id', $poId)
                    ->where('supplier_id', $supplierId)
                    ->where('material_name', $materialName)
                    ->where('unit', $unit)
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {
                    $stock = new Stock();
                    $stock->purchase_order_id = $poId;
                    $stock->supplier_id       = $supplierId;
                    $stock->material_name     = $materialName;
                    $stock->unit              = $unit;
                    $stock->quantity          = 0.0;
                }

                $stockOld = (float) $stock->quantity;
                $stockNew = $stockOld + $delta;

                if ($stockNew < -0.0000001) {
                    throw ValidationException::withMessages([
                        'items' => "Koreksi untuk '{$materialName}' akan membuat stok menjadi negatif. "
                            . "Stok saat ini: {$stockOld}, perubahan: {$delta}.",
                    ]);
                }

                // Update stok
                $stock->material_code = $materialCode;
                $stock->quantity      = $stockNew;
                $stock->save();

                // Movement koreksi (IN/OUT)
                if (abs($delta) > 0.0000001) {
                    StockMovement::create([
                        'stock_id'      => $stock->id,
                        'supplier_id'   => $stock->supplier_id,
                        'material_name' => $stock->material_name,
                        'unit'          => $stock->unit,
                        'direction'     => $delta > 0
                            ? StockMovement::DIR_IN
                            : StockMovement::DIR_OUT,
                        'quantity'      => abs($delta),
                        'notes'         => 'Koreksi receipt ' . ($receipt->receipt_number ?? $receipt->id) . ' — ' . $reason,
                        'po_number'     => $poNumber,
                        'moved_at'      => $receipt->receipt_date?->startOfDay() ?? now(),
                    ]);
                }

                // History stok (receipt_correction)
                StockHistory::recordChange(
                    $stock,
                    $stockOld,
                    $stockNew,
                    'receipt_correction',
                    $reason,
                    Auth::id()
                );

                // Update qty di receipt item
                $rcItem->received_quantity = $newQty;
                $rcItem->save();
            }

            // === Recalc actual_arrived_quantity per PurchaseOrderItem ===
            $poItems = $po->items; // sudah eager loaded

            foreach ($poItems as $poItem) {
                $totalPosted = (float) DB::table('purchase_receipt_items as pri')
                    ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                    ->where('pr.status', PurchaseReceipt::STATUS_POSTED)
                    ->where('pri.purchase_order_item_id', $poItem->id)
                    ->sum('pri.received_quantity');

                PurchaseOrderItem::whereKey($poItem->id)->update([
                    'actual_arrived_quantity' => $totalPosted,
                ]);
            }

            // === Recalc is_completed di PO ===
            $po = $po->fresh('items');

            $postedPerItem = DB::table('purchase_receipt_items as pri')
                ->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                ->where('pr.status', PurchaseReceipt::STATUS_POSTED)
                ->whereIn('pri.purchase_order_item_id', $po->items->pluck('id'))
                ->groupBy('pri.purchase_order_item_id')
                ->selectRaw('pri.purchase_order_item_id, SUM(pri.received_quantity) AS received_total')
                ->pluck('received_total', 'purchase_order_item_id');

            $allFulfilled = $po->items->every(function ($item) use ($postedPerItem) {
                $ordered  = (float) $item->ordered_quantity;
                $received = (float) ($postedPerItem[$item->id] ?? 0);
                return $received + 0.0000001 >= $ordered;
            });

            $po->update([
                'is_completed' => $allFulfilled,
            ]);

            // === Simpan alasan koreksi ke receipt (append ke notes) + edited_at ===
            $user  = Auth::user();
            $stamp = now();

            $line = 'Koreksi ' . $stamp->format('d/m/Y H:i')
                . ($user ? ' oleh ' . $user->name : '')
                . ': ' . $reason;

            $existingNotes   = trim((string) $receipt->notes);
            $receipt->notes  = $existingNotes
                ? $existingNotes . "\n" . $line
                : $line;

            $receipt->edited_at = $stamp;
            $receipt->save();
        });

        return redirect()
            ->route('admin.purchase-orders.show', $receipt->purchase_order_id)
            ->with('success', 'Koreksi penerimaan berhasil disimpan. Stok & status PO sudah diperbarui.');
    }
}
