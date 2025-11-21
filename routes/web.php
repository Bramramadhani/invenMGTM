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
    ReportController
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

        // MASTER DATA
        Route::resource('/supplier', SupplierController::class)->except(['show']);
        Route::resource('/stock',    StockController::class)->only(['index', 'update']);
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
        // Single reject (per item)
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

        // ==============================
        // ORDERS
        // ==============================
        Route::get(
            '/orders/supplier/{supplier}/pos',
            [OrderController::class, 'supplierPOs']
        )->name('orders.supplier-pos');

        Route::get(
            '/orders/po/{purchaseOrder}/stocks',
            [OrderController::class, 'poStocks']
        )->name('orders.po-stocks');

        // NEW: AJAX daftar style per PO
        Route::get(
            '/orders/po/{purchaseOrder}/styles',
            [OrderController::class, 'poStyles']
        )->name('orders.po-styles');

        Route::resource('/orders', OrderController::class)
            ->only(['index', 'show', 'create', 'store', 'update', 'destroy'])
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

