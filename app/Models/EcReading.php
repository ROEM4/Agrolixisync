<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcReading extends Model
{
    use HasFactory;

    protected $fillable = ['sensor_id', 'lote_id', 'value', 'humidity', 'temperature'];

    public function lote()
    {
        return $this->belongsTo(Lote::class);
    }
}