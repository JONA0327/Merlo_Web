<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One global, never-resetting folio series for Paquetería (F-000001,
 * F-000002, ...) shared by both ways a folio gets handed out: pre-printed
 * in a "guía de folios" sheet, or minted live when a registration needs
 * more than what's already pre-printed and unclaimed. Either way it's the
 * same counter, so a single registration's folios are always contiguous.
 */
class PackageFolioSequence extends Model
{
    protected $fillable = ['next_value'];

    /**
     * Atomically reserves the next $qty folio numbers and returns them as
     * a contiguous range [first, last]. Locks the single counter row for
     * the duration of the transaction so concurrent reservations never
     * overlap.
     *
     * @return array{0: int, 1: int}
     */
    public static function reserve(int $qty): array
    {
        return DB::transaction(function () use ($qty) {
            $sequence = self::query()->lockForUpdate()->findOrFail(1);
            $first = $sequence->next_value;
            $last = $first + $qty - 1;

            $sequence->update(['next_value' => $last + 1]);

            return [$first, $last];
        });
    }

    public static function formatFolio(int $number): string
    {
        return 'F-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
