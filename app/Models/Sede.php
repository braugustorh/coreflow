<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $fillable = [
        'name',
        'country',
        'state',
        'city',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
