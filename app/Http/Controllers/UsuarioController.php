<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UsuarioController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();
        $usuarios = User::orderBy('id')->get();
        return view('usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $this->authorizeAdmin();
        return view('usuarios.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|unique:usuarios,email',
            'password' => 'nullable|string|min:6|confirmed',
            'rol' => 'required|in:admin,agricultor',
        ]);
        User::create([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'password' => Hash::make($request->password ?: 'password'),
            'rol' => $request->rol,
        ]);
        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    // Show edit form
    public function edit($id)
    {
        $this->authorizeAdmin();
        $user = User::findOrFail($id);
        return view('usuarios.edit', compact('user'));
    }

    // Update user
    public function update(Request $request, $id)
    {
        $this->authorizeAdmin();
        $user = User::findOrFail($id);
        $request->validate([
            'email' => 'required|email|unique:usuarios,email,' . $user->id,
            'password' => 'nullable|string|min:6|confirmed',
            'rol' => 'required|in:admin,agricultor',
        ]);
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->rol = $request->rol;
        $user->save();
        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    private function authorizeAdmin()
    {
        if (auth()->user()->rol !== 'admin') {
            abort(403, 'Acceso denegado. Solo administradores.');
        }
    }
}