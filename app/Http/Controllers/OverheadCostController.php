<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class OverheadCostController extends Controller
{
    public function index()
    {
        return Inertia::render('overhead/Index');
    }
}
