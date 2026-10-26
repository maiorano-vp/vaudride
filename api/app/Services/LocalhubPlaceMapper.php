<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PoiCategory;

/** Turns a place as the Local Hub API returns it into what the app keeps: a title, a position, a category. */
final readonly class LocalhubPlaceMapper
{
    /**
     * @param  array<string, mixed>  $hit
     * @return array{id: string, title: array{fr: string, de: string, en: string}, summary: array{fr: string, de: string, en: string}|null, lat: float, lon: float, address: string|null, url: string|null, category: string}|null null when the place has no id or no position
     */
    public function map(array $hit): ?array
    {
        $coords = $this->coordinates($hit);

        if (! isset($hit['id']) || $coords === null) {
            return null;
        }

        return [
            'id' => 'localhub:'.$hit['id'],
            'title' => $this->localized($hit['name'] ?? null) ?? ['fr' => '?', 'de' => '?', 'en' => '?'],
            'summary' => $this->localized($hit['summary'] ?? null),
            'lat' => $coords['lat'],
            'lon' => $coords['lon'],
            'address' => $this->address($hit),
            'url' => $this->url($hit),
            'category' => $this->category($hit)->value,
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

    /**
     * The category of a place. The API gives no category on the places themselves: it is read from the attributes
     * an editor ticked (panorama, establishment type, theme…), and from the asset category when there is one.
     * Culture when nothing says otherwise.
     *
     * @param  array<string, mixed>  $hit
     */
    private function category(array $hit): PoiCategory
    {
        $facts = $this->facts($hit);
        $label = fn (string $needle): bool => array_filter($facts, fn (array $f): bool => str_contains($f['label'], $needle)) !== [];
        $theme = implode(' ', array_merge(...array_column(array_filter($facts, fn (array $f): bool => $f['label'] === 'thématique'), 'values') ?: [[]]));
        $category = mb_strtolower($this->categoryText($hit['rootAssetCategory'] ?? null).' '.$this->categoryText($hit['assetCategory'] ?? null));

        return match (true) {
            $label('hébergement') || $label('nombre de chambres') || $label('étoiles') || preg_match('/\b(hébergement|hebergement|hôtel|hotel|camping|auberge|gîte|gite|hostel|accommodation|lodging)\b/u', $category) === 1 => PoiCategory::Lodging,
            $label('oenotourisme') || preg_match('/\b(vin|vins|vignoble|vigneron)\b/u', $theme) === 1 => PoiCategory::Wine,
            $label('type d\'établissement') || preg_match('/\b(restaurant|gastronomie)\b/u', $category) === 1 => PoiCategory::Food,
            $label('panorama') => PoiCategory::Viewpoint,
            preg_match('/\b(patrimoine|histoire|historique)\b/u', $theme) === 1 => PoiCategory::History,
            preg_match('/\b(savoureux|gastronomie|terroir)\b/u', $theme) === 1 => PoiCategory::Food,
            $label('activité nature') || preg_match('/\bnature\b/u', $theme) === 1 => PoiCategory::Nature,
            default => PoiCategory::Culture,
        };
    }

    /**
     * The attributes that carry a value, as a lowercase French label and the labels of the chosen options.
     *
     * @param  array<string, mixed>  $hit
     * @return list<array{label: string, values: list<string>}>
     */
    private function facts(array $hit): array
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
