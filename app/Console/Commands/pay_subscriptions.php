<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\PaymentsController;

class pay_subscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:pay_subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pay Plans Subscriptions';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $payments_controller = new PaymentsController();
        $payments_controller->pay_subscriptions();
    }
}
