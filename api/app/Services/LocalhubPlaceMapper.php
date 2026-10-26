<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PoiCategory;

/** Turns a place as the Local Hub API returns it into what the app keeps: a title, a position, a category. */
final readonly class LocalhubPlaceMapper
{
    /**
     * @param  array<string, mixed>  $hit
     * @return array{id: string, title: array{fr: string, de: string, en: string}, summary: array{fr: string, de: string, en: string}|null, lat: float, lon: float, address: string|null, url: string|null, facts: list<array{label: string, values: list<string>}>, category: string}|null null when the place has no id or no position
     */
    public function map(array $hit): ?array
    {
        $coords = $this->coordinates($hit);

        if (! isset($hit['id']) || $coords === null) {
            return null;
        }

        $title = $this->localized($hit['name'] ?? null) ?? ['fr' => '?', 'de' => '?', 'en' => '?'];
        $facts = $this->facts($hit);
        $facts[] = ['label' => 'catégorie', 'values' => [mb_strtolower($this->categoryText($hit['rootAssetCategory'] ?? null).' '.$this->categoryText($hit['assetCategory'] ?? null))]];

        return [
            'id' => 'localhub:'.$hit['id'],
            'title' => $title,
            'summary' => $this->localized($hit['summary'] ?? null),
            'lat' => $coords['lat'],
            'lon' => $coords['lon'],
            'address' => $this->address($hit),
            'url' => $this->url($hit),
            'facts' => $facts,
            'category' => $this->categorize($title['fr'], $facts)->value,
        ];
    }

    /**
     * The category of a place. The API gives no category on the places themselves, so it is read from the name
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
