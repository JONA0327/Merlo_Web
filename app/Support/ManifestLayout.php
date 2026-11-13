<?php

namespace App\Support;

/**
 * Per-unit drawing of the printed "Lista de asientos" (PDF) only — the seat
 * map in the editor and in the guides is never touched by this.
 *
 * Some buses have things the list should show next to the seats, as on the
 * paper sheet: the stairs and the toilet. A block of 4 consecutive seats (the
 * list's unit, see manifiesto-pdf) can be drawn "stacked": its first two seats
 * on one line, the other two right under them, and a labelled box on the right
 * spanning both lines (e.g. ESCALERAS, WC).
 */
final class ManifestLayout
{
    /**
     * Unit name => deck => label of the FIRST seat of the 4-seat block => text of
     * the box drawn to the right of that block.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const STACKED_BLOCKS = [
        '5740' => [
            'upper' => [
                '9' => 'ESCALERAS',  // seats 9-10 / 11-12, stairs on their right
                '45' => 'WC',        // seats 45-46 / 47-48, toilet on their right
            ],
        ],
    ];

    /**
     * @return array<string, array<string, string>> deck => [first seat label => box text]
     */
    public static function stackedBlocks(?string $unitName): array
    {
        return self::STACKED_BLOCKS[(string) $unitName] ?? [];
    }
}
