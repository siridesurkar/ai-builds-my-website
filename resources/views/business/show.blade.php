@extends('layouts.app')
@section('content')
@php
    $labels = \App\Models\Fact::LABELS;
    $groups = $business->status === 'needs_review' ? $business->singleGroups() : [];
    $aiFacts = $business->facts()->with('source')->where('origin', 'ai')->get();
@endphp

<h1>{{ $business->name }} <span class="muted">· {{ $business->city }}</span></h1>

@if($business->status_message)
    <div class="note {{ $business->status === 'ai_failed' ? 'err' : 'warn' }}">{{ $business->status_message }}</div>
@endif

<section class="card">
    <h2>What we could read</h2>
    @foreach($business->sources as $s)
        <div class="src">
            <strong>{{ $s->ref }}</strong> · {{ $s->typeLabel() }} — {{ $s->label }}
            <span class="badge {{ $s->status }}">{{ $s->status }}</span>
            @if($s->status_message)<div class="muted">{{ $s->status_message }}</div>@endif
            @foreach(($s->flags ?? []) as $flag)
                <div class="note warn" style="margin:6px 0 0">⚠ Instruction-like text was found in this source and was ignored: “{{ $flag['snippet'] }}”</div>
            @endforeach
        </div>
    @endforeach

    <form method="POST" action="{{ route('businesses.paste', $business) }}">
        @csrf
        <label for="text">Something we couldn't read? Paste it here (Instagram bio, Google listing text, price list…)</label>
        <textarea id="text" name="text" rows="4" required minlength="10"></textarea>
        <button class="btn secondary">Add and re-read</button>
    </form>
</section>

@if($business->status === 'ai_failed')
    <section class="card">
        <h2>The AI is unavailable</h2>
        <p>Your sources are saved. You can try again, or continue by typing your details yourself and we'll build a simple site from exactly what you enter.</p>
        <div class="bar">
            <form method="POST" action="{{ route('businesses.retry', $business) }}">@csrf <button class="btn">Try again</button></form>
            <form method="POST" action="{{ route('businesses.manual', $business) }}">@csrf <button class="btn secondary">Continue without AI</button></form>
        </div>
    </section>

@elseif($business->status === 'needs_candidate')
    <section class="card">
        <h2>Which business is yours?</h2>
        <form method="POST" action="{{ route('businesses.candidate', $business) }}">
            @csrf
            @foreach($business->candidates ?? [] as $i => $c)
                <label class="opt"><input type="radio" name="choice" value="{{ $i }}" required> <strong>{{ $c['name'] }}</strong>@if($c['address']) — {{ $c['address'] }}@endif</label>
            @endforeach
            <label class="opt"><input type="radio" name="choice" value="none"> None of these</label>
            <button class="btn">Continue</button>
        </form>
    </section>

@elseif($business->status === 'needs_review')
    <form class="card" method="POST" action="{{ route('businesses.confirm', $business) }}">
        @csrf
        <h2>Check the facts</h2>
        <p class="muted">Only what you confirm here is used. Anything left blank stays off your website — we never fill gaps.</p>

        @foreach(\App\Models\Fact::SINGLE_KEYS as $key)
            @php
                $g = $groups[$key];
                $firstGroup = $g['distinct']->first();
                $noneDefault = $g['distinct']->isEmpty() || (! $g['conflict'] && ! $firstGroup->contains('verified', true));
            @endphp
            <fieldset class="field">
                <legend>{{ $labels[$key] }} @if($g['conflict'])<span class="badge failed">sources disagree — pick one</span>@endif</legend>
                @foreach($g['distinct'] as $facts)
                    @php $first = $facts->first(); $verified = $facts->contains('verified', true); @endphp
                    <label class="opt">
                        <input type="radio" name="single[{{ $key }}]" value="{{ $first->id }}"
                               @checked(! $g['conflict'] && $verified) @required($g['conflict'])>
                        {{ $first->value }}
                        <span class="muted">— from {{ $facts->map(fn($f) => $f->source?->ref)->filter()->unique()->implode(', ') }}@unless($verified) · we couldn't verify this in the source text, please check @endunless</span>
                    </label>
                    @if($first->evidence)<div class="evidence">“{{ $first->evidence }}”</div>@endif
                @endforeach
                <label class="opt"><input type="radio" name="single[{{ $key }}]" value="none" @checked($noneDefault)> Leave this off my site</label>
                <label class="opt"><input type="radio" name="single[{{ $key }}]" value="custom"> Other: <input type="text" name="custom[{{ $key }}]" maxlength="600" style="width:70%" onfocus="this.previousElementSibling.checked=true"></label>
            </fieldset>
        @endforeach

        <fieldset class="field">
            <legend>Services / products</legend>
            @forelse($aiFacts->where('key', 'service')->groupBy(fn($f) => \App\Models\Fact::normalize('service', $f->value)) as $group)
                @php $f = $group->first(); @endphp
                <label class="opt">
                    <input type="checkbox" name="multi[]" value="{{ $f->id }}" @checked($group->contains('verified', true))>
                    {{ $f->value }}@if(! empty($group->pluck('meta.price')->filter()->first())) — {{ $group->pluck('meta.price')->filter()->first() }}@endif
                    <span class="muted">— from {{ $group->map(fn($x) => $x->source?->ref)->filter()->unique()->implode(', ') }}</span>
                </label>
            @empty
                <p class="muted">No services found in your sources.</p>
            @endforelse
            <label for="extra_services">Add your own (one per line, optional price after “|”)</label>
            <textarea id="extra_services" name="extra_services" rows="3" placeholder="Haircut | ₹300">{{ old('extra_services') }}</textarea>
        </fieldset>

        @php $socials = $aiFacts->where('key', 'social_link')->unique('value'); @endphp
        @if($socials->count())
            <fieldset class="field">
                <legend>Social links</legend>
                @foreach($socials as $f)
                    <label class="opt"><input type="checkbox" name="multi[]" value="{{ $f->id }}" @checked($f->verified)> {{ $f->value }}</label>
                @endforeach
            </fieldset>
        @endif

        <button class="btn green">Build my website</button>
    </form>
@endif
@endsection
