<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $alternates)
@foreach ($alternates as $url)
    <url>
        <loc>{{ $url }}</loc>
@foreach ($alternates as $locale => $alternate)
        <xhtml:link rel="alternate" hreflang="{{ $locale }}" href="{{ $alternate }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $alternates['en'] }}"/>
    </url>
@endforeach
@endforeach
</urlset>
