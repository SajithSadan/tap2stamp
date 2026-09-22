<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $theme['name'] }} — Theme Preview</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family={{ $theme['google_fonts'] }}&display=swap" rel="stylesheet">
    <style>
        body {
            background: {{ $theme['page_bg'] }};
            color: {{ $theme['text'] }};
            font-family: {{ $theme['body_font'] }};
        }
        .heading-font { font-family: {{ $theme['heading_font'] }}; }
    </style>
</head>
<body class="min-h-screen antialiased">
    <div class="mx-auto max-w-sm px-5 py-8">
        <a href="/dev/themes" class="text-xs opacity-70 hover:opacity-100">&larr; back to theme picker</a>

        <div class="mt-4 flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-wide opacity-60">{{ $theme['name'] }}</p>
                <h1 class="heading-font text-xl font-bold">Artisan Cafe</h1>
            </div>
        </div>

        {{-- Loyalty card --}}
        <div
            class="mt-5 p-5"
            style="background: {{ $theme['card_bg'] }}; border-radius: {{ $theme['radius'] }}; box-shadow: {{ $theme['shadow'] }}; border: 1px solid {{ $theme['border'] }};"
        >
            <p class="heading-font text-lg font-semibold">Free coffee, every 6th visit</p>
            <p class="mt-1 text-sm" style="color: {{ $theme['muted'] }}">3 / 6 &middot; free coffee at 6</p>

            {{-- Stamp grid --}}
            <div class="mt-4 grid grid-cols-6 gap-2">
                @for ($i = 1; $i <= 6; $i++)
                    <div
                        class="flex aspect-square items-center justify-center rounded-full text-xs font-semibold"
                        style="background: {{ $i <= 3 ? $theme['stamp_filled'] : $theme['stamp_empty'] }}; color: {{ $i <= 3 ? $theme['accent_text'] : $theme['muted'] }};"
                    >
                        @if ($i <= 3) &#10003; @endif
                    </div>
                @endfor
            </div>

            <p class="mt-3 text-xs" style="color: {{ $theme['muted'] }}">Rewards claimed: 2</p>

            {{-- QR placeholder --}}
            <div class="mt-4 flex justify-center">
                <div
                    class="grid h-32 w-32 grid-cols-5 grid-rows-5 gap-0.5 p-2"
                    style="background: #FFFFFF; border-radius: 12px; border: 1px solid {{ $theme['border'] }};"
                >
                    @for ($i = 0; $i < 25; $i++)
                        <div style="background: {{ rand(0, 100) > 45 ? '#111111' : 'transparent' }};"></div>
                    @endfor
                </div>
            </div>
        </div>

        {{-- Hub tiles --}}
        <div class="mt-4 space-y-3">
            <a
                href="#"
                onclick="return false;"
                class="flex items-center justify-between px-4 py-3 text-sm font-medium"
                style="background: {{ $theme['card_bg'] }}; border-radius: {{ $theme['button_radius'] }}; border: 1px solid {{ $theme['border'] }};"
            >
                Leave a Google Review
                <span style="color: {{ $theme['accent'] }}">&rarr;</span>
            </a>
            <a
                href="#"
                onclick="return false;"
                class="flex items-center justify-between px-4 py-3 text-sm font-medium"
                style="background: {{ $theme['card_bg'] }}; border-radius: {{ $theme['button_radius'] }}; border: 1px solid {{ $theme['border'] }};"
            >
                Follow us on Instagram
                <span style="color: {{ $theme['accent'] }}">&rarr;</span>
            </a>

            <div x-data="{ open: false }" style="background: {{ $theme['card_bg'] }}; border-radius: {{ $theme['button_radius'] }}; border: 1px solid {{ $theme['border'] }};">
                <button
                    @click="open = !open"
                    class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium"
                >
                    Free Wi-Fi
                    <span style="color: {{ $theme['accent'] }}" x-text="open ? '−' : '+'"></span>
                </button>
                <div x-show="open" x-cloak class="px-4 pb-4 text-sm" style="color: {{ $theme['muted'] }}">
                    <p>SSID: <span style="color: {{ $theme['text'] }}">ArtisanCafe-Guest</span></p>
                    <p class="mt-1">Password: <span style="color: {{ $theme['text'] }}">latte1234</span></p>
                    <button
                        class="mt-3 px-3 py-1.5 text-xs font-semibold"
                        style="background: {{ $theme['accent'] }}; color: {{ $theme['accent_text'] }}; border-radius: {{ $theme['button_radius'] }};"
                    >
                        Copy password
                    </button>
                </div>
            </div>
        </div>

        {{-- Primary button sample --}}
        <button
            class="mt-5 w-full px-4 py-3 text-sm font-semibold"
            style="background: {{ $theme['accent'] }}; color: {{ $theme['accent_text'] }}; border-radius: {{ $theme['button_radius'] }};"
        >
            Register this card
        </button>
    </div>
</body>
</html>
