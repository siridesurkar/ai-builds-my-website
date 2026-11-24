<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $guarded = [];

    protected $casts = ['flags' => 'array'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'website' => 'Website',
            'social' => 'Social page',
            'google_business' => 'Google listing',
            'file' => 'Uploaded document',
            'image' => 'Uploaded photo',
            'pasted' => 'Text you pasted',
            'web_search' => 'Web search',
            default => ucfirst($this->type),
        };
    }
}
