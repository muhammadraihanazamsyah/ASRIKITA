<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\SaleController;
use App\Support\MasterData;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});

Route::middleware(['auth', 'active', 'role:admin'])->prefix('admin/master')->name('admin.master.')->group(function () {
    Route::get('/{resource}', [MasterDataController::class, 'index'])->whereIn('resource', MasterData::RESOURCES)->name('index');
    Route::get('/{resource}/tambah', [MasterDataController::class, 'create'])->whereIn('resource', MasterData::RESOURCES)->name('create');
    Route::post('/{resource}', [MasterDataController::class, 'store'])->whereIn('resource', MasterData::RESOURCES)->name('store');
    Route::get('/{resource}/{record}/ubah', [MasterDataController::class, 'edit'])->whereIn('resource', MasterData::RESOURCES)->whereNumber('record')->name('edit');
    Route::put('/{resource}/{record}', [MasterDataController::class, 'update'])->whereIn('resource', MasterData::RESOURCES)->whereNumber('record')->name('update');
    Route::patch('/{resource}/{record}/nonaktifkan', [MasterDataController::class, 'deactivate'])->whereIn('resource', MasterData::RESOURCES)->whereNumber('record')->name('deactivate');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active', 'role:admin,staff'])->group(function () {
    Route::get('/tabungan', [BalanceController::class, 'members'])->name('balances.members');
    Route::get('/tabungan/{member}', [BalanceController::class, 'member'])->whereNumber('member')->name('balances.ledger');
    Route::get('/stok', [BalanceController::class, 'stock'])->name('balances.stock');
    Route::get('/stok/{wasteType}', [BalanceController::class, 'movements'])->whereNumber('wasteType')->name('balances.movements');
    Route::get('/setoran', [DepositController::class, 'index'])->name('deposits.index');
    Route::get('/setoran/tambah', [DepositController::class, 'create'])->name('deposits.create');
    Route::post('/setoran', [DepositController::class, 'store'])->name('deposits.store');
    Route::get('/setoran/{deposit}', [DepositController::class, 'show'])->whereNumber('deposit')->name('deposits.show');
    Route::patch('/setoran/{deposit}/batalkan', [DepositController::class, 'destroy'])->whereNumber('deposit')->name('deposits.destroy');
    Route::get('/penjualan', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/penjualan/tambah', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/penjualan', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/penjualan/{sale}', [SaleController::class, 'show'])->whereNumber('sale')->name('sales.show');
    Route::patch('/penjualan/{sale}/batalkan', [SaleController::class, 'destroy'])->whereNumber('sale')->name('sales.destroy');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin/dashboard', [DashboardController::class, 'show'])->middleware('role:admin')->name('admin.dashboard');
    Route::get('/petugas/dashboard', [DashboardController::class, 'show'])->middleware('role:staff')->name('staff.dashboard');
});
