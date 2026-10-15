<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Cliente de paquetería, identificado por su teléfono (últimos 10 dígitos). */
class PackageClient extends Model
{
    protected $fillable = ['name', 'phone', 'email'];

    public static function normalizePhone(string $phone): string
    {
        return substr(preg_replace('/\D/', '', $phone), -10);
    }

    /** "4441234567" → "444 123 4567" (formato del campo de captura). */
    public function getPhoneFormattedAttribute(): string
    {
        if (! $this->phone) {
            return 'Sin teléfono';
        }

        return substr($this->phone, 0, 3).' '.substr($this->phone, 3, 3).' '.substr($this->phone, 6);
    }

    /** Busca/crea por teléfono; el nombre queda como el último capturado. */
    public static function forRegistration(string $name, string $phone): self
    {
        return static::updateOrCreate(['phone' => static::normalizePhone($phone)], ['name' => $name]);
    }

    /** Cliente sin teléfono (paquetes entregados que nunca se registraron), por nombre. */
    public static function forName(string $name): self
    {
        return static::firstOrCreate(['name' => $name, 'phone' => null]);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(PackageGroup::class);
    }

    /**
     * Envíos del cliente con algo por cobrar. Solo cuentan los que ya se
     * entregaron (total o parcialmente): el cobro se hace al entregar.
     *
     * @return Collection<int, Package|PackageGroup>
     */
    public function pendingUnits(): Collection
    {
        $groups = $this->groups()->with('packages')->get()
            ->filter(fn (PackageGroup $g) => $g->packages->contains('status', Package::STATUS_ENTREGADO));
        $packages = $this->packages()->whereNull('package_group_id')->where('status', Package::STATUS_ENTREGADO)->get();

        return $groups->concat($packages)->filter(fn ($u) => $u->pendingAmount() > 0)->values();
    }

    public function pendingAmount(): float
    {
        return (float) $this->pendingUnits()->sum(fn ($u) => $u->pendingAmount());
    }
}
