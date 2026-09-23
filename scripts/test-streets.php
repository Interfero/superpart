<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s = app(App\Services\StreetSuggestionService::class);

foreach (['Лен', 'Ленина', 'Чапае'] as $query) {
    $t = microtime(true);
    $r = $s->suggest('Казань', $query, 12);
    $ms = round((microtime(true) - $t) * 1000);
    echo $query.': '.$ms."ms — ".count($r)." results\n";
    foreach (array_slice($r, 0, 5) as $row) {
        echo '  - '.$row['label']."\n";
    }
}
