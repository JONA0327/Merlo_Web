<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An additional evidence photo of a package registration (the first one is
 * kept in photo_path on the package / group). Belongs to the lone package or,
 * when the registration was bundled, to its PackageGroup.
 */
class PackagePhoto extends Model
{
    protected $fillable = [
        'package_id',
        'package_group_id',
        'path',
    ];
}
