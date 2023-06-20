<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\PaymentsController;

class send_alerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send_alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send alerts to users';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $payments_controller = new PaymentsController();
        $payments_controller->send_alerts();
    }
}
