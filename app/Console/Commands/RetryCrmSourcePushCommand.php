<?php

namespace App\Console\Commands;

use App\Models\ReferenceSource;
use App\Models\Source;
use App\Support\LocalSourceReferenceMirror;
use Illuminate\Console\Command;

/**
 * Догнать локальные источники без ID CRM (429 / обрыв сети).
 */
class RetryCrmSourcePushCommand extends Command
{
    protected $signature = 'sources:retry-crm-push {--limit=20 : Максимум источников за прогон}';

    protected $description = 'Повторно отправить в CRM локальные источники без levelion_source_id';

    public function handle(): int
    {
        if (! LocalSourceReferenceMirror::isActive()) {
            $this->warn('CRM не настроена');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $orphans = ReferenceSource::query()
            ->whereNotNull('local_source_id')
            ->where(function ($q) {
                $q->whereNull('levelion_source_id')
                    ->orWhere('levelion_source_id', 0);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $pushed = 0;
        $failed = 0;

        foreach ($orphans as $ref) {
            $local = Source::query()->find($ref->local_source_id);
            if (! $local) {
                $failed++;

                continue;
            }

            $fresh = LocalSourceReferenceMirror::sync($local);
            if ($fresh && $fresh->canPushToCrm()) {
                $pushed++;
            } else {
                $failed++;
            }
        }

        $this->info("orphans={$orphans->count()} pushed={$pushed} failed={$failed}");

        return self::SUCCESS;
    }
}
