@php
    $hero = $sections['hero']->content ?? [];
    $about = $sections['about']->content ?? [];
    $services = $sections['services']->content ?? [];
    $gallery = $sections['gallery']->content ?? [];
    $contact = $sections['contact']->content ?? [];
    $tel = isset($contact['phone']) ? preg_replace('/[^\d+]/', '', $contact['phone']) : '';
    $desc = \Illuminate\Support\Str::limit(strip_tags($hero['subheadline'] ?? ($about['body'] ?? '')), 155);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $business->name }} — {{ $business->city }}</title>
    <meta name="description" content="{{ $desc }}">
    @if($preview)<meta name="robots" content="noindex">@endif
    <style>
        :root{--accent: {{ $site->accent }}}
        *{box-sizing:border-box}body{margin:0;font:17px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#1f2937}
        .preview{background:#fef3c7;text-align:center;padding:6px;font-size:.85rem}
        .hero{background:var(--accent);color:#fff;text-align:center;padding:84px 20px}
        .hero h1{font-size:clamp(2rem,6vw,3.2rem);margin:0 0 10px}.hero p{font-size:1.2rem;margin:0 auto 22px;max-width:640px;opacity:.95}
        .cta{display:inline-block;background:#fff;color:var(--accent);padding:12px 24px;border-radius:999px;font-weight:700;text-decoration:none}
        section.s{max-width:880px;margin:0 auto;padding:48px 20px}section.s h2{color:var(--accent);margin-top:0}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
        .item{border:1px solid #e5e7eb;border-radius:12px;padding:16px}.item h3{margin:0 0 4px}.price{color:var(--accent);font-weight:700}
        .gal{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px}.gal img{width:100%;height:180px;object-fit:cover;border-radius:10px}
        .contact{background:#f3f4f6}footer{text-align:center;padding:24px;color:#6b7280;font-size:.85rem}
        a{color:var(--accent)}
    </style>
</head>
<body>
@if($preview)<div class="preview">Draft preview — not public yet</div>@endif

<header class="hero">
    <h1>{{ $hero['headline'] ?? $business->name }}</h1>
    @if(! empty($hero['subheadline']))<p>{{ $hero['subheadline'] }}</p>@endif
    @if(! empty($contact['phone']) || ! empty($contact['email']))
        <a class="cta" href="#contact">{{ $hero['cta_label'] ?? 'Contact us' }}</a>
    @endif
</header>

@if(! empty($about['body']))
<section class="s">
    <h2>{{ $about['title'] ?? 'About us' }}</h2>
    @foreach(preg_split('/\R{2,}/', $about['body']) as $para)<p>{{ $para }}</p>@endforeach
</section>
@endif

@if(! empty($services['items']))
<section class="s">
    <h2>{{ $services['title'] ?? 'What we offer' }}</h2>
    <div class="grid">
        @foreach($services['items'] as $item)
            <div class="item">
                <h3>{{ $item['name'] }}</h3>
                @if(! empty($item['price']))<div class="price">{{ $item['price'] }}</div>@endif
                @if(! empty($item['description']))<p>{{ $item['description'] }}</p>@endif
            </div>
        @endforeach
    </div>
</section>
@endif

@if(! empty($gallery['images']))
<section class="s">
    <h2>{{ $gallery['title'] ?? 'Gallery' }}</h2>
    <div class="gal">
        @foreach($gallery['images'] as $img)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($img) }}" alt="{{ $business->name }}" loading="lazy">
        @endforeach
    </div>
</section>
@endif

@if(! empty($contact['phone']) || ! empty($contact['email']) || ! empty($contact['address']) || ! empty($contact['hours']) || ! empty($contact['socials']))
<div class="contact" id="contact">
<section class="s">
    <h2>{{ $contact['title'] ?? 'Get in touch' }}</h2>
    @if(! empty($contact['address']))<p>📍 {{ $contact['address'] }}</p>@endif
    @if(! empty($contact['phone']))<p>📞 <a href="tel:{{ $tel }}">{{ $contact['phone'] }}</a></p>@endif
    @if(! empty($contact['email']))<p>✉️ <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></p>@endif
    @if(! empty($contact['hours']))<p>🕒 {!! nl2br(e($contact['hours'])) !!}</p>@endif
    @foreach($contact['socials'] ?? [] as $url)
        @if(preg_match('~^https?://~i', $url))<p><a href="{{ $url }}" rel="noopener nofollow" target="_blank">{{ parse_url($url, PHP_URL_HOST) }}</a></p>@endif
    @endforeach
</section>
</div>
@endif

<footer>© {{ date('Y') }} {{ $business->name }}</footer>
</body>
</html>
