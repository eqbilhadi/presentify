<!DOCTYPE html>
@php
    $activeTenant = tenancy()->initialized && tenant() instanceof \App\Models\Tenant ? tenant() : null;
    $tenantFaviconUrl = $activeTenant?->faviconUrl();
    $tenantThemePayload = $activeTenant
        ? app(\App\Services\Tenant\ThemeCustomizerService::class)->resolveThemePayload($activeTenant)
        : null;
    $themeDataAttributes = $tenantThemePayload['data_attributes'] ?? null;
@endphp
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @class(['dark' => ($appearance ?? 'system') == 'dark'])
    @if (! empty($themeDataAttributes['style'])) data-theme-style="{{ $themeDataAttributes['style'] }}" @endif
    @if (! empty($themeDataAttributes['menu_style'])) data-menu-style="{{ $themeDataAttributes['menu_style'] }}" @endif
    @if (! empty($themeDataAttributes['menu_accent'])) data-menu-accent="{{ $themeDataAttributes['menu_accent'] }}" @endif
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        @if ($tenantFaviconUrl)
            <link rel="icon" href="{{ $tenantFaviconUrl }}" data-tenant-favicon="true">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        @if (! empty($tenantThemePayload['font_stylesheet_url']))
            <link id="tenant-theme-fonts" rel="stylesheet" href="{{ $tenantThemePayload['font_stylesheet_url'] }}">
        @endif

        @if (! empty($tenantThemePayload['css_variables']))
            <style id="tenant-theme-initial">
                :root {
                    @foreach ($tenantThemePayload['css_variables'] as $var => $val)
                        {{ $var }}: {{ $val }};
                    @endforeach
                }
                @if (! empty($tenantThemePayload['dark_css_variables']))
                .dark {
                    @foreach ($tenantThemePayload['dark_css_variables'] as $var => $val)
                        {{ $var }}: {{ $val }};
                    @endforeach
                }
                @endif
            </style>
        @endif

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
