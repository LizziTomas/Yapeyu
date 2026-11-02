<?php

namespace App\Http\Controllers;

use App\Http\Requests\CerrarCajaRequest;
use App\Http\Requests\StoreCajaRequest;
use App\Models\Caja;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

        $cajaDeOtro = $user->cajaAbiertaDeOtro();

        return view('cajas.index', compact('cajas', 'cajaAbierta', 'cajaDeOtro', 'usuarios'));
    }

    /**
     * Muestra el formulario para abrir una nueva caja.
     */
    public function create(): View|RedirectResponse
    {
        Gate::authorize('create', Caja::class);

        if (Caja::abiertas()->exists()) {
            return $this->rechazarApertura(Auth::user());
        }

        return view('cajas.create');
    }

    /**
     * Almacena una nueva caja abierta en la base de datos.
     * Solo puede haber una caja abierta a la vez en todo el negocio.
     */
    public function store(StoreCajaRequest $request): RedirectResponse
    {
        Gate::authorize('create', Caja::class);

        /** @var User $user */
        $user = Auth::user();
        $montoInicial = $request->validated('monto_inicial');

        // El bloqueo evita que dos personas abran una caja en el mismo instante.
        // Devuelve false si otra apertura está en curso, y null si ya hay una caja abierta.
        $caja = Cache::lock('abrir-caja', 10)->get(function () use ($user, $montoInicial) {
            if (Caja::abiertas()->exists()) {
                return null;
            }

            return Caja::create([
                'user_id' => $user->id,
                'fecha_apertura' => now(),
                'monto_inicial' => $montoInicial,
                'monto_esperado' => $montoInicial,
                'estado' => 'abierta',
            ]);
        });

        if (! $caja) {
            return $this->rechazarApertura($user);
        }

        return redirect()->route('cajas.show', $caja)
            ->with('success', 'Caja abierta exitosamente.');
    }

    /**
     * Vuelve al listado de cajas explicando por qué no se puede abrir otra.
     */
    private function rechazarApertura(User $user): RedirectResponse
    {
        $mensaje = $user->cajaAbierta()
            ? 'Ya tienes una caja abierta. Ciérrala antes de abrir otra.'
            : 'Ya hay una caja abierta por '.($user->cajaAbiertaDeOtro()?->user->name ?? 'otra persona').'. No se puede abrir otra hasta que se cierre.';

        return redirect()->route('cajas.index')->with('error', $mensaje);
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
     * Muestra el comprobante imprimible del arqueo de una caja.
     * Usa la misma autorización que la vista de detalle de la caja.
     */
    public function ticket(Caja $caja): View
    {
        Gate::authorize('view', $caja);

        $caja->load('user');

        return view('cajas.ticket', ['caja' => $caja]);
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

        $montoEsperadoCalculado = $caja->dineroEsperado();
        $montoReal = (float) $request->validated('monto_real');
        $diferencia = round($montoReal - $montoEsperadoCalculado, 2);

        $caja->update([
            'fecha_cierre' => now(),
            'monto_esperado' => $montoEsperadoCalculado,
            'monto_real' => $montoReal,
            'diferencia' => $diferencia,
            'estado' => 'cerrada',
        ]);

        return redirect()->route('cajas.show', $caja)
            ->with('success', 'Caja cerrada exitosamente.');
    }
}
