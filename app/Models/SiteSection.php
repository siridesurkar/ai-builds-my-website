<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSection extends Model
{
    protected $guarded = [];

    protected $casts = ['content' => 'array'];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
