@extends('layouts.app')

@section('content')
{{-- Top slider area (will include only if sliders exist) --}}
@includeIf('components.slider')

<main class="container mx-auto py-8">
    @if(isset($page) && $page)
    <h1 class="text-3xl font-bold mb-4">{{ $page->display_title ?? ($title ?? 'Page') }}</h1>
    {{-- Render a simple description/content if present --}}
    @if(is_array($page->description))
    {!! $page->description[app()->getLocale()] ?? ($page->description['en'] ?? '') !!}
    @else
    {!! $page->description ?? '' !!}
    @endif
    @else
    <h1 class="text-3xl font-bold">{{ $title ?? 'Page' }}</h1>
    <p>No page content.</p>
    @endif
</main>
@endsection