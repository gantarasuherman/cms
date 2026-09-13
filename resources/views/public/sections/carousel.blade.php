@php $slides = $items; @endphp

{{-- The hero is the carousel section: one component, used by the homepage and
     by the admin preview, so what an administrator checks is what ships. --}}
<x-public.hero-slider :slides="$slides" :label="$section->title ?: 'Sorotan utama'" />
