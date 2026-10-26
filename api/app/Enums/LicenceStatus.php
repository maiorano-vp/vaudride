<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rights over an imported route. Only anything other than Unknown may be exported to the courses folder.
 */
enum LicenceStatus: string
{
    case Unknown = 'unknown';
    case PermissionGranted = 'permission_granted';
    case OpenLicence = 'open_licence';
    case OwnWork = 'own_work';
}
