<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'nombre',
        'email',
        'password',
        'rol',
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

    // Relación con plantas
    public function plantas()
    {
        return $this->hasMany(Planta::class, 'usuario_id');
    }

    /**
     * Obtener todos los sensores asociados a las plantas del usuario (a través de ubicaciones)
     */
    public function sensores()
    {
        return Sensor::whereIn('ubicacion_id',
            Ubicacion::whereIn('planta_id', $this->plantas()->pluck('id'))->pluck('id')
        );
    }

    /**
     * Obtener todas las lecturas asociadas a los sensores del usuario
     */
    public function lecturas()
    {
        return Lectura::whereIn('sensor_id', $this->sensores()->pluck('id'));
    }

    /**
     * Obtener todos los análisis del usuario (a través de plantas)
     */
    public function analisis()
    {
        return AnalisisLixiviacion::whereIn('planta_id', $this->plantas()->pluck('id'));
    }

    /**
     * Obtener todas las alertas del usuario
     */
    public function alertas()
    {
        return Alerta::whereIn('planta_id', $this->plantas()->pluck('id'));
    }

    /**
     * Obtener alertas no resueltas del usuario
     */
    public function getUnresolvedAlerts()
    {
        return $this->alertas()->where('resuelta', false)->orderByDesc('created_at')->get();
    }

    /**
     * Verificar si el usuario es administrador
     */
    public function isAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    /**
     * Verificar si el usuario es agricultor
     */
    public function isAgricultor(): bool
    {
        return $this->rol === 'agricultor' || $this->rol === 'user';
    }
}