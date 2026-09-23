<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Developer-only метрики синхронизации (ТЗ §13) — без PII и секретов.
 */
class SyncMonitorController extends Controller
{
    public function index(): View
    {
        $health = Cache::get('superpart_sync_health_last');
        $full = Cache::get('superpart_full_reconcile_last');
        $cursor = Cache::get('superpart_crm_changes_cursor');

        $inbox = [
            'pending' => 0,
            'rejected' => 0,
            'applied' => 0,
            'duplicate' => 0,
            'oldest_pending_age_sec' => 0,
        ];
        if (Schema::hasTable('sync_inbox')) {
            $inbox['pending'] = (int) DB::table('sync_inbox')->whereIn('result', ['received', 'pending'])->count();
            $inbox['rejected'] = (int) DB::table('sync_inbox')->where('result', 'rejected')->count();
            $inbox['applied'] = (int) DB::table('sync_inbox')->where('result', 'applied')->count();
            $inbox['duplicate'] = (int) DB::table('sync_inbox')->whereIn('result', ['duplicate', 'stale'])->count();
            $oldest = DB::table('sync_inbox')
                ->whereIn('result', ['received', 'pending'])
                ->orderBy('received_at')
                ->value('received_at');
            $inbox['oldest_pending_age_sec'] = $oldest ? now()->diffInSeconds(\Carbon\Carbon::parse($oldest)) : 0;
        }

        $deadLetter = Schema::hasTable('sync_dead_letter')
            ? (int) DB::table('sync_dead_letter')->count()
            : 0;

        $discrepancies = Schema::hasTable('financial_discrepancies')
            ? (int) DB::table('financial_discrepancies')->where('status', 'open')->count()
            : 0;

        $excluded = Schema::hasColumn('orders', 'excluded_at')
            ? (int) DB::table('orders')->whereNotNull('excluded_at')->count()
            : 0;

        $history = Schema::hasTable('order_source_history')
            ? DB::table('order_source_history')->orderByDesc('id')->limit(40)->get()
            : collect();

        $runs = Schema::hasTable('reconcile_runs')
            ? DB::table('reconcile_runs')->orderByDesc('id')->limit(20)->get()
            : collect();

        return view('developer.sync-monitor', [
            'health' => is_array($health) ? $health : null,
            'full' => is_array($full) ? $full : null,
            'cursor' => $cursor,
            'inbox' => $inbox,
            'deadLetter' => $deadLetter,
            'discrepancies' => $discrepancies,
            'excluded' => $excluded,
            'history' => $history,
            'runs' => $runs,
        ]);
    }
}
