<?php

namespace App\Http\Controllers\Admin;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Http\Controllers\Controller;

class TransactionController extends Controller
{
    public function product()
    {
        $transactions = Transaction::with('details.product')->latest()->paginate(10);
        $grandQuantity = TransactionDetail::sum('quantity');

        return view('admin.transaction.product', compact('transactions', 'grandQuantity'));
    }
    
   
}
