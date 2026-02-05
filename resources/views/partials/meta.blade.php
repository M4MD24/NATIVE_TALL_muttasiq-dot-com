<meta charset="utf-8" />
<meta
    name="application-name"
    content="{{ config('app.name') }}"
/>
<meta
    name="csrf-token"
    content="{{ csrf_token() }}"
/>
<meta
    name="viewport"
    content="width=device-width, initial-scale=1, user-scalable=no, viewport-fit=cover"
/>
<link
    href="{{ route('home') }}"
    rel="canonical"
>
<title>{{ config('app.name') }}</title>
<meta
    property="og:title"
    content="{{ config('app.name') }}"
>
<meta
    name="keywords"
    content="keyword, another, and another"
>
<meta
    name="description"
    content="Some description..."
>
<meta
    property="og:description"
    content="Some description..."
>
<meta
    property="og:image"
    content="{{ asset('images/open-graph.jpg') }}"
>
<meta
    name="theme-color"
    x-bind:content="$store.colorScheme.isDarkModeOn ?
        $store.colorScheme.bodyBackgroundHexes.dark :
        $store.colorScheme.bodyBackgroundHexes.light"
>
