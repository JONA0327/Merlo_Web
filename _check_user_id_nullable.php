<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = collect(Schema::getColumns('seat_reservations'));
$u = $cols->firstWhere('name', 'user_id');
echo "user_id: type={$u['type']} nullable=" . ($u['nullable'] ? 'YES' : 'NO') . PHP_EOL;

$fks = collect(Schema::getForeignKeys('seat_reservations'))
    ->filter(fn ($fk) => in_array('user_id', $fk['columns'], true))
    ->values()
    ->all();
echo "FKs on user_id: " . json_encode($fks, JSON_PRETTY_PRINT) . PHP_EOL;
