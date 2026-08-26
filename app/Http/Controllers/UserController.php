<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Muestra la lista de usuarios.
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $users = User::when($search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%");
        })
        ->orderBy('id', 'asc')
        ->paginate(10)
        ->withQueryString();

        return view('usuarios.index', compact('users', 'search'));
    }

    /**
     * Muestra el formulario para crear un nuevo usuario.
     */
    public function create(): View
    {
        return view('usuarios.create');
    }

    /**
     * Almacena un nuevo usuario en la base de datos.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado exitosamente.');
    }

    /**
     * Muestra el formulario de edición de un usuario.
     */
    public function edit(User $usuario): View
    {
        return view('usuarios.edit', ['user' => $usuario]);
    }

    /**
     * Actualiza un usuario existente en la base de datos.
     */
    public function update(UpdateUserRequest $request, User $usuario): RedirectResponse
    {
        $validated = $request->validated();

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $usuario->update($data);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado exitosamente.');
    }

    /**
     * Elimina un usuario de la base de datos.
     * Un administrador NO puede eliminarse a sí mismo.
     */
    public function destroy(User $usuario): RedirectResponse
    {
        if ($usuario->id === Auth::id()) {
            return redirect()->route('usuarios.index')->with('error', 'No puedes eliminar tu propia cuenta de administrador.');
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado exitosamente.');
    }
}
