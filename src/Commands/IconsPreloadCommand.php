<?php

declare(strict_types=1);

namespace Unicon\Commands;

use Unicon\IconRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IconsPreloadCommand extends Command
{
    /**
     * The name and signature of the console command
     */
    protected $signature = 'icons:preload';

    /**
     * The console command description
     */
    protected $description = 'Preloads all Unicon icons';

    public function __construct(
        protected IconRenderer $renderer,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $this->info('Looking for icons to preload in your Blade files...');

        $this->findAllBladeFiles()
            ->flatMap(fn(string $file) => $this->findIconsInFile($file))
            ->unique()
            ->sort()
            ->each(fn(string $icon) => $this->preloadIcon($icon));

        return self::SUCCESS;
    }

    /**
     * Find all Blade files in the application
     * 
     * This method will look for all Blade files in the application and return
     * them as an array of paths.
     * 
     * @return Collection<string>
     */
    protected function findAllBladeFiles(): Collection
    {
        return $this->glob('*.blade.php');
    }

    /**
     * Recursive glob
     * 
     * This method will recursively glob files using the given pattern.
     */
    protected function glob(string $pattern, ?string $root = null): Collection
    {
        $root ??= base_path();

        $files = collect(glob($root . '/' . $pattern));

        collect(glob($root . '/*', GLOB_ONLYDIR))->each(function ($dir) use (&$files, $pattern) {
            if (!in_array(basename($dir), ['node_modules', 'vendor'])) {
                $files = $files->merge($this->glob($pattern, $dir));
            }
        });

        return $files;
    }

    /**
     * Find all icons in a Blade file
     * 
     * This method will attempt to find all Unicon icons in a Blade file
     * by performing a regex search on the file contents. This implementation
     * only works for icons that have been statitcally defined. Variables in
     * the name attribute will not be evaluated.
     */
    protected function findIconsInFile(string $path): Collection
    {
        $contents = file_get_contents($path);

        $componentName = Str::snake(config('unicon.name', 'icon'));

        $hasMatches = preg_match_all(
            pattern: '/<x-' . $componentName . '\s+name\s*=\s*(["\'])(?<name>[\s\S]*?)\1/m',
            subject: $contents,
            matches: $matches,
            flags: PREG_SET_ORDER,
        );

        if (!$hasMatches) {
            return [];
        }

        return collect($matches)->map(fn($match) => $match['name']);
    }

    /**
     * Preloads an icon
     */
    protected function preloadIcon(string $icon): void
    {
        $this->info("Preloading {$icon}...");
        $this->renderer->render($icon);
    }
}
