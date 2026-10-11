<?php

namespace Bulbula\Core\Console\Commands;

use Illuminate\Console\Command;
use Bulbula\Core\Models\Extension;
use Bulbula\Core\Services\ExtensionService;

class ExtensionsSyncCommand extends Command
{
    protected $signature = 'bulbula:extensions:sync {--no-activate-bundled : Do not auto-activate bundled extensions when first syncing}';

    protected $aliases = ['bulbula:extensions:sync'];

    protected $description = 'Sync discovered extension manifests into the extensions table.';

    public function handle(ExtensionService $extensions): int
    {
        $discovered = $extensions->discover();

        if ($discovered->isEmpty()) {
            $this->info('No extensions discovered.');
            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;

        foreach ($discovered as $item) {
            $manifest = $item['manifest'] ?? [];
            $code = $manifest['code'] ?? null;
            if (!$code) {
                continue;
            }

            $extension = Extension::firstOrNew(['code' => $code]);
            $isNew = !$extension->exists;

            // Always refresh metadata from manifest.
            $extension->name = $manifest['name'] ?? $extension->name;
            $extension->description = $manifest['description'] ?? $extension->description;
            $extension->version = $manifest['version'] ?? $extension->version;
            $extension->author = $manifest['author'] ?? $extension->author;
            $extension->author_url = $manifest['author_url'] ?? $extension->author_url;
            $extension->icon = $manifest['icon'] ?? $extension->icon;
            $extension->requires = $manifest['requires'] ?? ($manifest['require'] ?? $extension->requires);

            // First sync defaults:
            if ($isNew) {
                $isBundled = ($item['source'] ?? null) === 'bundled';

                $extension->installed = $isBundled;
                $extension->installed_at = $isBundled ? now() : null;

                if ($isBundled && !$this->option('no-activate-bundled')) {
                    $extension->active = true;
                }
            }

            $extension->save();

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->info("Extensions synced. Created: {$created}. Updated: {$updated}.");
        $this->line("Tip: run `php artisan bulbula:extensions:list` to view status.");

        return self::SUCCESS;
    }
}
