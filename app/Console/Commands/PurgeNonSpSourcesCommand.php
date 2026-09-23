<?php

namespace App\Console\Commands;

use App\Support\LeaveSuperpartCatalog;
use Illuminate\Console\Command;

class PurgeNonSpSourcesCommand extends Command
{
    protected $signature = 'sources:purge-non-sp';

    protected $description = 'Скрыть не-SP источники (листовки и снятые с каталога) и сторнировать невыплаченное';

    public function handle(): int
    {
        $stats = LeaveSuperpartCatalog::hideNonSpAndLeaflets();
        $this->info(json_encode($stats, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
