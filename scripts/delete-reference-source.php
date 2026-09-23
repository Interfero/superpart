<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = (int) ($argv[1] ?? 0);
if ($id < 1) {
    fwrite(STDERR, "Usage: php delete-reference-source.php <id>\n");
    exit(1);
}

DB::table('user_allowed_reference_sources')->where('reference_source_id', $id)->delete();
DB::table('partner_phones')->where('reference_source_id', $id)->update(['reference_source_id' => null]);
$deleted = DB::table('reference_sources')->where('id', $id)->delete();

echo json_encode(['deleted' => $deleted], JSON_UNESCAPED_UNICODE).PHP_EOL;
