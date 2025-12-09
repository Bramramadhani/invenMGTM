<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\{
    DashboardController,
    SupplierController,
    StockController,
    TransactionController,
    SettingController,
    PurchaseOrderController,
    PurchaseReceiptController,
    PurchaseOrderRejectController,
    PurchaseReceiptPostingController,
    PurchaseReceiptDeleteController,
    PermissionController,
    RoleController,
    ProductionIssueController,
    ProductionIssuePostingController,
    OrderController,
    OutgoingController,
    ReportController,
    BuyerController,
    FobStockController
};

Route::get('/', fn () => redirect('login'));

// ==============================
// AUTH (tanpa Auth::routes())
// ==============================

// Login & Logout
Route::get('/login',  [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// Password Reset (Forgot Password)
Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])
    ->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])
    ->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])
    ->name('password.update');

// ==============================
// ADMIN AREA
// ==============================
Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // DASHBOARD
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ==============================
        // MASTER DATA
        // ==============================
        Route::resource('/supplier', SupplierController::class)->except(['show']);

        // Buyer (FOB)
        Route::resource('/buyers', BuyerController::class)->except(['show']);

        // ==============================
        // STOK
        // ==============================

        // Stok normal (dari supplier / PO)
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');

        // Stok FOB (berdasarkan Buyer, tanpa PO)
        Route::resource('/fob-stocks', FobStockController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
            ->names('fob-stocks');

        // Detail + history stok FOB
        Route::get('/fob-stocks/{fob_stock}/history', [FobStockController::class, 'history'])
            ->name('fob-stocks.history');

        // Laporan Pembelian FOB (per tanggal / per bulan)
        Route::get(
            '/fob-stocks/purchase-report',
            [FobStockController::class, 'purchaseReport']
        )->name('fob-stocks.purchase-report');

        // Export Laporan Pembelian FOB ke Excel
        Route::get(
            '/fob-stocks/purchase-report/export',
            [FobStockController::class, 'exportPurchaseReport']
        )->name('fob-stocks.purchase-report.export');

        // ==============================
        // PERMISSION & ROLE
        // ==============================
        Route::resource('/transaction', TransactionController::class);
        Route::resource('/permission',  PermissionController::class)->except(['show']);
        Route::resource('/role',        RoleController::class)->names('role')->except(['show']);

        // USER SETTING
        Route::get('/setting',        [SettingController::class, 'index'])->name('setting.index');
        Route::put('/setting/{user}', [SettingController::class, 'update'])->name('setting.update');

        // TRANSACTION PRODUCT (lama)
        Route::get(
            '/transaction-product',
            [TransactionController::class, 'product']
        )->name('transaction.product');

        // ==============================
        // PURCHASE ORDERS
        // ==============================
        Route::resource('/purchase-orders', PurchaseOrderController::class);

        // === Barang Reject ===
        Route::post(
            '/purchase-orders/{purchaseOrder}/reject',
            [PurchaseOrderRejectController::class, 'store']
        )->name('purchase-orders.reject');

        // === Purchase Receipt (Parsial) ===
        Route::get(
            'purchase-orders/{purchaseOrder}/receipts/create',
            [PurchaseReceiptController::class, 'create']
        )->name('receipts.create');

        Route::post(
            'purchase-orders/{purchaseOrder}/receipts',
            [PurchaseReceiptController::class, 'store']
        )->name('receipts.store');

        Route::post(
            'receipts/{receipt}/post',
            [PurchaseReceiptPostingController::class, 'post']
        )->name('receipts.post');

        Route::delete(
            'receipts/{receipt}',
            [PurchaseReceiptDeleteController::class, 'delete']
        )->name('receipts.delete');

        // === Receipt PDF ===
        Route::get(
            'receipts/{receipt}/pdf',
            [PurchaseReceiptController::class, 'pdf']
        )->name('receipts.pdf');

        Route::get(
            'purchase-orders/{purchaseOrder}/receipts/pdf-merged',
            [PurchaseReceiptController::class, 'pdfMerged']
        )->name('receipts.pdf-merged');

        // === Koreksi Receipt (POSTED) ===
        Route::get(
            'receipts/{receipt}/correction',
            [PurchaseReceiptController::class, 'editCorrection']
        )->name('receipts.correction.edit');

        Route::put(
            'receipts/{receipt}/correction',
            [PurchaseReceiptController::class, 'updateCorrection']
        )->name('receipts.correction.update');

        // ==============================
        // ORDERS (Permintaan Barang)
        // ==============================

        // AJAX: daftar PO milik supplier (mode=po → hanya yg punya stok normal; mode=fob → semua PO)
        Route::get(
            '/orders/supplier/{supplier}/pos',
            [OrderController::class, 'supplierPOs']
        )->name('orders.supplier-pos');

        // AJAX: stok per-PO (stok normal)
        Route::get(
            '/orders/po/{purchaseOrder}/stocks',
            [OrderController::class, 'poStocks']
        )->name('orders.po-stocks');

        // AJAX: stok FOB per Buyer
        Route::get(
            '/orders/buyer/{buyer}/stocks',
            [OrderController::class, 'buyerStocks']
        )->name('orders.buyer-stocks');

        // AJAX: daftar style per PO
        Route::get(
            '/orders/po/{purchaseOrder}/styles',
            [OrderController::class, 'poStyles']
        )->name('orders.po-styles');

        // Resource utama permintaan barang
        Route::resource('/orders', OrderController::class)
            ->only(['index', 'show', 'create', 'store', 'edit', 'update', 'destroy'])
            ->names('orders');

        // Receipt PDF untuk Permintaan Barang
        Route::get(
            '/orders/{order}/receipt-pdf',
            [OrderController::class, 'receiptPdf']
        )->name('orders.receipt-pdf');

        // ==============================
        // PRODUCTION ISSUE
        // ==============================
        Route::resource('/issues', ProductionIssueController::class)->only(['show']);

        Route::post(
            'issues/{issue}/post',
            [ProductionIssuePostingController::class, 'post']
        )->name('issues.post');

        Route::get(
            'issues/{issue}/pdf',
            [ProductionIssueController::class, 'pdf']
        )->name('issues.pdf');

        // ==============================
        // OUTGOING
        // ==============================
        Route::get('/outgoing',  [OutgoingController::class, 'index'])->name('outgoing.index');
        Route::get('/outgoings', [OutgoingController::class, 'index'])->name('outgoings.index'); // alias

        // ==============================
        // REPORTS
        // ==============================
        Route::get('/reports',        [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

// Redirect /home bawaan auth ke dashboard admin
Route::get('/home', fn () => redirect()->route('admin.dashboard'))->middleware('auth');
