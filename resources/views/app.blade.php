<!DOCTYPE html>
<html @if(App\Lib\Utils::isDarkTheme()) class="dark" @endif>
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"/>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml"/>
    @vite('resources/js/app.ts')
    @vite('resources/css/app.css')
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
