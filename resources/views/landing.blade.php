@extends('layouts.app')

@section('title', config('app.name') . ' — Digital Loyalty & Social Hub')

@section('content')
    <main class="flex min-h-screen flex-col items-center justify-center px-6 text-center">
        <h1 class="text-3xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
        <p class="mt-2 max-w-sm text-sm text-stone-500">
            Digital loyalty cards for high-street independents — no app, no passwords, just a
            scan.
        </p>
    </main>
@endsection
