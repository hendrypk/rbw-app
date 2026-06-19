<?php
namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuController extends Controller
{
    public function index()
    {
        return Inertia::render('menus/Index');
    }

    // Menampilkan halaman form tambah
    public function create()
    {
        return Inertia::render('menus/Create');
    }

    // Menampilkan halaman form edit
    public function edit(Menu $menu)
    {
        return Inertia::render('menus/Edit', [
            'menu' => $menu
        ]);
    }
}