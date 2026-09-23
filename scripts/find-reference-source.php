<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = DB::table('reference_sources')
    ->select('id', 'name', 'levelion_source_id', 'local_source_id')
    ->where('name', 'like', '%удал%')
    ->orWhere('name', 'like', '%тест%')
    ->orWhere('name', 'like', '%Тест%')
    ->orWhere('name', 'like', '%снос%')
    ->orderByDesc('id')
    ->get();

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
