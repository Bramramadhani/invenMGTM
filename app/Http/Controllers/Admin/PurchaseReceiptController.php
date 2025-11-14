<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseOrderReject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class PurchaseReceiptController extends Controller
{
    public function create(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['items' => function ($q) {
            $q->withSum(['receiptItems as received_total' => function ($r) {
                $r->whereHas('receipt', fn($rec) => $rec->where('status', PurchaseReceipt::STATUS_POSTED));
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
            $qty = isset($row['received_quantity']) ? (float)$row['received_quantity'] : 0.0;
            if ($qty > 0) {
                $filtered[] = [
                    'purchase_order_item_id' => (int)$row['purchase_order_item_id'],
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

        // Ambil total penerimaan sebelumnya
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
            $id = $row['purchase_order_item_id'];
            $qty = (float)$row['received_quantity'];

            $ord = (float)($ordered[$id] ?? 0);
            $recSoFar = (float)($posted[$id] ?? 0);
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
            DB::table('purchase_orders')->where('id', $purchaseOrder->id)->lockForUpdate()->value('id');

            if (!empty($data['idempotency_token'])) {
                $existing = PurchaseReceipt::where('purchase_order_id', $purchaseOrder->id)
                    ->where('status', PurchaseReceipt::STATUS_DRAFT)
                    ->where('idempotency_token', $data['idempotency_token'])
                    ->first();
                if ($existing) return;
            }

            // Auto increment RC-XXX-YYY
            $maxSuffix = (int)DB::table('purchase_receipts')
                ->where('purchase_order_id', $purchaseOrder->id)
                ->lockForUpdate()
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED)), 0) as max_suf")
                ->value('max_suf');

            $next   = $maxSuffix + 1;
            $number = 'RC-' . $purchaseOrder->po_number . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);

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
                    'received_quantity'      => (float)$row['received_quantity'],
                    'notes'                  => $row['notes'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('admin.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Penerimaan parsial tersimpan sebagai DRAFT.');
    }

    /**
     * Revisi PDF (menambahkan catatan reject ke kolom Catatan)
     */
    public function pdf(PurchaseReceipt $receipt)
    {
        $receipt->load(['purchaseOrder.supplier', 'items']);

        // 🔹 Gabungkan catatan reject ke notes
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
     * Revisi PDF Merged (gabungkan catatan reject per material)
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
                $key = (string)$it->purchase_order_item_id;
                if (!isset($agg[$key])) {
                    $agg[$key] = [
                        'material_name' => $it->material_name,
                        'unit'          => $it->unit,
                        'quantity'      => 0.0,
                        'notes'         => [],
                    ];
                }

                $agg[$key]['quantity'] += (float)$it->received_quantity;
                if (!empty($it->notes)) $agg[$key]['notes'][] = $it->notes;

                // 🔹 Tambahkan catatan reject
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
            $row['notes'] = empty($row['notes']) ? '' : implode("\n", array_unique($row['notes']));
            return (object)$row;
        })->sortBy('material_name')->values();

        $datesSummary = $receipts->groupBy(fn($r) => optional($r->receipt_date)->format('Y-m-d'))
            ->map(fn($group, $ymd) => [
                'date' => $ymd,
                'receipts' => $group->count(),
                'rows' => $group->sum(fn($r) => $r->items->count()),
                'qty' => $group->reduce(fn($s, $r) => $s + (float)$r->items->sum('received_quantity'), 0),
            ])->values();

        $meta = (object)[
            'receipt_number' => 'RC-' . $purchaseOrder->po_number . '-MERGED',
            'status' => PurchaseReceipt::STATUS_POSTED,
            'receipt_date' => optional($receipts->min('receipt_date')),
            'posted_at' => optional($receipts->max('posted_at')),
            'purchaseOrder' => $purchaseOrder->load('supplier'),
            'notes' => 'Gabungan semua receipt yang sudah diposting untuk PO ini.',
        ];

        $pdf = PDF::loadView('admin.receipts.pdf_merged', [
            'po' => $purchaseOrder,
            'receipt' => $meta,
            'items' => $items,
            'datesSummary' => $datesSummary,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('RC-' . $purchaseOrder->po_number . '-MERGED.pdf');
    }
}
