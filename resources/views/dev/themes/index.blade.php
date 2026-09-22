@extends('layouts.app')

@section('title', 'Theme Preview — ' . config('app.name'))

@section('content')
    <main class="mx-auto max-w-2xl px-6 py-12" x-data="{ search: '' }">
        <h1 class="text-2xl font-semibold">Pick a theme</h1>
        <p class="mt-2 text-sm text-stone-500">
            {{ count($themes) }} candidate look-and-feel combos for the customer loyalty card,
            each rendered with real dummy data. Open each one, then tell me which fits best.
        </p>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <input
                type="text"
                x-model="search"
                placeholder="Search themes (e.g. &quot;pub&quot;, &quot;pastel&quot;, &quot;dark&quot;)&hellip;"
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none sm:flex-1"
            >
            <select
                onchange="if (this.value) window.location = this.value"
                class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm focus:border-stone-500 focus:outline-none sm:w-56"
            >
                <option value="">Jump to a theme&hellip;</option>
                @foreach ($themes as $slug => $theme)
                    <option value="/dev/themes/{{ $slug }}">{{ $theme['name'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @foreach ($themes as $slug => $theme)
                <a
                    href="/dev/themes/{{ $slug }}"
                    data-search="{{ Illuminate\Support\Str::lower($theme['name'].' '.$theme['blurb']) }}"
                    x-show="search === '' || $el.dataset.search.includes(search.toLowerCase())"
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
