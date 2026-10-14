<?php

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    public const STATUS_SIN_ASIGNAR = 'sin_asignar';

    public const STATUS_RECOLECTADO = 'recolectado';

    public const STATUS_EN_RECORRIDO = 'en_recorrido';

    public const STATUS_ENTREGADO = 'entregado';

    public const STATUS_NO_ENTREGADO = 'no_entregado';

    public const STATUSES = [
        self::STATUS_SIN_ASIGNAR,
        self::STATUS_RECOLECTADO,
        self::STATUS_EN_RECORRIDO,
        self::STATUS_ENTREGADO,
        self::STATUS_NO_ENTREGADO,
    ];

    /**
     * Still moving through the pipeline — this is the "Activos" view.
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_SIN_ASIGNAR,
        self::STATUS_RECOLECTADO,
        self::STATUS_EN_RECORRIDO,
    ];

    /**
     * Closed out, one way or another — this is the permanent "Historial"
     * record. Nothing in the app ever deletes a package once it leaves
     * sin_asignar, so these rows accumulate forever as the delivery log.
     */
    public const HISTORY_STATUSES = [
        self::STATUS_ENTREGADO,
        self::STATUS_NO_ENTREGADO,
    ];

    protected $fillable = [
        'tracking_code',
        'folio_number',
        'status',
        'client_name',
        'client_email',
        'phone',
        'price',
        'photo_path',
        'package_group_id',
        'generated_by',
        'collected_by',
        'collected_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'collected_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Every route that binds a Package does so by its tracking code — the
     * QR label and the scan URL both carry the code, never the numeric id.
     */
    public function getRouteKeyName(): string
    {
        return 'tracking_code';
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PackageGroup::class, 'package_group_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function isGrouped(): bool
    {
        return $this->package_group_id !== null;
    }

    /**
     * Once a package is bundled into a group, the group is the single
     * source of truth for who/what was charged — the package's own
     * client_name/client_email/price columns are cleared at that point.
     */
    public function displayClientName(): ?string
    {
        return $this->isGrouped() ? $this->group->client_name : $this->client_name;
    }

    public function displayClientEmail(): ?string
    {
        return $this->isGrouped() ? $this->group->client_email : $this->client_email;
    }

    public function displayPrice(): ?string
    {
        return $this->isGrouped() ? $this->group->total_price : $this->price;
    }

    public function displayPhone(): ?string
    {
        return $this->isGrouped() ? $this->group->phone : $this->phone;
    }

    public function displayPhotoPath(): ?string
    {
        return $this->isGrouped() ? $this->group->photo_path : $this->photo_path;
    }

    public function getFolioLabelAttribute(): ?string
    {
        return $this->folio_number === null ? null : PackageFolioSequence::formatFolio($this->folio_number);
    }

    /**
     * A short, unambiguous (no 0/O/1/I) code a client can read off an email
     * or type by hand — retried until it doesn't collide with an existing one.
     */
    public static function generateTrackingCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

        do {
            $code = 'MP-';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('tracking_code', $code)->exists());

        return $code;
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
