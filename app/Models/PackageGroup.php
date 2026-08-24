<?php

namespace App\Models;

use Database\Factories\PackageGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageGroup extends Model
{
    /** @use HasFactory<PackageGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'client_name',
        'client_email',
        'total_price',
        'tracking_code',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
        ];
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A short, unambiguous (no 0/O/1/I) code a client can read off an email
     * or type by hand — retried until it doesn't collide with an existing one.
     */
    public static function generateTrackingCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

        do {
            $code = 'MG-';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('tracking_code', $code)->exists());

        return $code;
    }
}
