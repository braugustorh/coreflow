<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandardValue extends Model
{
    protected $guarded = [];

    public function standardSample()
    {
        return $this->belongsTo(StandardSample::class);
    }

    public function element()
    {
        return $this->belongsTo(Element::class);
    }

    public function assayMethod()
    {
        return $this->belongsTo(AssayMethod::class);
    }
}
