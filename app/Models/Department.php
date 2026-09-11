<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['assistant_id', 'name', 'description'];

    public function assistant()
    {
        return $this->belongsTo(Assistant::class);
    }

    public function agents()
    {
        return $this->hasMany(HumanAgent::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}