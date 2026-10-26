<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportSource: string
{
    case GpxUpload = 'gpx_upload';
    case Komoot = 'komoot';
}
