<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PoiCategory;

/** Turns a Terroir partner into the fields of a point of interest. */
final readonly class TerroirPartnerMapper
{
    /** The partner groups that are shown on a course, and the category each one gives its places. */
    public const array CATEGORY_BY_GROUP = [
        'agritourisme' => PoiCategory::Agritourism,
        'boulangerie-vaudoise' => PoiCategory::Bakery,
        'petites-epiceries' => PoiCategory::Grocery,
    ];

    /**
     * @param  array<string, mixed>  $partner
     * @return array{id: string, title: array{fr: string, de: string, en: string}, description: array{fr: string, de: string, en: string}|null, lat: float, lon: float, address: string|null, url: string|null, image_url: string|null, category: PoiCategory}|null
     *                                                                                                                                                                                                                                                              null when the partner has no name or no usable position
     */
    public function map(array $partner, PoiCategory $category): ?array
    {
        $id = $partner['id'] ?? null;
        $title = $this->text($partner['title'] ?? null) ?? $this->text($partner['company'] ?? null);
        $lat = is_numeric($partner['latitude'] ?? null) ? (float) $partner['latitude'] : 0.0;
        $lon = is_numeric($partner['longitude'] ?? null) ? (float) $partner['longitude'] : 0.0;

        if ($id === null || $title === null || ! $this->usable($lat, $lon)) {
            return null;
        }

        $content = $this->text($partner['content'] ?? null);

        return [
            'id' => 'terroir:'.$id,
            'title' => ['fr' => $title, 'de' => $title, 'en' => $title],
            'description' => $content === null ? null : ['fr' => $content, 'de' => $content, 'en' => $content],
            'lat' => $lat,
            'lon' => $lon,
            'address' => $this->address($partner),
            'url' => $this->url($partner['website'] ?? null),
            'image_url' => $this->text($partner['cover_image'] ?? null) ?? $this->text($partner['logo'] ?? null),
            'category' => $category,
        ];
    }

    /** Plain text: HTML tags removed, entities decoded, spaces tidied; null when nothing is left. */
    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = mb_trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $text === '' ? null : $text;
    }

    /** A position inside the borders' neighbourhood of Vaud: 0,0 and the like are missing data, not places. */
    private function usable(float $lat, float $lon): bool
    {
        return $lat >= 45.5 && $lat <= 48.0 && $lon >= 5.5 && $lon <= 10.6;
    }

    /**
     * @param  array<string, mixed>  $partner
     */
    private function address(array $partner): ?string
    {
        $parts = array_filter([$this->text($partner['address'] ?? null), $this->text($partner['postal_locality'] ?? null)]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function url(mixed $value): ?string
    {
        $url = $this->text($value);

        if ($url === null) {
            return null;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : 'https://'.$url;
    }
}
