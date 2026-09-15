<?php

namespace App\Http\Controllers;

use App\Http\Requests\CerrarCajaRequest;
use App\Http\Requests\StoreCajaRequest;
use App\Models\Caja;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CajaController extends Controller
{
    /**
     * Muestra la lista de cajas registradas y el estado de la caja actual del usuario.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Caja::class);

        /** @var User $user */
        $user = Auth::user();
        $cajaAbierta = $user->cajaAbierta();

        if ($user->isAdmin()) {
            $query = Caja::with('user');

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $cajas = $query->orderBy('fecha_apertura', 'desc')
                ->paginate(15)
                ->withQueryString();

            $usuarios = User::orderBy('name')->get();
        } else {
            $cajas = $user->cajas()
                ->orderBy('fecha_apertura', 'desc')
                ->paginate(15)
                ->withQueryString();

            $usuarios = collect();
        }

        return view('cajas.index', compact('cajas', 'cajaAbierta', 'usuarios'));
    }

    /**
     * Muestra el formulario para abrir una nueva caja.
     */
    public function create(): View|RedirectResponse
    {
        Gate::authorize('create', Caja::class);

        /** @var User $user */
        $user = Auth::user();

        if ($user->cajaAbierta()) {
            return redirect()->route('cajas.index')
                ->with('error', 'Ya tienes una caja abierta actualmente.');
        }

        return view('cajas.create');
    }

    /**
     * Almacena una nueva caja abierta en la base de datos.
     */
    public function store(StoreCajaRequest $request): RedirectResponse
    {
        Gate::authorize('create', Caja::class);

        /** @var User $user */
        $user = Auth::user();

        if ($user->cajaAbierta()) {
            return redirect()->route('cajas.index')
                ->with('error', 'Ya tienes una caja abierta. No puedes abrir otra caja.');
        }

        $montoInicial = $request->validated('monto_inicial');

        $caja = Caja::create([
            'user_id' => $user->id,
            'fecha_apertura' => now(),
            'monto_inicial' => $montoInicial,
            'monto_esperado' => $montoInicial,
            'estado' => 'abierta',
        ]);

        return redirect()->route('cajas.show', $caja)
            ->with('success', 'Caja abierta exitosamente.');
    }

    /**
     * Muestra los detalles de una caja específica.
     */
    public function show(Caja $caja): View
    {
        Gate::authorize('view', $caja);

        return view('cajas.show', compact('caja'));
    }

    /**
     * Registra el arqueo y cierre de la caja.
     * El cálculo de la diferencia se realiza estrictamente en el backend.
     */
    public function cerrar(CerrarCajaRequest $request, Caja $caja): RedirectResponse
    {
        Gate::authorize('close', $caja);

        if ($caja->estaCerrada()) {
            return redirect()->route('cajas.show', $caja)
                ->with('error', 'Esta caja ya se encuentra cerrada.');
        }

        $montoReal = (float) $request->validated('monto_real');
        $diferencia = round($montoReal - (float) $caja->monto_esperado, 2);

        $caja->update([
            'fecha_cierre' => now(),
            'monto_real' => $montoReal,
            'diferencia' => $diferencia,
            'estado' => 'cerrada',
        ]);

        return redirect()->route('cajas.show', $caja)
            ->with('success', 'Caja cerrada exitosamente.');
    }
}
