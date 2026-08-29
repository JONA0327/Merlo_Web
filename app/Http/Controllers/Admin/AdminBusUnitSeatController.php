<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusUnit;
use App\Models\BusUnitSeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBusUnitSeatController extends Controller
{
    public function sync(Request $request, BusUnit $busUnit): JsonResponse
    {
        $validated = $request->validate([
            'seats' => ['present', 'array'],
            'seats.*.id' => ['nullable', 'integer'],
            'seats.*.label' => ['required', 'string', 'max:12'],
            'seats.*.kind' => ['required', 'string', 'in:seat,object'],
            'seats.*.type' => ['required', 'string', 'in:normal,vip,disabled,door,stairs,driver,bathroom,table,other,outline,divider'],
            'seats.*.deck' => ['required', 'string', 'in:lower,upper'],
            'seats.*.shape' => ['required', 'string', 'in:rect,circle'],
            'seats.*.width' => ['required', 'integer', 'min:10', 'max:5000'],
            'seats.*.height' => ['required', 'integer', 'min:10', 'max:5000'],
            'seats.*.corner_radius' => ['required', 'integer', 'min:0', 'max:60'],
            'seats.*.border_width' => ['required', 'integer', 'min:1', 'max:8'],
            'seats.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'seats.*.allowed_trip_type' => ['nullable', 'string', 'in:both,one_way,round_trip'],
            'seats.*.pos_x' => ['required', 'numeric'],
            'seats.*.pos_y' => ['required', 'numeric'],
        ]);

        $existingIds = $busUnit->seats()->pluck('id');
        $payloadIds = collect($validated['seats'])->pluck('id')->filter()->values();

        $toDelete = $existingIds->diff($payloadIds);

        if (BusUnitSeat::whereIn('id', $toDelete)->whereHas('reservations')->exists()) {
            $taken = BusUnitSeat::whereIn('id', $toDelete)->whereHas('reservations')->first();

            return response()->json([
                'message' => "No se puede eliminar el asiento {$taken->label}: ya tiene una reserva.",
            ], 422);
        }

        DB::transaction(function () use ($busUnit, $validated, $toDelete) {
            BusUnitSeat::whereIn('id', $toDelete)->delete();

            foreach ($validated['seats'] as $seat) {
                $attributes = [
                    'label' => $seat['label'],
                    'kind' => $seat['kind'],
                    'type' => $seat['type'],
                    'deck' => $seat['deck'],
                    'shape' => $seat['shape'],
                    'width' => $seat['width'],
                    'height' => $seat['height'],
                    'corner_radius' => $seat['corner_radius'],
                    'border_width' => $seat['border_width'],
                    'color' => $seat['color'] ?? null,
                    'allowed_trip_type' => $seat['allowed_trip_type'] ?? 'both',
                    'pos_x' => $seat['pos_x'],
                    'pos_y' => $seat['pos_y'],
                ];

                if (! empty($seat['id'])) {
                    $busUnit->seats()->whereKey($seat['id'])->update($attributes);
                } else {
                    $busUnit->seats()->create($attributes);
                }
            }
        });

        return response()->json([
            'seats' => $busUnit->seats()->get(['id', 'label', 'kind', 'type', 'deck', 'shape', 'width', 'height', 'corner_radius', 'border_width', 'color', 'allowed_trip_type', 'pos_x', 'pos_y']),
        ]);
    }
}
