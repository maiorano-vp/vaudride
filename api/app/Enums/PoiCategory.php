<?php

declare(strict_types=1);

namespace App\Enums;

enum PoiCategory: string
{
    case Viewpoint = 'viewpoint';
    case Culture = 'culture';
    case History = 'history';
    case Nature = 'nature';
    case Water = 'water';
    case Wine = 'wine';
    case Food = 'food';
    case Lodging = 'lodging';
    case Bike = 'bike';
    case Station = 'station';

    /**
     * The category of an OpenStreetMap element, from its tags; null when it is not worth a rider's attention.
     *
     * @param  array<string, string>  $tags
     */
    public static function fromOsmTags(array $tags): ?self
    {
        return match (true) {
            ($tags['tourism'] ?? null) === 'viewpoint' => self::Viewpoint,
            in_array($tags['tourism'] ?? null, ['attraction', 'artwork', 'museum', 'gallery'], true) => self::Culture,
            isset($tags['historic']) => self::History,
            in_array($tags['natural'] ?? null, ['peak', 'spring', 'waterfall', 'cave_entrance'], true),
            in_array($tags['man_made'] ?? null, ['tower', 'lighthouse', 'observation'], true) => self::Nature,
            in_array($tags['tourism'] ?? null, ['hotel', 'hostel', 'guest_house', 'camp_site', 'chalet', 'apartment', 'alpine_hut'], true) => self::Lodging,
            ($tags['amenity'] ?? null) === 'bicycle_repair_station', ($tags['shop'] ?? null) === 'bicycle' => self::Bike,
            ($tags['railway'] ?? null) === 'station' => self::Station,
            ($tags['amenity'] ?? null) === 'drinking_water' => self::Water,
            ($tags['craft'] ?? null) === 'winery', ($tags['shop'] ?? null) === 'wine' => self::Wine,
            in_array($tags['shop'] ?? null, ['farm', 'cheese'], true),
            in_array($tags['amenity'] ?? null, ['cafe', 'restaurant'], true) => self::Food,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Viewpoint => 'Point de vue',
            self::Culture => 'Culture',
            self::History => 'Histoire',
            self::Nature => 'Nature',
            self::Water => 'Eau potable',
            self::Wine => 'Vin',
            self::Food => 'Terroir et restauration',
            self::Lodging => 'Hébergement',
            self::Bike => 'Service vélo',
            self::Station => 'Gare',
        };
    }
}
