@php
    // Grouped here rather than in the middleware: each network has its own
    // snippet, and a view that filters inline is a view nobody can read.
    $metaPixels = $pixels->where('provider', 'meta');
    $tiktokPixels = $pixels->where('provider', 'tiktok');
    $snapchatPixels = $pixels->where('provider', 'snapchat');
@endphp

@if ($metaPixels->isNotEmpty())
{{--
    Meta Pixel base code.

    Loaded async and injected at the end of <body>, so it never blocks paint on
    a page the merchant is paying per visit to show. `fbq` queues calls made
    before the script arrives, so the events below are safe to fire immediately.
--}}
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');

@foreach ($metaPixels as $pixel)
fbq('init', @json($pixel->pixel_id));
@endforeach

fbq('track', 'PageView');

/*
 * Persist `fbc` from the ad click.
 *
 * Meta only sets the `_fbc` cookie itself in some browsers, and it is the
 * strongest match signal we have — it names the exact ad click. Deriving it
 * here from `fbclid` means the server copy of the event can carry it even when
 * the browser never stored one.
 */
(function () {
    const params = new URLSearchParams(location.search);
    const fbclid = params.get('fbclid');
    if (!fbclid || document.cookie.includes('_fbc=')) return;

    const value = `fb.1.${Date.now()}.${fbclid}`;
    document.cookie = `_fbc=${value};path=/;max-age=${90 * 86400};SameSite=Lax`;
})();
</script>

<noscript><img height="1" width="1" style="display:none" alt=""
    src="https://www.facebook.com/tr?id={{ $metaPixels->first()->pixel_id }}&ev=PageView&noscript=1"></noscript>
@endif

{{--
    TikTok Pixel.

    Loaded async like Meta's. `ttq` queues calls made before the script lands,
    so the page event below is safe to fire immediately.
--}}
@if ($tiktokPixels->isNotEmpty())
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];
  ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};
  for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);
  ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};
  ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=r;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};
    var o=d.createElement("script");o.type="text/javascript";o.async=!0;o.src=r+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};

@foreach ($tiktokPixels as $pixel)
  ttq.load(@json($pixel->pixel_id));
@endforeach
  ttq.page();
}(window, document, 'ttq');
</script>
@endif

{{-- Snapchat Pixel. --}}
@if ($snapchatPixels->isNotEmpty())
<script>
(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};
a.queue=[];var s='script';var r=t.createElement(s);r.async=!0;r.src=n;var u=t.getElementsByTagName(s)[0];u.parentNode.insertBefore(r,u)})
(window,document,'https://sc-static.net/scevent.min.js');

@foreach ($snapchatPixels as $pixel)
snaptr('init', @json($pixel->pixel_id));
@endforeach
snaptr('track', 'PAGE_VIEW');
</script>
@endif
