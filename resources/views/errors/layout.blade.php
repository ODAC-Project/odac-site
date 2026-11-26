@php($locale = \App\Support\Odac\Locale::resolve(request()))
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>{{ \App\Support\Odac\Locale::text("errors.{$code}_title", $locale) }} · ODAC</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #0b1120; color: #e8ecf5; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; padding: 16px; box-sizing: border-box; }
        main { max-width: 32rem; text-align: center; }
        .mark { font-size: 2.5rem; font-weight: 600; letter-spacing: -0.02em; }
        .mark span { color: #2dd4bf; }
        h1 { font-size: 1.25rem; margin: 1.25rem 0 0.5rem; }
        p { color: #b4bed4; line-height: 1.5; }
        a { color: #5eead4; }
    </style>
</head>
<body>
<main>
    <div class="mark">odac<span>.</span></div>
    <h1>{{ \App\Support\Odac\Locale::text("errors.{$code}_title", $locale) }}</h1>
    <p>{{ \App\Support\Odac\Locale::text("errors.{$code}_text", $locale) }}</p>
    <p><a href="/">{{ \App\Support\Odac\Locale::text('errors.back_home', $locale) }}</a></p>
</main>
</body>
</html>
