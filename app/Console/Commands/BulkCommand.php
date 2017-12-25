<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BulkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'index:bulk {model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk Index';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $class = $this->argument('model');
        (new $class())->bulkIndex();

        info(__CLASS__. ' ' . $class . 'OK');
    }
}
