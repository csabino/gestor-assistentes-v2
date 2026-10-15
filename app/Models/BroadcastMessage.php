<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastMessage extends Model
{
    protected $fillable = ['name', 'body', 'created_by_user_id'];
}
