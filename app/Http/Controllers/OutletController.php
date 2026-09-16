<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class OutletController extends Controller
{
    public function index()
    {
        return Inertia::render('outlet/Index');
    }
}
