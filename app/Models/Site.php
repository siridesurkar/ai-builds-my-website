<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $guarded = [];

    protected $casts = ['published_at' => 'datetime'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function sections()
    {
        return $this->hasMany(SiteSection::class)->orderBy('position');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
