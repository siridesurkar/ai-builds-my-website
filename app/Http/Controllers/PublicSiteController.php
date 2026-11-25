<?php

namespace App\Http\Controllers;

use App\Models\Site;

class PublicSiteController extends Controller
{
    public function show(string $slug)
    {
        $site = Site::with('sections', 'business')->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('site.public', [
            'site' => $site,
            'sections' => $site->sections->keyBy('type'),
            'business' => $site->business,
            'preview' => false,
        ]);
    }
}
