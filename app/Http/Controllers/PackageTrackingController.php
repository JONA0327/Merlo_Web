<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\PackageGroup;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageTrackingController extends Controller
{
    public function show(Request $request): View
    {
        $code = strtoupper(trim((string) $request->query('code', '')));

        $data = ['code' => $code === '' ? null : $code, 'package' => null, 'group' => null, 'notFound' => false];

        if ($code === '') {
            return view('paqueteria.rastreo', $data);
        }

        $package = Package::where('tracking_code', $code)->first();

        if ($package) {
            return view('paqueteria.rastreo', [...$data, 'package' => $package]);
        }

        $group = PackageGroup::with('packages')->where('tracking_code', $code)->first();

        if ($group) {
            return view('paqueteria.rastreo', [
                ...$data,
                'group' => $group,
                'groupStatus' => Package::furthestStatus($group->packages),
                'groupFailedCount' => $group->packages->where('status', Package::STATUS_NO_ENTREGADO)->count(),
            ]);
        }

        return view('paqueteria.rastreo', [...$data, 'notFound' => true]);
    }
}
