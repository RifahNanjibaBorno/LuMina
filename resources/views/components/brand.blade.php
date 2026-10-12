@props(['light' => false, 'subtitle' => true])
<a href="{{ route('home') }}" class="brand {{ $light ? 'brand-light' : '' }}" aria-label="LUMiNA home">
    <span class="brand-mark" aria-hidden="true">
        <img class="brand-left" src="{{ asset('assets/figma/logo-left.svg') }}" alt="">
        <img class="brand-right" src="{{ asset('assets/figma/logo-right.svg') }}" alt="">
        <span class="brand-spine"></span>
        <img class="brand-dot" src="{{ asset('assets/figma/'.($light ? 'logo-dot-light.svg' : 'logo-dot.svg')) }}" alt="">
    </span>
    <span class="brand-copy"><strong>LUMiNA</strong>@if($subtitle)<small>Academic Portfolio &amp; Repository</small>@endif</span>
</a>
