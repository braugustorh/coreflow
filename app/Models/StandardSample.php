<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandardSample extends Model
{
    protected $guarded = [];

    public function standardValues()
    {
        return $this->hasMany(StandardValue::class);
    }
}
