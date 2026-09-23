<?php

namespace App\Console\Commands;

use App\Support\UserBalance;
use Illuminate\Console\Command;

class BackfillOrderClosedLocalCommand extends Command
{
    protected $signature = 'orders:backfill-closed-local {--limit=500 : Максимум заявок за прогон}';

    protected $description = 'Догон closed_local у заявок с начислением (иначе залипает «доступно к выводу»)';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $fixed = UserBalance::backfillMissingClosedLocal($limit);
        $this->info("Исправлено заявок: {$fixed}");

        return self::SUCCESS;
    }
}
