<?php

namespace App\Console\Commands;

use App\Queries\DemoQueries;
use Illuminate\Console\Command;

class DemoQueriesCommand extends Command
{
    protected $signature = 'demo:queries';

    protected $description = 'Ejecuta las 7 consultas Eloquent del laboratorio ORM';

    public function handle(DemoQueries $demo): int
    {
        $this->info('Ejecutando consultas Eloquent de demostración…');
        $this->newLine();
        $demo->runAll();
        $this->newLine();
        $this->info('Listo.');
        return self::SUCCESS;
    }
}
