<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaContactName extends Model
{
    protected $fillable = ['assistant_id', 'phone_number', 'name'];

    public function assistant()
    {
        return $this->belongsTo(Assistant::class);
    }
}
