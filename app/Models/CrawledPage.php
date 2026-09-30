<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrawledPage extends Model
{
    protected $fillable = ['assistant_id', 'url', 'menu_path', 'file_path', 'content_size', 'crawled_at'];

    protected $casts = [
        'menu_path' => 'array',
        'crawled_at' => 'datetime',
    ];

    public function assistant()
    {
        return $this->belongsTo(Assistant::class);
    }
}
