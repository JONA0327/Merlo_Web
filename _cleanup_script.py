from pathlib import Path

path = Path(r"D:\Proyectos y Aplicaiones\Proyectos\Proyectos MERLO\Merlo_Web_Tickets\_test_hold_guest.php")
text = path.read_text(encoding="utf-8")

# Replace inline Request creations with the postToHold helper
replacements = [
    (
        """$req2 = Request::create("/viajes/{$trip->id}/asientos/{$seats[0]->id}/hold", 'POST', [
    '_token' => csrf_token(),
    'trip_type' => 'one_way',
]);
$req2->setLaravelSession(app('session.store'));
$res2 = $httpKernel->handle($req2);""",
        "$res2 = postToHold($httpKernel, $trip->id, $seats[0]->id, $csrfToken);"
    ),
    (
        """$req3 = Request::create("/viajes/{$trip->id}/asientos/{$seats[1]->id}/hold", 'POST', [
    '_token' => csrf_token(),
    'trip_type' => 'one_way',
]);
$req3->setLaravelSession(app('session.store'));
$res3 = $httpKernel->handle($req3);""",
        "$res3 = postToHold($httpKernel, $trip->id, $seats[1]->id, $csrfToken);"
    ),
    (
        """$reqGuest = Request::create("/viajes/{$trip->id}/asientos/{$seats[0]->id}/hold", 'POST', [
    '_token' => csrf_token(),
    'trip_type' => 'one_way',
]);
$reqGuest->setLaravelSession(app('session.store'));
$resGuest = $httpKernel->handle($reqGuest);""",
        "$resGuest = postToHold($httpKernel, $trip->id, $seats[0]->id, $csrfToken);"
    ),
    (
        """$reqUser = Request::create("/viajes/{$trip->id}/asientos/{$seats[0]->id}/hold", 'POST', [
    '_token' => csrf_token(),
    'trip_type' => 'one_way',
]);
$reqUser->setLaravelSession(app('session.store'));
$resUser = $httpKernel->handle($reqUser);""",
        "$resUser = postToHold($httpKernel, $trip->id, $seats[0]->id, $csrfToken);"
    ),
]

for old, new in replacements:
    if old in text:
        text = text.replace(old, new)
        print("Replaced one")
    else:
        print(f"NOT FOUND: {old[:80]}...")

path.write_text(text, encoding="utf-8")
print("Done")