<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title inertia>{{ $seo['title'] ?? config('app.name') }}</title>
    @isset($seo)
        {{-- Public pages (landing: LandingPage::seo(); legal pages, contact): the app renders in
             the browser, so search engines and link previews need these in the HTML itself. --}}
        <meta name="description" content="{{ $seo['description'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        @isset($seo['image'])
            <meta property="og:image" content="{{ $seo['image'] }}">
            <meta property="og:image:type" content="image/jpeg">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:alt" content="{{ $seo['image_alt'] }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:image" content="{{ $seo['image'] }}">
        @endisset
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        @isset($seo['json_ld'])
            <script type="application/ld+json">{!! $seo['json_ld'] !!}</script>
        @endisset
    @endisset
    <link rel="icon" type="image/png" sizes="512x512" href="/images/favicon-512.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="bg-brand-bg text-brand-text antialiased">
    @inertia
</body>
</html>
