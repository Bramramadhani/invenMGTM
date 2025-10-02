<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\{
    DashboardController,
    SupplierController,
    StockController,
    TransactionController,
    SettingController,
    PurchaseOrderController,
    PurchaseReceiptController,
    PermissionController,
    RoleController,
    ProductionIssueController,
    OrderController
};
use App\Http\Controllers\Admin\PurchaseReceiptPostingController;
use App\Http\Controllers\Admin\PurchaseReceiptDeleteController;
use App\Http\Controllers\Admin\ProductionIssuePostingController;
use App\Http\Controllers\Admin\OutgoingController;
use App\Http\Controllers\Admin\ReportController;

Route::get('/', fn () => redirect('login'));

Auth::routes();
Route::get('/login',  [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout',[LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // DASHBOARD
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // MASTER & LAINNYA
        Route::resource('/supplier', SupplierController::class)->except(['show']);
        Route::resource('/stock',    StockController::class)->only(['index','update']);
        Route::resource('/transaction', TransactionController::class);
        Route::resource('/permission',  PermissionController::class)->except(['show']);
        Route::resource('/role',        RoleController::class)->names('role')->except(['show']);

        Route::get('/setting',        [SettingController::class, 'index'])->name('setting.index');
        Route::put('/setting/{user}', [SettingController::class, 'update'])->name('setting.update');

        // (lama, kalau masih dipakai)
        Route::get('/transaction-product',[TransactionController::class, 'product'])->name('transaction.product');

        // PURCHASE ORDERS
        Route::resource('/purchase-orders', PurchaseOrderController::class);

        // RECEIPT IN (Parsial)
        Route::get('purchase-orders/{purchaseOrder}/receipts/create', [PurchaseReceiptController::class,'create'])
            ->name('receipts.create');
        Route::post('purchase-orders/{purchaseOrder}/receipts', [PurchaseReceiptController::class,'store'])
            ->name('receipts.store');
        Route::post('receipts/{receipt}/post', [PurchaseReceiptPostingController::class, 'post'])
            ->name('receipts.post');
        Route::delete('receipts/{receipt}', [PurchaseReceiptDeleteController::class, 'delete'])
            ->name('receipts.delete');

        // RECEIPT PDF (single receipt & merged)
        Route::get('receipts/{receipt}/pdf', [PurchaseReceiptController::class, 'pdf'])
            ->name('receipts.pdf');
        Route::get('purchase-orders/{purchaseOrder}/receipts/pdf-merged', [PurchaseReceiptController::class,'pdfMerged'])
            ->name('receipts.pdf-merged'); // satu tombol download semua receipt POSTED pada PO

        // ORDERS (AJAX helper + resource)
        Route::get('/orders/supplier/{supplier}/pos', [OrderController::class, 'supplierPOs'])
            ->name('orders.supplier-pos');
        Route::get('/orders/po/{purchaseOrder}/stocks', [OrderController::class, 'poStocks'])
            ->name('orders.po-stocks');

        Route::resource('/orders', OrderController::class)
            ->only(['index','show','create','store','update','destroy'])
            ->names('orders');

        // >>> NEW: Receipt PDF untuk Permintaan Barang (Order)
        Route::get('/orders/{order}/receipt-pdf', [OrderController::class, 'receiptPdf'])
            ->name('orders.receipt-pdf');

        // PRODUCTION ISSUE (detail & posting OUT)
        Route::resource('/issues', ProductionIssueController::class)->only(['show']);
        Route::post('issues/{issue}/post', [ProductionIssuePostingController::class, 'post'])
            ->name('issues.post');

        // (Opsional) PDF untuk Production Issue
        Route::get('issues/{issue}/pdf', [ProductionIssueController::class, 'pdf'])
            ->name('issues.pdf');

        // OUTGOING
        Route::get('/outgoing',  [OutgoingController::class, 'index'])->name('outgoing.index');
        Route::get('/outgoings', [OutgoingController::class, 'index'])->name('outgoings.index'); // alias

        // REPORTS
        Route::get('/reports',        [ReportController::class,  'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class,  'export'])->name('reports.export');
    });

// Redirect /home bawaan auth
Route::get('/home', fn () => redirect()->route('admin.dashboard'))->middleware('auth');
