<?php

namespace App\Http\Controllers;

use App\Exceptions\AiUnavailableException;
use App\Models\Business;
use App\Models\Site;
use App\Models\SiteSection;
use App\Services\SiteGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteController extends Controller
{
    public function edit(Business $business)
    {
        $site = $this->site($business);
        return view('business.editor', ['business' => $business, 'site' => $site, 'sections' => $site->sections]);
    }

    public function preview(Business $business)
    {
        $site = $this->site($business);
        return view('site.public', ['site' => $site, 'sections' => $site->sections->keyBy('type'), 'business' => $business, 'preview' => true]);
    }

    public function settings(Request $request, Business $business)
    {
        $data = $request->validate(['accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        $this->site($business)->update(['accent' => $data['accent']]);
        return back()->with('ok', 'Colour updated.');
    }

    public function update(Request $request, Business $business, string $type)
    {
        $section = $this->section($business, $type);

        $content = match ($type) {
            'hero' => array_merge($section->content, $request->validate([
                'headline' => 'nullable|string|max:120', 'subheadline' => 'nullable|string|max:240', 'cta_label' => 'nullable|string|max:40',
            ])),
            'about' => array_merge($section->content, $request->validate(['title' => 'nullable|string|max:80', 'body' => 'nullable|string|max:4000'])),
            'services' => array_merge($section->content, ['items' => $this->items($request)]),
            'contact' => array_merge($section->content, $request->validate([
                'phone' => 'nullable|string|max:60', 'email' => 'nullable|email|max:150',
                'address' => 'nullable|string|max:300', 'hours' => 'nullable|string|max:500',
            ]), ['socials' => $this->socials((string) $request->input('socials', ''))]),
            'gallery' => array_merge($section->content, [
                'images' => array_values(array_diff($section->content['images'] ?? [], (array) $request->input('remove', []))),
            ]),
            default => abort(404),
        };

        $section->update(['content' => $content, 'version' => $section->version + 1]);
        return back()->with('ok', ucfirst($type) . ' saved.');
    }

    public function regenerate(Request $request, Business $business, string $type, SiteGenerator $generator)
    {
        set_time_limit(120);
        $section = $this->section($business, $type);
        $instruction = trim((string) $request->input('instruction', '')) ?: null;

        try {
            $generator->regenerate($business, $section, $instruction);
        } catch (AiUnavailableException $e) {
            // Graceful failure: the section is untouched and the user can keep editing by hand.
            return back()->with('warn', "The AI couldn't regenerate this section right now ({$e->getMessage()}). Your current text is unchanged; you can still edit it by hand or try again later.");
        }
        return back()->with('ok', ucfirst($type) . ' regenerated.');
    }

    public function publish(Business $business)
    {
        $site = $this->site($business);
        if (! $site->slug) {
            $base = Str::slug($business->name . '-' . $business->city) ?: 'site';
            $slug = $base;
            while (Site::where('slug', $slug)->exists()) {
                $slug = $base . '-' . Str::lower(Str::random(4));
            }
            $site->slug = $slug;
        }
        $site->status = 'published';
        $site->published_at = now();
        $site->save();

        return back()->with('ok', 'Published! Your site is live at ' . route('public.site', $site->slug));
    }

    public function unpublish(Business $business)
    {
        $this->site($business)->update(['status' => 'draft']);
        return back()->with('ok', 'Your site is no longer public.');
    }

    // ---------------------------------------------------------------------------------------------

    private function site(Business $business): Site
    {
        return $business->site()->with('sections')->firstOr(fn () => abort(redirect()->route('businesses.show', $business)));
    }

    private function section(Business $business, string $type): SiteSection
    {
        return $this->site($business)->sections()->where('type', $type)->firstOrFail();
    }

    private function items(Request $request): array
    {
        $items = [];
        foreach ((array) $request->input('items', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue; // blank name = delete the row
            }
            $items[] = [
                'name' => mb_substr($name, 0, 150),
                'price' => mb_substr(trim((string) ($row['price'] ?? '')), 0, 60) ?: null,
                'description' => mb_substr(trim((string) ($row['description'] ?? '')), 0, 300),
            ];
        }
        return array_slice($items, 0, 50);
    }

    private function socials(string $text): array
    {
        $urls = [];
        foreach (preg_split('/\R+/', $text) ?: [] as $line) {
            $line = trim($line);
            if (preg_match('~^https?://~i', $line) && filter_var($line, FILTER_VALIDATE_URL)) {
                $urls[] = $line;
            }
        }
        return array_values(array_unique($urls));
    }
}
