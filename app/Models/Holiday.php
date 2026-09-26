<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['assistant_id', 'name', 'date', 'is_recurring'];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
    ];

    public function assistant()
    {
        return $this->belongsTo(Assistant::class);
    }
}
