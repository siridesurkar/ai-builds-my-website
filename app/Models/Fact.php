<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fact extends Model
{
    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'verified' => 'boolean',
        'confirmed' => 'boolean',
    ];

    public const SINGLE_KEYS = ['category', 'tagline', 'description', 'phone', 'email', 'address', 'hours'];
    public const MULTI_KEYS = ['service', 'social_link'];

    public const LABELS = [
        'category' => 'Type of business',
        'tagline' => 'Tagline',
        'description' => 'About the business',
        'phone' => 'Phone',
        'email' => 'Email',
        'address' => 'Address',
        'hours' => 'Opening hours',
        'service' => 'Services / products',
        'social_link' => 'Social links',
    ];

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    /** Normalise a value so "+91 98765-43210" and "098765 43210" count as the same phone, etc. */
    public static function normalize(string $key, string $value): string
    {
        if ($key === 'phone') {
            $digits = preg_replace('/\D+/', '', $value);
            return strlen($digits) > 10 ? substr($digits, -10) : $digits;
        }
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $value));
    }
}
