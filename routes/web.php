<?php

use App\Http\Controllers\CajaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Listado y consulta de productos (Admin y Vendedor)
    Route::get('productos', [ProductoController::class, 'index'])->name('productos.index');

    // Módulo de Caja (Admin y Vendedor según políticas de autorización)
    Route::get('cajas', [CajaController::class, 'index'])->name('cajas.index');
    Route::get('cajas/abrir', [CajaController::class, 'create'])->name('cajas.create');
    Route::post('cajas', [CajaController::class, 'store'])->name('cajas.store');
    Route::get('cajas/{caja}', [CajaController::class, 'show'])->name('cajas.show');
    Route::post('cajas/{caja}/cerrar', [CajaController::class, 'cerrar'])->name('cajas.cerrar');

    // Módulo de Ventas (Admin y Vendedor según políticas de autorización)
    Route::get('ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::get('ventas/crear', [VentaController::class, 'create'])->name('ventas.create');
    Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
});

// Rutas de administración (SOLO para administradores)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('usuarios', UserController::class);
    Route::resource('productos', ProductoController::class)->except(['index', 'show']);
});

require __DIR__.'/auth.php';

