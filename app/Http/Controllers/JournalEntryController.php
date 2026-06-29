<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class JournalEntryController extends Controller
{
    public function index()
    {
        return Inertia::render('finance/journals/Index');
    }
}
