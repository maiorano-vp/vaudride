<?php

declare(strict_types=1);

namespace App\Enums;

/** The steps from a virtual ride towards a real one that are counted, anonymously, per course and per day. */
enum CourseActionType: string
{
    case GpxDownload = 'gpx_download';
    case GpxShare = 'gpx_share';
    case TransitStart = 'transit_start';
    case TransitEnd = 'transit_end';
    case MapStart = 'map_start';
    case PlaceLink = 'place_link';
    case LodgingLink = 'lodging_link';
    case DeclaredReal = 'declared_real';
}
