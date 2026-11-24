<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Fetches a public page and returns readable text. Never throws: returns a status the UI can explain.
 * status: ok | blocked | failed | empty
 */
class PageFetcher
{
    private const SOCIAL = [
        'instagram.com', 'facebook.com', 'fb.com', 'fb.me', 'twitter.com', 'x.com',
        'linkedin.com', 'tiktok.com', 'pinterest.com', 'youtube.com', 'youtu.be', 'threads.net',
    ];
    private const MAX_CHARS = 15000;

    /** @return array{status:string,message:?string,text:?string} */
    public function fetch(string $url): array
    {
        $current = $url;

        for ($hop = 0; $hop < 5; $hop++) {
            if (! UrlGuard::safe($current)) {
                return $this->result('failed', 'This address is not allowed or could not be found.');
            }
            try {
                $res = Http::timeout(15)
                    ->withOptions(['allow_redirects' => false])   // we follow manually so every hop is SSRF-checked
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; AIBuildsMyWebsite/1.0)',
                        'Accept-Language' => 'en',
                    ])
                    ->get($current);
            } catch (\Throwable $e) {
                return $this->result('failed', 'Could not reach this address.');
            }

            if (in_array($res->status(), [301, 302, 303, 307, 308], true) && $res->header('Location')) {
                $current = $this->resolve($current, $res->header('Location'));
                if (preg_match('~/(login|accounts/login|signin|checkpoint)|consent\.google~i', $current)) {
                    return $this->result('blocked', 'The site sent us to a login/consent page, so it blocks automated reading.');
                }
                continue;
            }
            return $this->parse($url, $res);
        }
        return $this->result('failed', 'Too many redirects.');
    }

    private function parse(string $originalUrl, $res): array
    {
        $code = $res->status();
        if (in_array($code, [401, 403, 429, 999], true)) {
            return $this->result('blocked', "The site refused automated access (HTTP {$code}).");
        }
        if ($code >= 400) {
            return $this->result('failed', "The page returned an error (HTTP {$code}).");
        }
        $type = (string) $res->header('Content-Type');
        if ($type !== '' && stripos($type, 'html') === false && stripos($type, 'text') === false) {
            return $this->result('failed', 'This address is not a web page.');
        }

        $html = substr($res->body(), 0, 1_500_000);
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        $xp = new \DOMXPath($dom);

        $meta = function (string $q) use ($xp): string {
            $n = $xp->query($q)->item(0);
            return $n ? trim($n->getAttribute('content')) : '';
        };
        $title = trim($xp->query('//title')->item(0)?->textContent ?? '');
        $ogTitle = $meta('//meta[@property="og:title"]');
        $desc = $meta('//meta[@property="og:description"]') ?: $meta('//meta[@name="description"]');

        $host = strtolower(preg_replace('/^www\./', '', (string) parse_url($originalUrl, PHP_URL_HOST)));
        $isSocial = collect(self::SOCIAL)->contains(fn ($d) => $host === $d || str_ends_with($host, '.' . $d));

        if ($isSocial) {
            $loginWall = preg_match('/log\s?in|sign\s?up|sign\s?in/i', $ogTitle . ' ' . $title);
            if ($desc === '' || ($loginWall && mb_strlen($desc) < 40)) {
                return $this->result('blocked', 'This social site blocks automated reading. Paste your bio / about text below instead.');
            }
            $text = "Page title: " . ($ogTitle ?: $title) . "\nPublic preview description: {$desc}";
            return $this->result('ok', 'Only the public preview could be read. Paste more text below if it is incomplete.', $text);
        }

        $ld = [];
        foreach ($xp->query('//script[@type="application/ld+json"]') as $n) {
            $ld[] = trim($n->textContent);
        }
        foreach ($xp->query('//script|//style|//noscript|//svg|//iframe|//template') as $n) {
            $n->parentNode?->removeChild($n);
        }
        $body = $xp->query('//body')->item(0);
        $bodyText = trim(preg_replace('/\s+/u', ' ', $body ? $body->textContent : ''));

        $parts = [];
        if ($title !== '') $parts[] = "Page title: {$title}";
        if ($desc !== '') $parts[] = "Description: {$desc}";
        if ($ld) $parts[] = 'Structured data (JSON-LD): ' . mb_substr(implode(' ', $ld), 0, 4000);
        if ($bodyText !== '') $parts[] = "Page text: {$bodyText}";
        $text = mb_substr(implode("\n", $parts), 0, self::MAX_CHARS);

        if (mb_strlen($bodyText) < 80 && ! $ld) {
            return $this->result('empty', 'No readable text found (the page may need JavaScript to load).', $text ?: null);
        }
        return $this->result('ok', null, $text);
    }

    private function resolve(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME)) {
            return $location;
        }
        $b = parse_url($base);
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($location, '//')) {
            return $b['scheme'] . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $dir = rtrim(dirname($b['path'] ?? '/'), '/');
        return $origin . $dir . '/' . $location;
    }

    private function result(string $status, ?string $message, ?string $text = null): array
    {
        return ['status' => $status, 'message' => $message, 'text' => $text];
    }
}
