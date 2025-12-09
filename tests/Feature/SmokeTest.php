<?php

namespace Tests\Feature;

use App\Models\{Buyer, Supplier, Stock, StockHistory, StockMovement, User, PurchaseOrder, PurchaseReceipt, PurchaseReceiptItem, Order};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Create test user for authentication
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test 1: FOB Stock Creation & Update
     */
    public function test_fob_stock_creation_and_update()
    {
        $buyer = Buyer::create([
            'name' => 'Test Buyer FOB',
            'code' => 'FOB001',
            'contact_name' => 'Contact',
            'email' => 'test@buyer.com',
            'phone' => '08123456789',
            'address' => 'Test Address',
        ]);

        // Create FOB stock
        $stock = Stock::create([
            'buyer_id' => $buyer->id,
            'material_name' => 'Fabric A',
            'material_code' => 'FAB001',
            'unit' => 'meter',
            'quantity' => 100,
            'supplier_id' => null,
            'purchase_order_id' => null,
        ]);

        $this->assertNotNull($stock->id);
        $this->assertEquals(100, $stock->quantity);
        $this->assertEquals($buyer->id, $stock->buyer_id);
        $this->assertNull($stock->supplier_id);

        // Record FOB create history
        StockHistory::recordChange(
            stock: $stock,
            old_quantity: 0,
            new_quantity: 100,
            type: StockHistory::TYPE_FOB_CREATE,
            reason: 'Initial FOB Purchase',
            created_by: $this->user->id,
            unit_price: 50000,
            total_price: 5000000,
        );

        $history = StockHistory::where('stock_id', $stock->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals(StockHistory::TYPE_FOB_CREATE, $history->type);
        $this->assertEquals(100, $history->new_quantity);
        $this->assertEquals(50000, $history->unit_price);

        // Update FOB stock quantity
        $oldQty = $stock->quantity;
        $stock->quantity = 80;
        $stock->save();

        StockHistory::recordChange(
            stock: $stock,
            old_quantity: $oldQty,
            new_quantity: 80,
            type: StockHistory::TYPE_FOB_UPDATE,
            reason: 'Correction - Qty Adjustment',
            created_by: $this->user->id,
        );

        StockMovement::create([
            'stock_id' => $stock->id,
            'type' => 'correction',
            'quantity_in' => 0,
            'quantity_out' => 20,
        ]);

        $movements = StockMovement::where('stock_id', $stock->id)->get();
        $this->assertCount(1, $movements);
        $this->assertEquals(20, $movements->first()->quantity_out);

        echo "\n✅ Test 1 PASSED: FOB Stock Creation & Update\n";
    }

    /**
     * Test 2: PO Receipt Posting & Stock Update
     */
    public function test_po_receipt_posting_and_stock_update()
    {
        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'code' => 'SUP001',
            'contact_name' => 'Contact',
            'phone' => '08123456789',
            'email' => 'supplier@test.com',
            'address' => 'Supplier Address',
        ]);

        // Create PO
        $po = PurchaseOrder::create([
            'po_number' => 'PO-' . date('YmdHis'),
            'supplier_id' => $supplier->id,
            'po_date' => now(),
            'delivery_date' => now()->addDays(10),
            'status' => 'pending',
            'is_completed' => false,
        ]);

        // Create PO item
        $poItem = \App\Models\PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'material_name' => 'Material X',
            'material_code' => 'MAT001',
            'unit' => 'kg',
            'quantity' => 500,
            'unit_price' => 10000,
        ]);

        // Create receipt
        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'REC-' . date('YmdHis'),
            'purchase_order_id' => $po->id,
            'status' => 'draft',
        ]);

        // Create receipt item
        $receiptItem = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => 300,
        ]);

        // Post receipt (simulating PurchaseReceiptPostingController logic)
        $receipt->update(['status' => 'posted']);

        // Create or update stock
        $stock = Stock::firstOrCreate(
            [
                'supplier_id' => $supplier->id,
                'material_name' => 'Material X',
                'unit' => 'kg',
                'purchase_order_id' => $po->id,
            ],
            [
                'quantity' => 0,
                'material_code' => 'MAT001',
            ]
        );

        $stock->quantity += 300;
        $stock->save();

        // Record stock history
        StockHistory::recordChange(
            stock: $stock,
            old_quantity: 0,
            new_quantity: 300,
            type: StockHistory::TYPE_PO_RECEIVE,
            reason: 'Receipt ' . $receipt->receipt_number,
            created_by: $this->user->id,
        );

        // Create stock movement
        StockMovement::create([
            'stock_id' => $stock->id,
            'purchase_order_id' => $po->id,
            'type' => 'received',
            'quantity_in' => 300,
            'quantity_out' => 0,
        ]);

        // Verify
        $this->assertEquals('posted', $receipt->status);
        $this->assertEquals(300, $stock->quantity);
        
        $history = StockHistory::where('stock_id', $stock->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals(StockHistory::TYPE_PO_RECEIVE, $history->type);

        $movement = StockMovement::where('stock_id', $stock->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(300, $movement->quantity_in);

        echo "\n✅ Test 2 PASSED: PO Receipt Posting & Stock Update\n";
    }

    /**
     * Test 3: Order Creation (FOB Mode & PO Mode)
     */
    public function test_order_creation_fob_and_po_mode()
    {
        // ===== FOB Mode =====
        $buyer = Buyer::create([
            'name' => 'Test Buyer Order',
            'code' => 'ORD-BUYER-001',
            'contact_name' => 'Contact',
            'email' => 'buyer@test.com',
            'phone' => '08123456789',
            'address' => 'Buyer Address',
        ]);

        // Create FOB stock
        $fobStock = Stock::create([
            'buyer_id' => $buyer->id,
            'material_name' => 'Cotton Fabric',
            'material_code' => 'COT001',
            'unit' => 'meter',
            'quantity' => 200,
            'supplier_id' => null,
            'purchase_order_id' => null,
        ]);

        // Create order from FOB
        $orderFob = Order::create([
            'order_number' => 'ORD-FOB-' . date('YmdHis'),
            'source_type' => 'fob',
            'buyer_id' => $buyer->id,
            'supplier_id' => null,
            'po_id' => null,
            'order_date' => now(),
            'status' => 'draft',
        ]);

        $this->assertEquals('fob', $orderFob->source_type);
        $this->assertEquals($buyer->id, $orderFob->buyer_id);

        // ===== PO Mode =====
        $supplier = Supplier::create([
            'name' => 'Test Supplier Order',
            'code' => 'SUP-ORD-001',
            'contact_name' => 'Contact',
            'phone' => '08123456789',
            'email' => 'supplier@test.com',
            'address' => 'Supplier Address',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-ORD-' . date('YmdHis'),
            'supplier_id' => $supplier->id,
            'po_date' => now(),
            'delivery_date' => now()->addDays(10),
            'status' => 'pending',
            'is_completed' => false,
        ]);

        // Create order from PO
        $orderPo = Order::create([
            'order_number' => 'ORD-PO-' . date('YmdHis'),
            'source_type' => 'po',
            'buyer_id' => null,
            'supplier_id' => $supplier->id,
            'po_id' => $po->id,
            'order_date' => now(),
            'status' => 'draft',
        ]);

        $this->assertEquals('po', $orderPo->source_type);
        $this->assertEquals($po->id, $orderPo->po_id);

        echo "\n✅ Test 3 PASSED: Order Creation (FOB & PO Mode)\n";
    }

    /**
     * Test 4: Stock History & Movement Consistency
     */
    public function test_stock_history_and_movement_consistency()
    {
        $supplier = Supplier::create([
            'name' => 'Consistency Test Supplier',
            'code' => 'CONS-SUP-001',
            'contact_name' => 'Contact',
            'phone' => '08123456789',
            'email' => 'test@test.com',
            'address' => 'Address',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-CONS-' . date('YmdHis'),
            'supplier_id' => $supplier->id,
            'po_date' => now(),
            'delivery_date' => now()->addDays(10),
            'status' => 'pending',
            'is_completed' => false,
        ]);

        $stock = Stock::create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'material_name' => 'Test Material',
            'material_code' => 'TEST001',
            'unit' => 'pcs',
            'quantity' => 0,
        ]);

        // Record multiple history entries
        for ($i = 1; $i <= 3; $i++) {
            StockHistory::recordChange(
                stock: $stock,
                old_quantity: ($i - 1) * 100,
                new_quantity: $i * 100,
                type: StockHistory::TYPE_PO_RECEIVE,
                reason: "Receipt batch $i",
                created_by: $this->user->id,
            );

            StockMovement::create([
                'stock_id' => $stock->id,
                'purchase_order_id' => $po->id,
                'type' => 'received',
                'quantity_in' => 100,
                'quantity_out' => 0,
            ]);
        }

        $histories = StockHistory::where('stock_id', $stock->id)->get();
        $movements = StockMovement::where('stock_id', $stock->id)->get();

        $this->assertCount(3, $histories);
        $this->assertCount(3, $movements);
        
        $totalIn = $movements->sum('quantity_in');
        $this->assertEquals(300, $totalIn);
        $this->assertEquals(300, $stock->fresh()->quantity);

        echo "\n✅ Test 4 PASSED: Stock History & Movement Consistency\n";
    }

    /**
     * Run all smoke tests
     */
    public function test_smoke_all()
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "SMOKE TEST SUITE - invenMGTM System\n";
        echo str_repeat("=", 60) . "\n";

        $this->test_fob_stock_creation_and_update();
        $this->test_po_receipt_posting_and_stock_update();
        $this->test_order_creation_fob_and_po_mode();
        $this->test_stock_history_and_movement_consistency();

        echo "\n" . str_repeat("=", 60) . "\n";
        echo "✅ ALL SMOKE TESTS PASSED!\n";
        echo str_repeat("=", 60) . "\n";
    }
}
