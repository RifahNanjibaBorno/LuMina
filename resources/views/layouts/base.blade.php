<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="LUMiNA connects student portfolios, academic projects and course resources.">
    <title>@yield('title', 'LUMiNA') · Academic Portfolio &amp; Repository</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body-class')">
    @yield('body')
</body>
</html>
