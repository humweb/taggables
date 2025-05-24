<?php

namespace Humweb\Taggables\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Humweb\Taggables\TaggablesServiceProvider;
use Illuminate\Database\Schema\Blueprint;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Humweb\\Taggables\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            TaggablesServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Run package migrations
        $migration = include __DIR__ . '/../database/migrations/create_tags_table.php.stub';
        $migration->up();
        
        $migration = include __DIR__ . '/../database/migrations/create_taggables_table.php.stub';
        $migration->up();
        
        // Create users table for testing user-scoped tags
        $app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestamps();
        });
        
        // Create test model table
        $app['db']->connection()->getSchemaBuilder()->create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
} 
