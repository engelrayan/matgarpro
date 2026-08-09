{{-- One product shot for the builder demo.

     Drawn, not photographed — there is no stock library in this repo, and a
     grey placeholder box says "unfinished" louder than any headline says
     "polished". Three things are what make a drawing read as a photo at
     thumbnail size, and all three are here:

       · a studio backdrop that is lighter at the top than the bottom,
       · a soft contact shadow under the object rather than around it,
       · the object filling most of the frame, the way a product photographer
         crops. The old hero mock failed on exactly this last point: tiny
         shapes marooned in grey.

     `$kind` picks the silhouette, `$hue` tints it. Both are passed by the
     caller so four cards side by side are four different products.
--}}
@php
    $uid = 'sh' . substr(md5($kind . $hue . uniqid('', true)), 0, 6);
    $deep = "hsl({$hue} 45% 24%)";
    $mid = "hsl({$hue} 40% 42%)";
    $pale = "hsl({$hue} 45% 72%)";
@endphp

<svg viewBox="0 0 108 108" role="img" aria-label="{{ $kind }}" xmlns="http://www.w3.org/2000/svg">
    <defs>
        {{-- Lit from the top-left, like a softbox over the merchant's shoulder. --}}
        <linearGradient id="{{ $uid }}-g" x1="0.2" y1="0" x2="0.9" y2="1">
            <stop offset="0" stop-color="{{ $pale }}"/>
            <stop offset="0.55" stop-color="{{ $mid }}"/>
            <stop offset="1" stop-color="{{ $deep }}"/>
        </linearGradient>

        <radialGradient id="{{ $uid }}-bg" cx="0.5" cy="0.12" r="0.95">
            <stop offset="0" stop-color="#ffffff"/>
            <stop offset="1" stop-color="hsl(40 12% 90%)"/>
        </radialGradient>

        <filter id="{{ $uid }}-soft" x="-30%" y="-30%" width="160%" height="160%">
            <feDropShadow dx="0" dy="3" stdDeviation="3.5" flood-color="{{ $deep }}" flood-opacity="0.28"/>
        </filter>
    </defs>

    <rect width="108" height="108" fill="url(#{{ $uid }}-bg)"/>

    {{-- Contact shadow. Without it the product floats and the whole card reads
         as clip-art rather than as something that was on a table. --}}
    <ellipse cx="54" cy="94" rx="30" ry="5" fill="{{ $deep }}" opacity="0.16"/>

    <g filter="url(#{{ $uid }}-soft)">
        @switch ($kind)
            @case ('shirt')
                <path d="M24 32 44 20l10 9 10-9 20 12-7 15-9-5v40H40V42l-9 5z" fill="url(#{{ $uid }}-g)"/>
                <path d="M44 20h20l-10 9z" fill="{{ $deep }}" opacity=".55"/>
                <path d="M54 46v34" stroke="{{ $deep }}" stroke-width="1.5" opacity=".35"/>
                @break

            @case ('watch')
                <rect x="43" y="8" width="22" height="26" rx="6" fill="{{ $deep }}"/>
                <rect x="43" y="72" width="22" height="26" rx="6" fill="{{ $deep }}"/>
                <circle cx="54" cy="53" r="26" fill="url(#{{ $uid }}-g)"/>
                <circle cx="54" cy="53" r="19" fill="#fff" opacity=".94"/>
                <path d="M54 40v13l9 6" stroke="{{ $deep }}" stroke-width="3" stroke-linecap="round" fill="none"/>
                <circle cx="54" cy="53" r="2" fill="{{ $deep }}"/>
                @break

            @case ('shoe')
                <path d="M12 72c10-3 18-12 25-24 5-8 12-9 17-4l7 9 24 11c7 3 10 7 10 12v4H12z" fill="url(#{{ $uid }}-g)"/>
                <path d="M12 80h83v5a5 5 0 0 1-5 5H17a5 5 0 0 1-5-5z" fill="{{ $deep }}"/>
                <path d="M44 50l11 7M51 42l11 7M58 34l11 7" stroke="#fff" stroke-width="2.5" stroke-linecap="round" opacity=".75"/>
                @break

            @default
                <path d="M25 38h58l-6 50H31z" fill="url(#{{ $uid }}-g)"/>
                <path d="M41 38V27a13 13 0 0 1 26 0v11" stroke="{{ $mid }}" stroke-width="5.5" fill="none" stroke-linecap="round"/>
                <rect x="46" y="52" width="16" height="11" rx="3.5" fill="#fff" opacity=".55"/>
        @endswitch
    </g>
</svg>
