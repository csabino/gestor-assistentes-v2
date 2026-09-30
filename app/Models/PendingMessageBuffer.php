<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingMessageBuffer extends Model
{
    protected $fillable = ['assistant_id', 'phone_number', 'buffered_text', 'token'];

    public function assistant()
    {
        return $this->belongsTo(Assistant::class);
    }
}
