<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ZiggyGenerateAll extends Command
{
    protected $signature = 'ziggy:generate-all';
    protected $description = 'Generate both Ziggy JavaScript and TypeScript files';

    public function handle()
    {
        $this->info('Generating Ziggy JavaScript file...');
        Artisan::call('ziggy:generate');
        $this->info(Artisan::output());

        $this->info('Generating Ziggy TypeScript file...');
        Artisan::call('ziggy:generate-types');
        $this->info(Artisan::output());

        $this->info('✅ Ziggy files generated successfully!');
        
        return Command::SUCCESS;
    }
}
