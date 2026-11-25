<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BusinessController::class, 'create'])->name('home');
Route::post('/businesses', [BusinessController::class, 'store'])->middleware('throttle:6,1')->name('businesses.store');

// The unguessable edit_token in the URL is the owner's "key" (no accounts in v1 - see DECISIONS.md).
Route::prefix('/b/{business:edit_token}')->group(function () {
    Route::get('/', [BusinessController::class, 'show'])->name('businesses.show');
    Route::post('/candidate', [BusinessController::class, 'candidate'])->name('businesses.candidate');
    Route::post('/paste', [BusinessController::class, 'paste'])->middleware('throttle:10,1')->name('businesses.paste');
    Route::post('/retry', [BusinessController::class, 'retry'])->middleware('throttle:10,1')->name('businesses.retry');
    Route::post('/manual', [BusinessController::class, 'manual'])->name('businesses.manual');
    Route::post('/confirm', [BusinessController::class, 'confirm'])->name('businesses.confirm');

    Route::get('/edit', [SiteController::class, 'edit'])->name('site.edit');
    Route::get('/preview', [SiteController::class, 'preview'])->name('site.preview');
    Route::put('/settings', [SiteController::class, 'settings'])->name('site.settings');
    Route::put('/sections/{type}', [SiteController::class, 'update'])->name('site.update');
    Route::post('/sections/{type}/regenerate', [SiteController::class, 'regenerate'])->middleware('throttle:20,1')->name('site.regenerate');
    Route::post('/publish', [SiteController::class, 'publish'])->name('site.publish');
    Route::post('/unpublish', [SiteController::class, 'unpublish'])->name('site.unpublish');
});

Route::get('/s/{slug}', [PublicSiteController::class, 'show'])->name('public.site');
