<?php

namespace Pixelworx\LivewireWorkflows\Commands;

use Illuminate\Console\Command;

class LivewireWorkflowsCommand extends Command
{
    public $signature = 'livewire-workflows';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
