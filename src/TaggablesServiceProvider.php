<?php

namespace Humweb\Taggables;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Humweb\Taggables\Commands\CleanupUnusedTagsCommand;

class TaggablesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('taggable')
            ->hasConfigFile()
            ->hasMigrations([
                'create_tags_table',
                'create_taggables_table'
            ])
            ->hasCommand(CleanupUnusedTagsCommand::class);
    }
}
