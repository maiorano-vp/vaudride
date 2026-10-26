<?php

declare(strict_types=1);

namespace App\Enums;

/** Climb categories by "length (m) × average grade (%)", the usual Strava-style thresholds. */
enum ClimbCategory: string
{
    case Cat4 = 'cat4';
    case Cat3 = 'cat3';
    case Cat2 = 'cat2';
    case Cat1 = 'cat1';
    case Hc = 'hc';

    public static function fromScore(float $score): ?self
    {
        return match (true) {
            $score >= 80_000 => self::Hc,
            $score >= 64_000 => self::Cat1,
            $score >= 32_000 => self::Cat2,
            $score >= 16_000 => self::Cat3,
            $score >= 8_000 => self::Cat4,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Hc => 'Hors catégorie',
            default => 'Catégorie '.mb_substr($this->value, 3),
        };
    }
}
