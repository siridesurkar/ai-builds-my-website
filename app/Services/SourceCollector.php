<?php

namespace App\Services;

use App\Exceptions\AiUnavailableException;
use App\Models\Business;
use App\Models\Source;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/** Step 1 of the pipeline: turn everything the owner gave us into Source rows (text we can read + honest statuses). */
class SourceCollector
{
    private const MAX_CHARS = 15000;
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private PageFetcher $fetcher,
        private InjectionScanner $scanner,
        private GeminiClient $gemini,
    ) {
    }

    /** @param string[] $urls @param UploadedFile[] $files */
    public function collect(Business $b, array $urls, ?string $googleUrl, array $files): void
    {
        foreach ($urls as $url) {
            $this->addUrl($b, $url, $this->classify($url));
        }
        if ($googleUrl) {
            $this->addUrl($b, $googleUrl, 'google_business');
        }
        foreach ($files as $file) {
            $this->addFile($b, $file);
        }
        $this->addWebSearch($b, $googleUrl);
    }

    public function addPasted(Business $b, string $text): Source
    {
        $scan = $this->scanner->scan(mb_substr($text, 0, self::MAX_CHARS));
        return $this->create($b, 'pasted', 'Pasted by you', [
            'status' => $scan['clean'] === '' ? 'empty' : 'ok',
            'content' => $scan['clean'] ?: null,
            'flags' => $scan['flags'] ?: null,
        ]);
    }

    private function addUrl(Business $b, string $url, string $type): void
    {
        $res = $this->fetcher->fetch($url);
        $status = $res['status'];
        $message = $res['message'];

        // Google listings are almost always JS-rendered or behind a consent wall.
        if ($type === 'google_business' && $status !== 'ok') {
            $status = 'blocked';
            $message = "Google listings can't be read automatically. Paste the listing details below, or rely on the web search.";
        }

        $scan = $this->scanner->scan($res['text'] ?? '');
        $this->create($b, $type, parse_url($url, PHP_URL_HOST) ?: $url, [
            'url' => $url,
            'status' => $status,
            'status_message' => $message,
            'content' => $scan['clean'] ?: null,
            'flags' => $scan['flags'] ?: null,
        ]);
    }

    private function addFile(Business $b, UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, self::IMAGE_EXT, true)) {
            $path = $file->store("businesses/{$b->id}", 'public');
            $this->create($b, 'image', 'Photo', [
                'file_path' => $path, 'mime' => $file->getMimeType(), 'status' => 'ok',
            ]);
            return;
        }

        $path = $file->store("businesses/{$b->id}", 'local');
        $text = '';
        $error = null;

        try {
            if ($ext === 'pdf') {
                $text = (new Parser())->parseFile(Storage::disk('local')->path($path))->getText();
            } else {
                $raw = (string) file_get_contents(Storage::disk('local')->path($path));
                $text = mb_convert_encoding($raw, 'UTF-8', 'UTF-8,ISO-8859-1');
            }
        } catch (\Throwable $e) {
            $error = 'This file could not be read. Try exporting it again or paste its text below.';
        }

        $scan = $this->scanner->scan(mb_substr($text, 0, self::MAX_CHARS));
        $empty = ! $error && $scan['clean'] === '';

        $this->create($b, 'file', strtoupper($ext) . ' document', [
            'file_path' => $path,
            'mime' => $file->getMimeType(),
            'status' => $error ? 'failed' : ($empty ? 'empty' : 'ok'),
            'status_message' => $error ?: ($empty ? 'No readable text found (scanned PDF?). Upload it as a photo or paste the text below.' : null),
            'content' => $scan['clean'] ?: null,
            'flags' => $scan['flags'] ?: null,
        ]);
    }

    private function addWebSearch(Business $b, ?string $googleUrl): void
    {
        $prompt = "Find publicly available, factual information about the business \"{$b->name}\" in \"{$b->city}\": "
            . "address, phone, opening hours, what it offers, prices, and a short description. "
            . ($googleUrl ? "Its Google listing is: {$googleUrl}. " : '')
            . "If several different businesses match that name, describe EACH separately with its own address. "
            . "Report only what search results actually state; never guess or fill gaps. Say which site each detail came from.";

        try {
            $r = $this->gemini->groundedSearch($prompt);
            $scan = $this->scanner->scan($r['text']);
            $consulted = collect($r['sources'])->pluck('title')->filter()->unique()->take(8)->implode(', ');
            $this->create($b, 'web_search', 'Google web search', [
                'status' => 'ok',
                'status_message' => $consulted ? "Pages consulted: {$consulted}" : null,
                'content' => trim($scan['clean'] . ($consulted ? "\n\nPages consulted: {$consulted}" : '')),
                'flags' => $scan['flags'] ?: null,
            ]);
        } catch (AiUnavailableException $e) {
            $this->create($b, 'web_search', 'Google web search', [
                'status' => 'failed',
                'status_message' => 'Web search is unavailable right now: ' . $e->getMessage(),
            ]);
        }
    }

    private function classify(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (preg_match('/(instagram|facebook|fb|twitter|linkedin|tiktok|pinterest|youtube|youtu|threads)\./', $host) || $host === 'x.com') {
            return 'social';
        }
        if (str_contains($host, 'google.') || $host === 'maps.app.goo.gl' || $host === 'g.page' || $host === 'goo.gl') {
            return 'google_business';
        }
        return 'website';
    }

    private function create(Business $b, string $type, string $label, array $attrs): Source
    {
        return Source::create(array_merge([
            'business_id' => $b->id,
            'ref' => 'S' . ($b->sources()->count() + 1),
            'type' => $type,
            'label' => $label,
        ], $attrs));
    }
}
