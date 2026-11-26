@php($locale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0b1120">
    <meta name="description" content="{{ \App\Support\Odac\Locale::text('meta.description') }}">
    <link rel="alternate" hreflang="fr" href="{{ request()->fullUrlWithQuery(['lang' => 'fr']) }}">
    <link rel="alternate" hreflang="en" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">
    <link rel="alternate" hreflang="x-default" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230b1120'/%3E%3Ccircle cx='16' cy='16' r='7' fill='none' stroke='%232dd4bf' stroke-width='3.5'/%3E%3C/svg%3E">
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="bg-ink-950 text-ink-100 antialiased">
    @inertia
</body>
</html>
