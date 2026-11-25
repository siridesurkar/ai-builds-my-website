@extends('layouts.app')
@section('content')
<h1>{{ $business->name }} <span class="muted">· edit your site</span></h1>

@if($site->notice)<div class="note warn">{{ $site->notice }}</div>@endif

<section class="card">
    <div class="bar">
        <a class="btn secondary" href="{{ route('site.preview', $business) }}" target="_blank">Preview</a>
        @if($site->isPublished())
            <span>🟢 Live: <a href="{{ route('public.site', $site->slug) }}" target="_blank">{{ route('public.site', $site->slug) }}</a></span>
            <form method="POST" action="{{ route('site.publish', $business) }}">@csrf <button class="btn">Publish changes</button></form>
            <form method="POST" action="{{ route('site.unpublish', $business) }}">@csrf <button class="btn red">Unpublish</button></form>
        @else
            <form method="POST" action="{{ route('site.publish', $business) }}">@csrf <button class="btn green">Publish to a public URL</button></form>
        @endif
        <form method="POST" action="{{ route('site.settings', $business) }}" class="bar">
            @csrf @method('PUT')
            <input type="color" name="accent" value="{{ $site->accent }}" style="width:44px;height:36px;padding:0">
            <button class="btn secondary" style="margin:0">Set colour</button>
        </form>
    </div>
    <p class="muted" style="margin-bottom:0">Edits save to the live page as soon as you press “Publish changes”. To add a new fact, type it into the section text yourself — AI regeneration can only use facts you confirmed.</p>
</section>

@foreach($sections as $section)
    @php $c = $section->content; $type = $section->type; @endphp
    <section class="card">
        <h2>{{ ucfirst($type) }} <span class="muted">· v{{ $section->version }}</span></h2>

        <form method="POST" action="{{ route('site.update', [$business, $type]) }}">
            @csrf @method('PUT')

            @if($type === 'hero')
                <label>Headline</label><input name="headline" value="{{ $c['headline'] ?? '' }}" maxlength="120">
                <label>Subheadline</label><input name="subheadline" value="{{ $c['subheadline'] ?? '' }}" maxlength="240">
                <label>Button text</label><input name="cta_label" value="{{ $c['cta_label'] ?? '' }}" maxlength="40">

            @elseif($type === 'about')
                <label>Title</label><input name="title" value="{{ $c['title'] ?? '' }}" maxlength="80">
                <label>Text</label><textarea name="body" rows="6" maxlength="4000">{{ $c['body'] ?? '' }}</textarea>

            @elseif($type === 'services')
                <p class="muted">Name · Price · Description. Clear a name to remove a row. Use the empty rows to add.</p>
                @foreach(array_merge($c['items'] ?? [], [[], [], []]) as $i => $item)
                    <div class="row">
                        <input name="items[{{ $i }}][name]" value="{{ $item['name'] ?? '' }}" placeholder="Name">
                        <input name="items[{{ $i }}][price]" value="{{ $item['price'] ?? '' }}" placeholder="Price">
                        <input name="items[{{ $i }}][description]" value="{{ $item['description'] ?? '' }}" placeholder="Description">
                    </div>
                @endforeach

            @elseif($type === 'gallery')
                <p class="muted">Tick photos to remove them from the site.</p>
                <div class="thumbs">
                    @foreach($c['images'] ?? [] as $img)
                        <label><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($img) }}" alt=""><input type="checkbox" name="remove[]" value="{{ $img }}"> remove</label>
                    @endforeach
                </div>

            @elseif($type === 'contact')
                <label>Phone</label><input name="phone" value="{{ $c['phone'] ?? '' }}">
                <label>Email</label><input name="email" type="email" value="{{ $c['email'] ?? '' }}">
                <label>Address</label><input name="address" value="{{ $c['address'] ?? '' }}">
                <label>Opening hours</label><textarea name="hours" rows="2">{{ $c['hours'] ?? '' }}</textarea>
                <label>Social links (one per line)</label><textarea name="socials" rows="2">{{ implode("\n", $c['socials'] ?? []) }}</textarea>
            @endif

            <button class="btn">Save {{ $type }}</button>
        </form>

        @if(in_array($type, ['hero', 'about', 'services']))
            <form method="POST" action="{{ route('site.regenerate', [$business, $type]) }}" class="regen">
                @csrf
                <input name="instruction" maxlength="200" placeholder="Optional: shorter, more formal, friendlier…">
                <button class="btn secondary">↻ Regenerate with AI</button>
            </form>
        @endif
    </section>
@endforeach
@endsection
