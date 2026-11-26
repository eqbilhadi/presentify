<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;
use SplFileInfo;

class RouteLoader
{
    /**
     * Load only top-level PHP route files inside routes/{context} (flat 1-level folder).
     */
    public static function load(string $context): void
    {
        $directory = base_path("routes/{$context}");

        if (! File::isDirectory($directory)) {
            return;
        }

        $files = array_values(array_filter(
            File::files($directory),
            static fn (SplFileInfo $file): bool => $file->getExtension() === 'php',
        ));

        usort(
            $files,
            static fn (SplFileInfo $a, SplFileInfo $b): int => strcmp($a->getFilename(), $b->getFilename()),
        );

        foreach ($files as $file) {
            require $file->getPathname();
        }
    }
}
