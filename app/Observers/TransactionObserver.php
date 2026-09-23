<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Support\UserBalance;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        UserBalance::sync((int) $transaction->user_id);
    }

    public function updated(Transaction $transaction): void
    {
        UserBalance::sync((int) $transaction->user_id);

        if ($transaction->wasChanged('user_id')) {
            UserBalance::sync((int) $transaction->getOriginal('user_id'));
        }
    }

    public function deleted(Transaction $transaction): void
    {
        UserBalance::sync((int) $transaction->user_id);
    }
}
