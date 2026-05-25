<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',  // ✅ Solo el nombre del campo
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relación con lotes
    public function lotes()
    {
        return $this->hasMany(Lote::class);
    }

    /**
     * Obtener todos los sensores asociados a los lotes del usuario (a través de ubicaciones)
     */
    public function sensors()
    {
        return Sensor::whereIn('location_id',
            Location::whereIn('lote_id', $this->lotes()->pluck('id'))->pluck('id')
        );
    }

    /**
     * Obtener todas las lecturas asociadas a los sensores del usuario
     */
    public function readings()
    {
        return Reading::whereIn('sensor_id', $this->sensors()->pluck('id'));
    }

    /**
     * Obtener todos los análisis del usuario (a través de lotes)
     */
    public function analysis()
    {
        return Analysis::whereIn('lote_id', $this->lotes()->pluck('id'));
    }

    /**
     * Obtener todas las alertas del usuario
     */
    public function alerts()
    {
        return Alert::whereIn('lote_id', $this->lotes()->pluck('id'));
    }

    /**
     * Obtener alertas no resueltas del usuario
     */
    public function getUnresolvedAlerts()
    {
        return $this->alerts()->unresolved()->orderByDesc('created_at')->get();
    }

    /**
     * Verificar si el usuario es administrador
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Verificar si el usuario es agricultor
     */
    public function isAgricultor(): bool
    {
        return $this->role === 'agricultor' || $this->role === 'user';
    }
}