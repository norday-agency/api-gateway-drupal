<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
    @foreach ($posts as $post)
        <url>
            <loc>{{ $post[0] }}</loc>
            <priority>{{ $post[2] }}</priority>
            <lastmod>{{ $post[1] }}</lastmod>
            <changefreq>{{ $post[3] }}</changefreq>
        </url>
    @endforeach
</urlset>