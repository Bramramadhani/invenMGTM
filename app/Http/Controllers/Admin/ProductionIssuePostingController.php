<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductionIssue;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductionIssuePostingController extends Controller
{
    public function post(ProductionIssue $issue)
    {
        return DB::transaction(function () use ($issue) {
            // Lock dokumen + items supaya anti double-click
            $issue = ProductionIssue::with('items')
                ->whereKey($issue->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($issue->status !== 'draft') {
                return back()->with('warning', 'Dokumen sudah diposting/void.');
            }

            if ($issue->items->isEmpty()) {
                return back()->with('warning', 'Tidak bisa posting: tidak ada item pada dokumen ini.');
            }

            // ---------- VALIDASI stok (per-PO dengan stock_id) ----------
            foreach ($issue->items as $row) {
                if (empty($row->stock_id)) {
                    return back()->with('warning', "Item {$row->material_name} belum terhubung ke baris stok per-PO (stock_id kosong).");
                }

                // Lock baris stok tepatnya
                $stock = Stock::lockForUpdate()->find($row->stock_id);
                if (!$stock) {
                    return back()->with('warning', "Baris stok untuk {$row->material_name} tidak ditemukan (stock_id {$row->stock_id}).");
                }

                $available = (float) $stock->quantity;
                $qtyOut    = (float) $row->quantity;

                if ($qtyOut <= 0) {
                    return back()->with('warning', "Qty untuk {$row->material_name} tidak valid.");
                }
                if ($available < $qtyOut) {
                    return back()->with('warning', "Stok tidak cukup untuk {$stock->material_name} (PO {$stock->last_po_number}). Tersedia: {$available}, diminta: {$qtyOut}.");
                }
            }

            // ---------- EKSEKUSI: kurangi stok + movement OUT ----------
            $now = now(); // satu timestamp konsisten
            foreach ($issue->items as $row) {
                $stock = Stock::lockForUpdate()->findOrFail($row->stock_id);
                $qtyOut = (float) $row->quantity;

                // Kurangi stok (tetap aman karena kita lock row)
                $stock->decrement('quantity', $qtyOut);

                // Catat movement OUT pakai helper (per-PO)
                StockMovement::recordOut(
                    (int) $stock->id,                  // $stockId
                    (int) $stock->supplier_id,         // $supplierId
                    (string) $stock->material_name,    // $material
                    (string) $stock->unit,             // $unit
                    (float) $qtyOut,                   // $qty
                    (string) $stock->last_po_number,   // $poNumber
                    $row->notes,                       // $notes (ambil dari item issue)
                    'production_issues',               // $refType
                    (int) $issue->id,                  // $refId
                    $now                               // $movedAt
                );
            }

            // Tandai dokumen posted
            $issue->update([
                'status'    => 'posted',
                'posted_at' => $now,
                'posted_by' => Auth::id(),
            ]);

            return back()->with('success', 'Pengeluaran berhasil diposting. Stok berkurang per-PO.');
        });
    }
}
