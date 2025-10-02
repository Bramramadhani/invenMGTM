<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReceipt;
use Illuminate\Support\Facades\DB;

class PurchaseReceiptDeleteController extends Controller
{
    public function delete(PurchaseReceipt $receipt)
    {
        // Hanya boleh menghapus yang draft
        if ($receipt->status !== 'draft') {
            return back()->with('warning', 'Hanya receipt DRAFT yang bisa dibatalkan.');
        }

        try {
            DB::transaction(function () use ($receipt) {
                // Hapus items terlebih dahulu (foreign key)
                $receipt->items()->delete();
                // Hapus receipt
                $receipt->delete();
            });

            return back()->with('success', 'Receipt berhasil dibatalkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membatalkan receipt: ' . $e->getMessage());
        }
    }
}