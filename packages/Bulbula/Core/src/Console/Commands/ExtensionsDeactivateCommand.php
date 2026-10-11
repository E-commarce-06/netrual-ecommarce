<?php

namespace Bulbula\Core\Console\Commands;

use Illuminate\Console\Command;
use Bulbula\Core\Services\ExtensionService;

class ExtensionsDeactivateCommand extends Command
{
    protected $signature = 'bulbula:extensions:deactivate {code : Extension code}';

    protected $aliases = ['bulbula:extensions:deactivate'];

    protected $description = 'Deactivate an active extension.';

    public function handle(ExtensionService $extensions): int
    {
        $code = (string) $this->argument('code');

        try {
            $extension = $extensions->deactivate($code);
            $this->info("Deactivated: {$extension->code}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
