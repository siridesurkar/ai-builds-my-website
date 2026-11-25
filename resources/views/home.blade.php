@extends('layouts.app')
@section('content')
<h1>Turn what you have into a website</h1>
<p class="muted">Tell us about your business. We read your links and files, search the web, and build a site — using only facts we can show you a source for. If we're unsure, we ask.</p>

<form class="card" method="POST" action="{{ route('businesses.store') }}" enctype="multipart/form-data">
    @csrf
    <label for="name">Business name *</label>
    <input id="name" name="name" value="{{ old('name') }}" required maxlength="150">

    <label for="city">City *</label>
    <input id="city" name="city" value="{{ old('city') }}" required maxlength="100">

    <label for="links">Website / social links (one per line)</label>
    <textarea id="links" name="links" rows="3" placeholder="https://instagram.com/yourshop&#10;https://yourshop.com">{{ old('links') }}</textarea>

    <label for="google_url">Google Business / Maps listing link</label>
    <input id="google_url" name="google_url" type="url" value="{{ old('google_url') }}" placeholder="https://maps.app.goo.gl/...">

    <label for="files">Files: PDF, price list (PDF/TXT/CSV), photos (JPG/PNG/WebP)</label>
    <input id="files" type="file" name="files[]" multiple accept=".pdf,.txt,.csv,.jpg,.jpeg,.png,.webp">

    <button class="btn">Read my sources</button>
    <p class="muted">This can take up to a minute while we fetch pages and search the web.</p>
</form>
@endsection
