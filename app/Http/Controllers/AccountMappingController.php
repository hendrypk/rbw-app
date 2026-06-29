<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountMappingController extends Controller
{
    public function index()
    {
        return Inertia::render('finance/mappings/Index');
    }
}
