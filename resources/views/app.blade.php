@extends('shopify-app::layouts.default')

@section('styles')
    @routes
    @viteReactRefresh
    @vite(['resources/js/app.jsx', "resources/js/pages/{$page['component']}.jsx"])
    @inertiaHead
@endsection

@section('content')
    @inertia
@endsection

@section('scripts')
    @parent
    <ui-nav-menu>
        <a href="{{ route('home', ['shop' => request('shop')]) }}" rel="home">Product Sync Dashboard</a>
        <a href="{{ route('product-sync.conflicts.index', ['shop' => request('shop')]) }}">Conflicts</a>
        <a href="{{ route('product-sync.logs.index', ['shop' => request('shop')]) }}">Sync Logs</a>
    </ui-nav-menu>

            {{-- Include token handler for SPA mode --}}
            @include('shopify-app::partials.token_handler')
@endsection

