<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index()
    {
        return Inertia::render('suppliers/Index');
    }
}
