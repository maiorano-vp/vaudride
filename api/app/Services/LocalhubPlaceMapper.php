<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PoiCategory;

/** Turns a place as the Local Hub API returns it into what the app keeps: a title, a position, a category. */
final readonly class LocalhubPlaceMapper
{
    /**
     * Local Hub's own sub-categories (asset categories) and what they are for a rider. Generic ones are refined by
     * the name afterwards. Categories missing here fall back to the name and the attributes.
     */
    private const array BY_SUBCATEGORY = [
        'restaurants' => 'food', 'otv_localprod' => 'food', 'otv_nightlife' => 'food', 'old_nightlife' => 'food',
        'j3l_restaurants' => 'food', 'j3l_bars' => 'food', 'j3l_products' => 'food',
        'gastronomy_wine' => 'wine', 'j3l_wines' => 'wine',
        'hotel' => 'lodging', 'holiday_apartment' => 'lodging', 'guesthouse' => 'lodging', 'campsite' => 'lodging',
        'dormitory_mountain_hut' => 'lodging', 'chalet' => 'lodging', 'otv_hotels' => 'lodging', 'accomodation' => 'lodging',
        'j3l_hotels' => 'lodging', 'j3l_camps' => 'lodging', 'j3l_bb' => 'lodging', 'j3l_group_accomodation' => 'lodging',
        'otv_shopping' => 'shop', 'shops' => 'shop',
        'otv_infra' => 'service', 'ots' => 'service', 'information' => 'service',
        'transports' => 'station',
        'culture' => 'culture', 'culture_heritage' => 'culture', 'otv_museums' => 'culture', 'j3l_museum' => 'culture',
        'j3l_theater_cinemas' => 'culture', 'j3l_guidedtour' => 'culture', 'j3l_patrimoine' => 'history',
        'nature' => 'nature', 'otv_parks' => 'nature', 'j3l_natural_sites' => 'nature', 'j3l_picnic' => 'nature',
        'sports_leisure' => 'activity', 'otv_summeracti' => 'activity', 'otv_winter' => 'activity', 'sport' => 'activity',
        'tourist_transports' => 'activity', 'otv_wellnes' => 'activity', 'family' => 'activity', 'j3l_water' => 'activity',
        'j3l_fun' => 'activity', 'j3l_various_sports' => 'activity', 'j3l_mountainbiking_poi' => 'activity', 'j3l_golf' => 'activity',
        'j3l_zoo' => 'activity', 'aventure-et-loisirs' => 'activity', 'atelier-et-initiation' => 'activity', 'j3l_group_offer' => 'activity',
    ];

    /** Width of the image served to the app (Local Hub scales it). */
    private const int IMAGE_WIDTH = 640;

    /** Not for riders: schools, business tourism. */
    private const array IGNORED_SUBCATEGORIES = ['otv_schools', 'otv_businesstourism'];

    /** What the name may still say about a generic category (a church among culture, a beach among activities). */
    private const array REFINED_BY_NAME = ['history', 'viewpoint', 'nature', 'water', 'station'];

    /**
     * @param  array<string, mixed>  $hit
     * @return array{id: string, title: array{fr: string, de: string, en: string}, summary: array{fr: string, de: string, en: string}|null, lat: float, lon: float, address: string|null, url: string|null, image_url: string|null, image_credit: string|null, facts: list<array{label: string, values: list<string>}>, category: string}|null null when the place has no id, no position, or is not for riders (schools…)
     */
    public function map(array $hit): ?array
    {
        $coords = $this->coordinates($hit);

        if (! isset($hit['id']) || $coords === null) {
            return null;
        }

        $title = $this->localized($hit['name'] ?? null) ?? ['fr' => '?', 'de' => '?', 'en' => '?'];
        $facts = $this->facts($hit);
        $facts[] = ['label' => 'catégorie', 'values' => array_values(array_filter([
            mb_strtolower((string) ($hit['rootAssetCategory']['slug'] ?? '')),
            mb_strtolower((string) ($hit['assetCategory']['slug'] ?? '')),
        ]))];
        $category = $this->classify($title['fr'], $facts);

        if ($category === null) {
            return null;
        }

        return [
            'id' => 'localhub:'.$hit['id'],
            'title' => $title,
            'summary' => $this->localized($hit['summary'] ?? null),
            'lat' => $coords['lat'],
            'lon' => $coords['lon'],
            'address' => $this->address($hit),
            'url' => $this->url($hit),
            ...$this->image($hit),
            'facts' => $facts,
            'category' => $category->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    public function isIgnored(array $hit): bool
    {
        return in_array(mb_strtolower((string) ($hit['assetCategory']['slug'] ?? '')), self::IGNORED_SUBCATEGORIES, true);
    }

    /**
     * The category of a place, or null when it is not for riders. Local Hub's own sub-category decides; the name
     * only refines a generic one, and replaces it when Local Hub gave none.
     *
     * @param  list<array{label: string, values: list<string>}>  $facts
     */
    public function classify(string $title, array $facts): ?PoiCategory
    {
        $slugs = array_merge([], ...array_column(array_filter($facts, fn (array $f): bool => $f['label'] === 'catégorie'), 'values'));
        $sub = $slugs !== [] ? end($slugs) : '';

        if (in_array($sub, self::IGNORED_SUBCATEGORIES, true)) {
            return null;
        }

        $byName = $this->categorize($title, $facts);
        $official = self::BY_SUBCATEGORY[$sub] ?? null;

        if ($official === null) {
            return $byName;
        }

        $category = PoiCategory::from($official);

        return in_array($category->value, ['culture', 'activity', 'nature'], true) && in_array($byName->value, self::REFINED_BY_NAME, true)
            ? $byName
            : $category;
    }

    /**
     * The category of a place read from its name and the attributes an editor ticked. The API gives no category on the places themselves, so it is read from the name
     * (a hotel, a restaurant, a pharmacy…) and from the attributes an editor ticked (panorama, establishment type,
     * theme…). Culture when nothing says otherwise.
     *
     * @param  list<array{label: string, values: list<string>}>  $facts
     */
    public function categorize(string $title, array $facts): PoiCategory
    {
        $name = mb_strtolower($title);
        $named = fn (string $words): bool => preg_match('/(?<![\p{L}])('.$words.')(?![\p{L}])/u', $name) === 1;
        $label = fn (string $needle): bool => array_filter($facts, fn (array $f): bool => str_contains($f['label'], $needle)) !== [];
        $values = fn (string $of): string => implode(' ', array_merge([], ...array_column(array_filter($facts, fn (array $f): bool => $f['label'] === $of), 'values')));
        $theme = $values('thématique');
        $category = $values('catégorie');

        return match (true) {
            preg_match('/^gare(?![\p{L}])/u', $name) === 1 => PoiCategory::Station,
            ($named('hôtel|hotel|camping|auberge|gîtes?|gites?|b&b|chambres? d\'hôtes?|apparthôtel|appartements?|apartments?|hostel|youth hostel|logements?') && ! $named('hôtel de ville|hôtel-de-ville'))
                || $label('hébergement') || $label('nombre de chambres') || $label('étoiles')
                || preg_match('/(?<![\p{L}])(hébergement|hebergement|accommodation|lodging)(?![\p{L}])/u', $category) === 1 => PoiCategory::Lodging,
            $named('pharmacie|migros|coop|denner|boucherie|charcuterie|épicerie|epicerie|supermarché|magasin|droguerie|kiosque|volg|manor|aldi|lidl') => PoiCategory::Shop,
            $named('vélos?|velos?|cycles?|bike|e-bike|vtt') => PoiCategory::Bike,
            $named('fontaine') => PoiCategory::Water,
            $label('oenotourisme') || $named('caves?|caveau|caveaux|vins?|vigneron(ne)?s?|domaine|clos|vinorama|winery|vignoble') || preg_match('/(?<![\p{L}])(vin|vins|vignoble|vigneron)(?![\p{L}])/u', $theme) === 1 => PoiCategory::Wine,
            $label('type d\'établissement') || preg_match('/(?<![\p{L}])(restaurant|gastronomie)(?![\p{L}])/u', $category) === 1
                || $named('restaurant|bistrot|bistro|brasserie|café|cafe|buvette|pizz\w*|sushi|burrito|boulangerie|pâtisserie|patisserie|confiserie|fromagerie|laiterie|traiteur|snack|kebab|tea-?room|salon de thé') => PoiCategory::Food,
            $label('panorama') || $named('belvédère|belvedere|point de vue|panorama|photo spot') => PoiCategory::Viewpoint,
            $named('église|eglise|château|chateau|temple|abbaye|chapelle|cathédrale|cathedrale|tour|ruines?|fort') || preg_match('/(?<![\p{L}])(patrimoine|histoire|historique)(?![\p{L}])/u', $theme) === 1 => PoiCategory::History,
            preg_match('/(?<![\p{L}])(savoureux|gastronomie|terroir)(?![\p{L}])/u', $theme) === 1 => PoiCategory::Food,
            $label('activité nature') || $named('plage|lac|cascade|gorges?|grotte|sentier|pique-nique|parc naturel') || preg_match('/(?<![\p{L}])nature(?![\p{L}])/u', $theme) === 1 => PoiCategory::Nature,
            default => PoiCategory::Culture,
        };
    }

    /**
     * The attributes that carry a value, as a lowercase French label and the labels of the chosen options.
     *
     * @param  array<string, mixed>  $hit
     * @return list<array{label: string, values: list<string>}>
     */
    public function facts(array $hit): array
    {
        $facts = [];

        foreach ($hit['attributes'] ?? [] as $attribute) {
            $value = $attribute['value'] ?? null;

            if ($value === null || $value === [] || $value === false || $value === '' || $value === 0) {
                continue;
            }

            $options = isset($value['key']) ? [$value] : (is_array($value) ? $value : []);
            $labels = [];

            foreach ($options as $option) {
                $name = is_array($option) ? ($option['json']['label']['fr'] ?? null) : null;

                if (is_string($name)) {
                    $labels[] = mb_strtolower($name);
                }
            }

            $label = $attribute['label']['fr'] ?? (is_array($attribute['label'] ?? null) ? reset($attribute['label']) : null);
            $facts[] = ['label' => mb_strtolower((string) $label), 'values' => $labels];
        }

        return $facts;
    }

    /**
     * The cover image of a place: the one flagged as cover (else the first), as a scaled copy served by Local Hub
     * (a few hundred KB instead of the original's megabytes) and its copyright line. Documents such as PDFs are no image.
     *
     * @param  array<string, mixed>  $hit  a place, or a tour: both carry their pictures in `images`
     * @param  int|null  $width  the width of the copy, in pixels (a bit more than the place photos for a course card)
     * @return array{image_url: string|null, image_credit: string|null}
     */
    public function image(array $hit, ?int $width = null): array
    {
        $cover = $this->orderedImages($hit)[0] ?? null;

        if ($cover === null) {
            return ['image_url' => null, 'image_credit' => null];
        }

        $copy = $this->scaledCopy($cover, $width ?? self::IMAGE_WIDTH);

        return ['image_url' => $copy['url'], 'image_credit' => $copy['credit']];
    }

    /**
     * All the pictures of a place or tour, the cover first then the others in their order, as scaled copies with their
     * copyright line: what a gallery shows.
     *
     * @param  array<string, mixed>  $hit
     * @return list<array{url: string, credit: string|null}>
     */
    public function images(array $hit, int $width, int $limit = 12): array
    {
        return array_map(fn (array $image): array => $this->scaledCopy($image, $width), array_slice($this->orderedImages($hit), 0, $limit));
    }

    /**
     * The valid pictures (documents such as PDFs are no picture), the cover first, the others by their order.
     *
     * @param  array<string, mixed>  $hit
     * @return list<array<string, mixed>>
     */
    private function orderedImages(array $hit): array
    {
        $images = array_values(array_filter(
            $hit['images'] ?? [],
            fn (mixed $image): bool => is_array($image)
                && isset($image['id'])
                && preg_match('/\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i', (string) ($image['url'] ?? '')) === 1,
        ));
        usort($images, fn (array $a, array $b): int => ((int) ($a['sort'] ?? 0)) <=> ((int) ($b['sort'] ?? 0)));
        $cover = collect($images)->first(fn (array $image): bool => (bool) ($image['is_cover'] ?? false)) ?? ($images[0] ?? null);

        return $cover === null ? [] : [$cover, ...array_values(array_filter($images, fn (array $image): bool => $image['id'] !== $cover['id']))];
    }

    /**
     * @param  array<string, mixed>  $image
     * @return array{url: string, credit: string|null}
     */
    private function scaledCopy(array $image, int $width): array
    {
        $credit = mb_trim((string) ($image['copyright'] ?? ''));

        return [
            'url' => mb_rtrim((string) config('services.localhub.url'), '/').'/api/image/'.$image['id'].'/scale/'.$width,
            'credit' => $credit === '' ? null : $credit,
        ];
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array{lat: float, lon: float}|null
     */
    private function coordinates(array $hit): ?array
    {
        foreach ([$hit['location'] ?? [], $hit['_geo'] ?? [], $hit] as $source) {
            $lat = $source['lat'] ?? $source['latitude'] ?? null;
            $lon = $source['lng'] ?? $source['lon'] ?? $source['longitude'] ?? null;

            if (is_numeric($lat) && is_numeric($lon)) {
                return ['lat' => (float) $lat, 'lon' => (float) $lon];
            }
        }

        return null;
    }

    private function categoryText(mixed $category): string
    {
        if (! is_array($category)) {
            return is_string($category) ? $category : '';
        }

        return mb_trim(($category['slug'] ?? '').' '.(is_array($category['name'] ?? null) ? implode(' ', $category['name']) : ($category['name'] ?? '')));
    }

    /**
     * A value that is either one string or a map by language, as {fr, de, en}.
     *
     * @return array{fr: string, de?: string, en?: string}|null
     */
    private function localized(mixed $value): ?array
    {
        if (is_string($value) && $value !== '') {
            return ['fr' => $value, 'de' => $value, 'en' => $value];
        }

        if (! is_array($value)) {
            return null;
        }

        $fr = $value['fr'] ?? $value['fr_CH'] ?? null;
        $fr = is_string($fr) && $fr !== '' ? $fr : (is_string(reset($value)) ? (string) reset($value) : null);

        if ($fr === null) {
            return null;
        }

        return [
            'fr' => $fr,
            'de' => is_string($value['de'] ?? null) && $value['de'] !== '' ? $value['de'] : $fr,
            'en' => is_string($value['en'] ?? null) && $value['en'] !== '' ? $value['en'] : $fr,
        ];
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function url(array $hit): ?string
    {
        $candidates = [];

        foreach ($hit['links'] ?? [] as $link) {
            $url = $link['url'] ?? null;
            $candidates[] = is_array($url) ? ($url['fr'] ?? reset($url) ?: null) : $url;
        }

        $candidates[] = $hit['location']['website'] ?? null;

        foreach ($candidates as $url) {
            if (is_string($url) && preg_match('#^https?://#i', $url) === 1) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function address(array $hit): ?string
    {
        $location = $hit['location'] ?? null;

        if (! is_array($location)) {
            return null;
        }

        $line = mb_trim(implode(', ', array_filter([
            $location['address'] ?? null,
            mb_trim(($location['postcode'] ?? '').' '.($location['city'] ?? '')),
        ])));

        return $line === '' ? null : $line;
    }
}
