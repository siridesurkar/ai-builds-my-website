<?php

namespace App\Http\Controllers;

use App\Exceptions\AiUnavailableException;
use App\Models\Business;
use App\Models\Fact;
use App\Services\FactExtractor;
use App\Services\SiteGenerator;
use App\Services\SourceCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    public function create()
    {
        return view('home');
    }

    public function store(Request $request, SourceCollector $collector, FactExtractor $extractor)
    {
        set_time_limit(240); // fetching + search + extraction run inline in v1 (queue is on the "next" list)

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'city' => 'required|string|max:100',
            'links' => 'nullable|string|max:3000',
            'google_url' => 'nullable|url|max:500',
            'files' => 'nullable|array|max:8',
            'files.*' => 'file|mimes:pdf,txt,csv,jpg,jpeg,png,webp|max:10240',
        ]);

        $urls = [];
        foreach (preg_split('/\R+/', $data['links'] ?? '') ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (! preg_match('~^https?://~i', $line)) {
                $line = 'https://' . $line;
            }
            if (! filter_var($line, FILTER_VALIDATE_URL)) {
                return back()->withErrors(['links' => "This doesn't look like a valid link: {$line}"])->withInput();
            }
            $urls[] = $line;
        }
        $urls = array_slice(array_unique($urls), 0, 8);

        $business = Business::create([
            'edit_token' => (string) Str::uuid(),
            'name' => trim($data['name']),
            'city' => trim($data['city']),
        ]);

        $collector->collect($business, $urls, $data['google_url'] ?? null, $request->file('files', []));
        $this->runExtraction($business, $extractor, null);

        return redirect()->route('businesses.show', $business);
    }

    public function show(Business $business)
    {
        if ($business->status === 'generated') {
            return redirect()->route('site.edit', $business);
        }
        $business->load('sources');
        return view('business.show', ['business' => $business]);
    }

    /** The user picked which of several same-name businesses is theirs. */
    public function candidate(Request $request, Business $business, FactExtractor $extractor)
    {
        set_time_limit(180);
        $choice = $request->input('choice');
        $candidates = $business->candidates ?? [];

        if ($choice === 'none' || ! isset($candidates[(int) $choice])) {
            // "None of these": drop mixed-up facts so nothing from another business leaks in.
            $business->facts()->where('origin', 'ai')->delete();
            $business->update(['status' => 'needs_review', 'selected_candidate' => null,
                'status_message' => 'None of the found businesses matched, so we cleared them. Add your details below or paste text about your business.']);
            return redirect()->route('businesses.show', $business);
        }

        $business->update(['selected_candidate' => $candidates[(int) $choice]]);
        $this->runExtraction($business, $extractor, $candidates[(int) $choice]);
        return redirect()->route('businesses.show', $business);
    }

    /** Extra text from the owner (blocked Instagram bio, Google listing text, price list...). */
    public function paste(Request $request, Business $business, SourceCollector $collector, FactExtractor $extractor)
    {
        set_time_limit(180);
        $data = $request->validate(['text' => 'required|string|min:10|max:20000']);
        $collector->addPasted($business, $data['text']);
        $this->runExtraction($business, $extractor, $business->selected_candidate);
        return redirect()->route('businesses.show', $business)->with('ok', 'Added. We re-read everything.');
    }

    public function retry(Business $business, FactExtractor $extractor)
    {
        set_time_limit(180);
        $this->runExtraction($business, $extractor, $business->selected_candidate);
        return redirect()->route('businesses.show', $business);
    }

    /** AI is down and the owner wants to continue by typing facts themselves. */
    public function manual(Business $business)
    {
        $business->update(['status' => 'needs_review', 'status_message' => 'Enter your details below. We will build a simple site from exactly what you type.']);
        return redirect()->route('businesses.show', $business);
    }

    public function confirm(Request $request, Business $business, SiteGenerator $generator)
    {
        set_time_limit(180);
        $groups = $business->singleGroups();
        $single = (array) $request->input('single', []);
        $custom = (array) $request->input('custom', []);

        // Validate first: conflicts must be resolved explicitly, custom values must not be empty.
        $errors = [];
        foreach (Fact::SINGLE_KEYS as $key) {
            $choice = $single[$key] ?? null;
            if ($groups[$key]['conflict'] && ! $choice) {
                $errors[] = 'Your sources disagree about "' . Fact::LABELS[$key] . '". Please choose one, or pick "Leave this off".';
            }
            if ($choice === 'custom' && trim((string) ($custom[$key] ?? '')) === '') {
                $errors[] = 'You chose "Other" for "' . Fact::LABELS[$key] . '" but left it empty.';
            }
        }
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        DB::transaction(function () use ($request, $business, $groups, $single, $custom) {
            $business->facts()->where('origin', 'user')->delete();
            $business->facts()->update(['confirmed' => false]);

            foreach (Fact::SINGLE_KEYS as $key) {
                $choice = $single[$key] ?? 'none';
                $typed = trim((string) ($custom[$key] ?? ''));

                if ($choice === 'custom') {
                    $this->userFact($business, $key, $typed);
                } elseif (ctype_digit((string) $choice)) {
                    $this->confirmGroup($business, $key, (int) $choice);
                } elseif ($choice === 'none' && ! isset($single[$key]) && $typed !== '') {
                    $this->userFact($business, $key, $typed);
                }
            }

            foreach ((array) $request->input('multi', []) as $id) {
                $fact = $business->facts()->whereIn('key', Fact::MULTI_KEYS)->find((int) $id);
                if ($fact) {
                    $this->confirmGroup($business, $fact->key, $fact->id);
                }
            }

            // "Name | price" per line, typed by the owner.
            foreach (preg_split('/\R+/', (string) $request->input('extra_services', '')) ?: [] as $line) {
                [$name, $price] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
                if ($name !== '') {
                    $this->userFact($business, 'service', mb_substr($name, 0, 150), $price !== '' ? ['price' => mb_substr($price, 0, 60)] : null);
                }
            }
        });

        $generator->build($business);
        $business->update(['status' => 'generated', 'status_message' => null]);

        return redirect()->route('site.edit', $business)->with('ok', 'Your site is ready to edit.');
    }

    // ---------------------------------------------------------------------------------------------

    private function runExtraction(Business $business, FactExtractor $extractor, ?array $target): void
    {
        try {
            $result = $extractor->run($business, $target);

            if (! $target && count($result['candidates']) > 1) {
                $business->update([
                    'status' => 'needs_candidate',
                    'candidates' => $result['candidates'],
                    'status_message' => 'We found more than one business with this name. Which one is yours?',
                ]);
                return;
            }

            $message = $result['count'] === 0
                ? "We couldn't find reliable details online. Paste text about your business above, or type the details below. We won't make anything up."
                : null;
            $business->update(['status' => 'needs_review', 'status_message' => $message]);
        } catch (AiUnavailableException $e) {
            $business->update([
                'status' => 'ai_failed',
                'status_message' => 'The AI service is unavailable right now (' . $e->getMessage() . '). Your sources are saved, so nothing is lost.',
            ]);
        }
    }

    private function userFact(Business $b, string $key, string $value, ?array $meta = null): void
    {
        Fact::create(['business_id' => $b->id, 'key' => $key, 'value' => mb_substr($value, 0, 600),
            'meta' => $meta, 'verified' => true, 'confirmed' => true, 'origin' => 'user']);
    }

    /** Confirm the chosen fact and its duplicates (same normalised value from other sources). */
    private function confirmGroup(Business $b, string $key, int $factId): void
    {
        $chosen = $b->facts()->where('key', $key)->find($factId);
        if (! $chosen) {
            return;
        }
        $norm = Fact::normalize($key, $chosen->value);
        foreach ($b->facts()->where('key', $key)->get() as $f) {
            if (Fact::normalize($key, $f->value) === $norm) {
                $f->update(['confirmed' => true]);
            }
        }
    }
}
