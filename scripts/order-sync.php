<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\LevelionApiService;

$id = (int) ($argv[1] ?? 46);
$order = Order::query()->findOrFail($id);

app(LevelionApiService::class)->syncOrderStatusFromCrmIfConfigured($order->fresh());

echo "status=" . Order::query()->find($id)->status . "\n";
