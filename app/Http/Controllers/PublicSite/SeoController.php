<?php

namespace App\Http\Controllers\PublicSite;

use App\Models\Project;
use App\Models\ServiceProduct;
use Illuminate\Http\Response;

class SeoController extends \App\Http\Controllers\Controller
{
    public function robots(): Response
    {
        $sitemapUrl = rtrim(config('app.url'), '/') . '/sitemap.xml';

        $lines = [
            'User-agent: *',
            'Disallow: /nav',
            'Disallow: /api/',
            '',
            'Sitemap: ' . $sitemapUrl,
        ];

        return response(
            implode("\n", $lines) . "\n",
            200,
            ['Content-Type' => 'text/plain']
        );
    }

    public function sitemap(): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $staticUrls = [
            ['path' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['path' => '/services', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/portfolio', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['path' => '/workflow', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['path' => '/pricing', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['path' => '/contact', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/privacy-policy', 'priority' => '0.2', 'changefreq' => 'yearly'],
        ];

        $projectUrls = Project::query()
            ->where('is_published', true)
            ->get(['url', 'updated_at'])
            ->map(fn (Project $project) => [
                'path' => '/portfolio/' . $project->url,
                'priority' => '0.7',
                'changefreq' => 'monthly',
                'lastmod' => $project->updated_at?->toAtomString(),
            ]);

        $serviceUrls = ServiceProduct::query()
            ->where('active', true)
            ->get(['slug', 'updated_at'])
            ->map(fn (ServiceProduct $serviceProduct) => [
                'path' => '/services/' . $serviceProduct->slug,
                'priority' => '0.8',
                'changefreq' => 'monthly',
                'lastmod' => $serviceProduct->updated_at?->toAtomString(),
            ]);

        $urls = collect($staticUrls)
            ->merge($serviceUrls)
            ->merge($projectUrls)
            ->map(fn (array $entry) => array_merge($entry, [
                'loc' => $baseUrl . $entry['path'],
            ]));

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
