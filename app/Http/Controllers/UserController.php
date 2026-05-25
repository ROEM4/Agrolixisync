<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();
        $usuarios = User::where('role', 'agricultor')->get();
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password ?: 'password'), // default si no pone
            'role' => 'agricultor',
        ]);

        return redirect()->route('usuarios.index')->with('success', 'Agricultor creado correctamente.');
    }

    private function authorizeAdmin()
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Acceso denegado. Solo administradores.');
        }
    }
}