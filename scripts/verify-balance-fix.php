<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo class_exists(App\Observers\TransactionObserver::class) ? "observer_ok\n" : "observer_missing\n";
echo class_exists(App\Support\UserBalance::class) ? "balance_ok\n" : "balance_missing\n";

$partner = App\Models\User::find(8);
$dev = App\Models\User::find(10);
echo "partner_wallet=".$partner->walletBalance()." show_nav=".($partner->showsWalletInNavbar()?'1':'0')."\n";
echo "dev_wallet=".$dev->walletBalance()." show_nav=".($dev->showsWalletInNavbar()?'1':'0')."\n";
