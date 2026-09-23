<?php

use App\Models\Order;
use App\Support\OrderCrmIdSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        while (true) {
            $row = DB::table('orders')
                ->whereNotNull('levelion_order_id')
                ->whereColumn('id', '!=', 'levelion_order_id')
                ->orderBy('id')
                ->first(['id', 'levelion_order_id']);

            if (! $row) {
                break;
            }

            $order = Order::query()->find($row->id);

            if (! $order) {
                break;
            }

            OrderCrmIdSync::align($order, (int) $row->levelion_order_id);
        }

        OrderCrmIdSync::bumpAutoIncrement();
    }

    public function down(): void
    {
        // Необратимо: старые локальные id не восстанавливаем.
    }
};
