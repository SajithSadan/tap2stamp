@extends('layouts.app')

@section('title', 'Theme Preview — ' . config('app.name'))

@section('content')
    <main class="mx-auto max-w-2xl px-6 py-12">
        <h1 class="text-2xl font-semibold">Pick a theme</h1>
        <p class="mt-2 text-sm text-stone-500">
            Four candidate look-and-feel combos for the customer loyalty card, each rendered
            with real dummy data. Open each one, then tell me which fits best.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            @foreach ($themes as $slug => $theme)
                <a
                    href="/dev/themes/{{ $slug }}"
                    class="block rounded-xl border border-stone-200 p-5 transition hover:border-stone-400 hover:shadow-md"
                >
                    <div class="flex gap-2">
                        <span class="h-8 w-8 rounded-full" style="background: {{ $theme['page_bg'] }}; border: 1px solid {{ $theme['border'] }}"></span>
                        <span class="h-8 w-8 rounded-full" style="background: {{ $theme['accent'] }}"></span>
                        <span class="h-8 w-8 rounded-full" style="background: {{ $theme['stamp_filled'] }}"></span>
                    </div>
                    <h2 class="mt-3 font-semibold">{{ $theme['name'] }}</h2>
                    <p class="mt-1 text-sm text-stone-500">{{ $theme['blurb'] }}</p>
                </a>
            @endforeach
        </div>
    </main>
@endsection
