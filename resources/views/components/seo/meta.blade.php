@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => 'website',
    'noindex' => false,
])

@php
    $seo = $site->seo();
    $general = $site->general();

    // Per-page values win; the global settings are the fallback. This is what
    // lets an editor override SEO on one article without touching the rest.
    $siteName = $site->siteName();
    // Every lookup is defensive: a fresh install with no settings rows yet
    // must still render a complete page, not a 500.
    $pageTitle = $title ? $title.' — '.$siteName : (($seo['seo_title'] ?? null) ?: $siteName);
    $metaDescription = $description ?: ($seo['seo_description'] ?? $general['site_description'] ?? null);
    $robots = $noindex ? 'noindex, nofollow' : (($seo['robots'] ?? null) ?: 'index, follow');

    $ogImage = $image
        ?: (($seo['og_image'] ?? null) ? Storage::disk('public')->url($seo['og_image']) : null);

    $canonical = ($seo['canonical'] ?? null) ?: url()->current();
@endphp

<title>{{ $pageTitle }}</title>

@if ($metaDescription)
    <meta name="description" content="{{ Str::limit(strip_tags($metaDescription), 160) }}">
@endif

@if ($keywords = $seo['seo_keywords'] ?? null)
    <meta name="keywords" content="{{ $keywords }}">
@endif

<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($metaDescription)
    <meta property="og:description" content="{{ Str::limit(strip_tags($metaDescription), 200) }}">
@endif
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
@endif

<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
@if ($metaDescription)
    <meta name="twitter:description" content="{{ Str::limit(strip_tags($metaDescription), 200) }}">
@endif
@if ($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif
