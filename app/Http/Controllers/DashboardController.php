<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role === 'admin' ? 'admin.dashboard' : 'staff.dashboard');
    }

    public function show(Request $request): View
    {
        return view('dashboard', ['isAdmin' => $request->user()->role === 'admin']);
    }
}
