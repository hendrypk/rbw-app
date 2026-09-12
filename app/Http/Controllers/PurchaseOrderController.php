<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return inertia('purchase/Index');
    }

    public function show()
    {
        return inertia('purchase/Show');
    }
}
