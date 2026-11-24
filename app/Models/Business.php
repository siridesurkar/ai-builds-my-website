<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    protected $guarded = [];

    protected $casts = [
        'candidates' => 'array',
        'selected_candidate' => 'array',
    ];

    /** Routes use the unguessable token, never the numeric id. */
    public function getRouteKeyName(): string
    {
        return 'edit_token';
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class)->orderBy('id');
    }

    public function facts(): HasMany
    {
        return $this->hasMany(Fact::class);
    }

    public function site(): HasOne
    {
        return $this->hasOne(Site::class);
    }

    /**
     * Single-value fields (phone, address...) grouped by normalised value.
     * More than one distinct value = the sources disagree and the user must choose.
     */
    public function singleGroups(): array
    {
        $all = $this->facts()->with('source')->where('origin', 'ai')->get()->groupBy('key');
        $out = [];
        foreach (Fact::SINGLE_KEYS as $key) {
            $distinct = ($all[$key] ?? collect())->groupBy(fn (Fact $f) => Fact::normalize($key, $f->value));
            $out[$key] = ['distinct' => $distinct, 'conflict' => $distinct->count() > 1];
        }
        return $out;
    }
}
