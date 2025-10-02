<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductionIssue;
use Barryvdh\DomPDF\Facade\Pdf as PDF; // <-- penting untuk generate PDF

class ProductionIssueController extends Controller
{
    /**
     * Tampilkan detail Permintaan Barang.
     */
    public function show(ProductionIssue $issue)
    {
        // Preload semua relasi yang dibutuhkan di halaman & modal
        $issue->load([
            'items.supplier', // supplier per item
            'requester',      // user yang meminta (opsional)
            'poster',         // user yang mem-posting (opsional)
        ]);

        return view('admin.issues.show', compact('issue'));
    }

    /**
     * Unduh bukti Permintaan Barang (PDF) untuk dicetak dan ditandatangani.
     */
    public function pdf(ProductionIssue $issue)
    {
        // Preload relasi utk PDF
        $issue->load([
            'items.supplier',
            'requester',
            'poster',
        ]);

        $filename = 'Permintaan_Barang_' . ($issue->issue_number ?: $issue->id) . '.pdf';

        return PDF::loadView('admin.issues.pdf', [
                'issue' => $issue,
            ])
            ->setPaper('a4', 'portrait')
            ->download($filename); // pakai ->stream($filename) jika mau preview
    }
}
