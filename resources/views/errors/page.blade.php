<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#517b7a">
    <title>{{ $code }} - {{ $title }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/errors.css') }}">
</head>
<body class="error-page">
    <main class="error-card" aria-labelledby="error-title">
        <p class="error-brand">Ozan Çetinyol <span>{{ __('errors.panel_label') }}</span></p>
        <div class="error-pill">{{ __('errors.page_label') }}</div>
        <p class="error-code" aria-hidden="true">{{ $code }}</p>
        <h1 class="error-title" id="error-title">{{ $title }}</h1>
        <p class="error-text">{{ $description }}</p>
        @if($code !== 503)
            <div class="error-actions">
                <a class="error-button" href="{{ url('/') }}">{{ __('errors.back_to_panel') }}</a>
            </div>
        @endif
    </main>
</body>
</html>
