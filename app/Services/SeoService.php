<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Support\Collection;

/**
 * Single source of truth for everything a public page needs to be understood
 * by a search engine: canonical + alternate URLs, meta fallbacks and the
 * schema.org @graph.
 *
 * Structured data is built as one connected graph with stable @id values
 * instead of several stand-alone blocks. That way the Organization, WebSite,
 * WebPage and BreadcrumbList nodes reference each other rather than being
 * re-declared (and silently duplicated) on every template.
 */
class SeoService
{
    /**
     * Locale -> BCP-47 tag used for `<html lang>` and `inLanguage`.
     * Hreflang deliberately stays language-only (see alternateUrls) because
     * that is what is already indexed and what the sitemap emits.
     */
    private const HTML_LANG = [
        'nl' => 'nl-BE',
        'fr' => 'fr-BE',
        'en' => 'en',
    ];

    private const OG_LOCALE = [
        'nl' => 'nl_BE',
        'fr' => 'fr_BE',
        'en' => 'en_GB',
    ];

    /**
     * Extra schema.org nodes contributed by the page templates (Service,
     * FAQPage, ContactPage, …). The layout renders the <head> only after the
     * content section has been evaluated, so partials can still add to the
     * graph. Requires the service to be resolved as a request-scoped
     * singleton — see AppServiceProvider.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $extraNodes = [];

    /**
     * @param  array<string, mixed>  $node
     */
    public function addNode(array $node): void
    {
        $this->extraNodes[] = $node;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extraNodes(): array
    {
        return $this->extraNodes;
    }

    /**
     * Drop nodes collected for a previous page. Called by PageController before
     * the view renders: relying on the container to hand out a fresh instance
     * per request holds under PHP-FPM but not under Octane or inside a test
     * that renders several pages, where the nodes would otherwise pile up and
     * every page would advertise the previous page's Service and FAQPage.
     */
    public function resetNodes(): void
    {
        $this->extraNodes = [];
    }

    /**
     * Absolute URL of a fixed page (services hub, request, contact, …) in the
     * given locale, resolved through config('site.page_slugs').
     */
    public function pageUrl(string $code, string $locale): string
    {
        $slug = config("site.page_slugs.{$code}.{$locale}")
            ?? config("site.page_slugs.{$code}.nl");

        return route('pages.show', ['locale' => $locale, 'slug' => $slug]);
    }

    public function pageLabel(string $code, string $locale): string
    {
        return config("site.page_labels.{$code}.{$locale}")
            ?? config("site.page_labels.{$code}.nl")
            ?? $code;
    }

    public function pageSlug(string $code, string $locale): string
    {
        return config("site.page_slugs.{$code}.{$locale}")
            ?? config("site.page_slugs.{$code}.nl");
    }

    /**
     * Breadcrumb trail for a page. Service and location pages get a three-level
     * trail through their hub page so Google sees a real hierarchy instead of a
     * flat site; the last crumb carries no URL (it is the current page).
     *
     * @return array<int, array{name: string, url: string|null}>
     */
    public function breadcrumbsFor(Page $page, PageTranslation $translation, string $locale): array
    {
        if ($page->type === 'home') {
            return [];
        }

        $crumbs = [[
            'name' => $this->pageLabel('home', $locale),
            'url' => route('pages.home', ['locale' => $locale]),
        ]];

        $hub = match ($page->type) {
            'service' => 'services',
            'location' => 'service_area',
            default => null,
        };

        if ($hub !== null) {
            $crumbs[] = [
                'name' => $this->pageLabel($hub, $locale),
                'url' => $this->pageUrl($hub, $locale),
            ];
        }

        $crumbs[] = [
            'name' => $translation->title,
            'url' => null,
        ];

        return $crumbs;
    }

    public function htmlLang(string $locale): string
    {
        return self::HTML_LANG[$locale] ?? self::HTML_LANG['nl'];
    }

    public function ogLocale(string $locale): string
    {
        return self::OG_LOCALE[$locale] ?? self::OG_LOCALE['nl'];
    }

    /**
     * Absolute URL of a page in one specific locale. Returns null when that
     * locale has no translation, so callers never link to a 404.
     */
    public function urlFor(Page $page, string $locale): ?string
    {
        $translation = $page->translations->firstWhere('locale', $locale);

        if ($translation === null) {
            return null;
        }

        return $this->urlForTranslation($page, $translation);
    }

    public function urlForTranslation(Page $page, PageTranslation $translation): string
    {
        return $page->type === 'home'
            ? route('pages.home', ['locale' => $translation->locale])
            : route('pages.show', ['locale' => $translation->locale, 'slug' => $translation->slug]);
    }

    /**
     * hreflang => absolute URL for every locale of this page, plus x-default.
     *
     * Every cluster is self-referencing and reciprocal because it is derived
     * from the same translation set on both sides; the sitemap uses this exact
     * method too so the two annotations can never drift apart.
     *
     * @return array<string, string>
     */
    public function alternateUrls(Page $page): array
    {
        $alternates = [];

        foreach ($page->translations as $translation) {
            $alternates[$translation->locale] = $this->urlForTranslation($page, $translation);
        }

        $default = config('site.default_locale', 'nl');

        if (isset($alternates[$default])) {
            $alternates['x-default'] = $alternates[$default];
        }

        return $alternates;
    }

    /**
     * Meta description with a locale-aware fallback. An empty description tag
     * is worse than none: it tells Google the page has nothing to say.
     */
    public function metaDescription(?PageTranslation $translation, string $locale): string
    {
        $description = trim((string) ($translation?->meta_description ?? ''));

        if ($description !== '') {
            return $description;
        }

        $intro = trim((string) ($translation?->intro ?? ''));

        if ($intro !== '') {
            return mb_strlen($intro) > 158
                ? rtrim(mb_substr($intro, 0, 155)) . '…'
                : $intro;
        }

        return config('site.tagline.' . $locale) ?? config('site.tagline.nl');
    }

    public function metaTitle(?PageTranslation $translation): string
    {
        $title = trim((string) ($translation?->meta_title ?? ''));

        if ($title !== '') {
            return $title;
        }

        $pageTitle = trim((string) ($translation?->title ?? ''));

        return $pageTitle !== ''
            ? $pageTitle . ' | ' . config('site.name')
            : config('site.name');
    }

    // ── Structured data ─────────────────────────────────────────────────────

    public function organizationId(): string
    {
        return rtrim(url('/'), '/') . '/#organization';
    }

    public function websiteId(): string
    {
        return rtrim(url('/'), '/') . '/#website';
    }

    /**
     * The LocalBusiness node. Emitted once per page as part of the graph.
     *
     * Deliberately omitted: aggregateRating / review. Google does not allow
     * self-serving review markup on LocalBusiness or Organization, and marking
     * up our own testimonials would risk a structured-data manual action.
     *
     * @return array<string, mixed>
     */
    public function organizationNode(string $locale): array
    {
        $contact = config('site.contact');
        $address = config('site.address');
        $center = config('site.service_area_center');

        $node = [
            '@type' => ['LocalBusiness', 'HVACBusiness'],
            '@id' => $this->organizationId(),
            'name' => config('site.name'),
            'legalName' => config('site.legal_name', config('site.name')),
            'description' => config('site.tagline.' . $locale) ?? config('site.tagline.nl'),
            'url' => route('pages.home', ['locale' => config('site.default_locale', 'nl')]),
            'telephone' => $contact['phone_display'],
            'email' => $contact['email'],
            'logo' => [
                '@type' => 'ImageObject',
                '@id' => rtrim(url('/'), '/') . '/#logo',
                'url' => asset('assets/images/Logo.webp'),
                'caption' => config('site.name'),
            ],
            'image' => asset('assets/images/hero.png'),
            'priceRange' => '€€',
            'currenciesAccepted' => 'EUR',
            'vatID' => str_replace(' ', '', (string) config('site.vat_number')),
            'knowsLanguage' => ['nl', 'fr', 'en'],
        ];

        $postalAddress = $this->postalAddressNode();

        if ($postalAddress !== null) {
            $node['address'] = $postalAddress;
        }

        $areaServed = $this->areaServedNodes();

        if ($areaServed !== []) {
            $node['areaServed'] = $areaServed;
        }

        if (is_array($center) && isset($center['latitude'], $center['longitude'])) {
            $node['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $center['latitude'],
                'longitude' => $center['longitude'],
            ];
        }

        $openingHours = config('site.opening_hours', []);

        if (is_array($openingHours) && $openingHours !== []) {
            $node['openingHours'] = array_values($openingHours);
        }

        $sameAs = $this->sameAsUrls();

        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        $catalog = $this->offerCatalogNode($locale);

        if ($catalog !== null) {
            $node['hasOfferCatalog'] = $catalog;
        }

        return $node;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function postalAddressNode(): ?array
    {
        $address = config('site.address', []);

        $street = trim((string) ($address['street'] ?? ''));
        $locality = trim((string) ($address['locality'] ?? ''));

        // Nothing verifiable configured yet — emit no address at all rather
        // than a country-only stub that adds no local signal.
        if ($street === '' && $locality === '') {
            return null;
        }

        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $street !== '' ? $street : null,
            'addressLocality' => $locality !== '' ? $locality : null,
            'postalCode' => trim((string) ($address['postal_code'] ?? '')) ?: null,
            'addressRegion' => trim((string) ($address['region'] ?? '')) ?: null,
            'addressCountry' => $address['country'] ?? 'BE',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function areaServedNodes(): array
    {
        $nodes = [];

        foreach (config('site.service_areas', []) as $area) {
            $nodes[] = array_filter([
                '@type' => 'City',
                'name' => $area['name'],
                'postalCode' => $area['postal_code'] ?? null,
                'addressCountry' => 'BE',
            ]);
        }

        $center = config('site.service_area_center');

        if (is_array($center) && isset($center['latitude'], $center['longitude'], $center['radius_m'])) {
            $nodes[] = [
                '@type' => 'GeoCircle',
                'geoMidpoint' => [
                    '@type' => 'GeoCoordinates',
                    'latitude' => $center['latitude'],
                    'longitude' => $center['longitude'],
                ],
                'geoRadius' => (string) $center['radius_m'],
            ];
        }

        return $nodes;
    }

    /**
     * @return array<int, string>
     */
    public function sameAsUrls(): array
    {
        return array_values(array_filter([
            config('reviews.platforms.facebook.url'),
            config('reviews.platforms.trustpilot.url'),
        ]));
    }

    /**
     * Every active service as an offer, linked to its own page. Gives Google
     * an explicit machine-readable list of what this business actually does.
     *
     * @return array<string, mixed>|null
     */
    private function offerCatalogNode(string $locale): ?array
    {
        $items = [];

        foreach (config('services', []) as $service) {
            if (!($service['is_active'] ?? false)) {
                continue;
            }

            $translation = $service['translations'][$locale] ?? $service['translations']['nl'] ?? null;

            if ($translation === null) {
                continue;
            }

            $items[] = [
                '@type' => 'Offer',
                'itemOffered' => [
                    '@type' => 'Service',
                    'name' => $translation['title'],
                    'description' => $translation['description'],
                    'url' => route('pages.show', ['locale' => $locale, 'slug' => $translation['slug']]),
                    'provider' => ['@id' => $this->organizationId()],
                ],
            ];
        }

        if ($items === []) {
            return null;
        }

        return [
            '@type' => 'OfferCatalog',
            'name' => config('site.name'),
            'itemListElement' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function websiteNode(string $locale): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => $this->websiteId(),
            'url' => rtrim(url('/'), '/') . '/',
            'name' => config('site.name'),
            'publisher' => ['@id' => $this->organizationId()],
            'inLanguage' => $this->htmlLang($locale),
        ];
    }

    /**
     * @param  array<int, array{name: string, url: string|null}>  $breadcrumbs
     * @return array<string, mixed>
     */
    public function webPageNode(
        string $url,
        string $title,
        string $description,
        string $locale,
        bool $hasBreadcrumb,
        ?string $primaryImage = null
    ): array {
        $node = [
            '@type' => 'WebPage',
            '@id' => $url . '#webpage',
            'url' => $url,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => $this->websiteId()],
            'about' => ['@id' => $this->organizationId()],
            'inLanguage' => $this->htmlLang($locale),
        ];

        if ($primaryImage !== null) {
            $node['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $primaryImage,
            ];
        }

        if ($hasBreadcrumb) {
            $node['breadcrumb'] = ['@id' => $url . '#breadcrumb'];
        }

        return $node;
    }

    /**
     * @param  Collection<int, array{name: string, url: string|null}>|array<int, array{name: string, url: string|null}>  $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbNode(string $url, $crumbs): array
    {
        $items = [];
        $position = 1;

        foreach ($crumbs as $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $crumb['name'],
            ];

            // The last crumb is the current page: schema.org allows omitting
            // `item` there, and doing so avoids a self-referencing link.
            if (!empty($crumb['url'])) {
                $item['item'] = $crumb['url'];
            }

            $items[] = $item;
            $position++;
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => $url . '#breadcrumb',
            'itemListElement' => $items,
        ];
    }

    /**
     * @param  array<int, array{question: string, answer: string}>  $faqs
     * @return array<string, mixed>
     */
    public function faqNode(string $url, array $faqs): array
    {
        return [
            '@type' => 'FAQPage',
            '@id' => $url . '#faq',
            'mainEntity' => array_map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ], $faqs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceNode(
        string $url,
        string $name,
        string $description,
        string $locale,
        ?string $areaName = null
    ): array {
        $node = [
            '@type' => 'Service',
            '@id' => $url . '#service',
            'name' => $name,
            'description' => $description,
            'serviceType' => $name,
            'url' => $url,
            'provider' => ['@id' => $this->organizationId()],
            'inLanguage' => $this->htmlLang($locale),
        ];

        $node['areaServed'] = $areaName !== null
            ? [array_filter(['@type' => 'City', 'name' => $areaName, 'addressCountry' => 'BE'])]
            : $this->areaServedNodes();

        return $node;
    }

    /**
     * Wrap nodes in a single JSON-LD document.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    public function graph(array $nodes): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter($nodes)),
        ];
    }
}
