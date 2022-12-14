<?php

namespace UseTheFork\LaravelElasticsearchModel\Commands;

use Illuminate\Console\Command;

class LaravelElasticsearchModelCommand extends Command
{
    public $signature = 'laravel-elasticsearch-model';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
