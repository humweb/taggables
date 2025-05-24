# Testing Strategy

## Overview

This document outlines the testing strategy for the Laravel Taggable Package to ensure reliability, maintainability, and performance.

## Test Structure

```
tests/
├── Unit/
│   ├── Models/
│   │   └── TagTest.php
│   ├── Traits/
│   │   └── HasTagsTest.php
│   └── Services/
│       └── TagServiceTest.php
├── Feature/
│   ├── TaggingWorkflowTest.php
│   ├── QueryScopesTest.php
│   ├── TagTypesTest.php
│   └── CommandsTest.php
├── Performance/
│   └── LargeDatasetTest.php
└── TestCase.php
```

## Unit Tests

### Tag Model Tests

```php
namespace Humweb\Taggables\Tests\Unit\Models;

use Humweb\Taggables\Tests\TestCase;
use Humweb\Taggables\Models\Tag;

class TagTest extends TestCase
{
    /** @test */
    public function it_generates_slug_on_creation()
    {
        $tag = Tag::create(['name' => 'Laravel Framework']);

        $this->assertEquals('laravel-framework', $tag->slug);
    }

    /** @test */
    public function it_generates_unique_slug_for_duplicate_names()
    {
        Tag::create(['name' => 'Laravel']);
        $tag2 = Tag::create(['name' => 'Laravel']);

        $this->assertEquals('laravel-1', $tag2->slug);
    }

    /** @test */
    public function it_finds_or_creates_tag()
    {
        $tag1 = Tag::findOrCreate('New Tag');
        $tag2 = Tag::findOrCreate('New Tag');

        $this->assertTrue($tag1->is($tag2));
        $this->assertEquals(1, Tag::count());
    }

    /** @test */
    public function it_finds_or_creates_many_tags()
    {
        $tags = Tag::findOrCreateMany(['Tag 1', 'Tag 2', 'Tag 1']);

        $this->assertCount(3, $tags);
        $this->assertEquals(2, Tag::count());
    }

    /** @test */
    public function it_respects_type_when_finding_or_creating()
    {
        $tag1 = Tag::findOrCreate('PHP', 'language');
        $tag2 = Tag::findOrCreate('PHP', 'category');

        $this->assertNotEquals($tag1->id, $tag2->id);
        $this->assertEquals('language', $tag1->type);
        $this->assertEquals('category', $tag2->type);
    }
}
```

### HasTags Trait Tests

```php
namespace Humweb\Taggables\Tests\Unit\Traits;

use Humweb\Taggables\Tests\TestCase;
use Humweb\Taggables\Tests\Models\Post;
use Humweb\Taggables\Models\Tag;

class HasTagsTest extends TestCase
{
    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post = Post::create(['title' => 'Test Post']);
    }

    /** @test */
    public function it_can_attach_single_tag()
    {
        $this->post->tag('laravel');

        $this->assertCount(1, $this->post->tags);
        $this->assertEquals('laravel', $this->post->tags->first()->slug);
    }

    /** @test */
    public function it_can_attach_multiple_tags()
    {
        $this->post->tag(['laravel', 'php', 'web']);

        $this->assertCount(3, $this->post->tags);
    }

    /** @test */
    public function it_can_detach_tags()
    {
        $this->post->tag(['laravel', 'php']);
        $this->post->untag('laravel');

        $this->assertCount(1, $this->post->tags);
        $this->assertFalse($this->post->hasTag('laravel'));
        $this->assertTrue($this->post->hasTag('php'));
    }

    /** @test */
    public function it_can_sync_tags()
    {
        $this->post->tag(['laravel', 'php']);
        $this->post->syncTags(['vue', 'javascript']);

        $this->assertCount(2, $this->post->tags);
        $this->assertTrue($this->post->hasTag('vue'));
        $this->assertFalse($this->post->hasTag('laravel'));
    }

    /** @test */
    public function it_can_check_for_tags()
    {
        $this->post->tag(['laravel', 'php']);

        $this->assertTrue($this->post->hasTag('laravel'));
        $this->assertTrue($this->post->hasAnyTag(['laravel', 'ruby']));
        $this->assertTrue($this->post->hasAllTags(['laravel', 'php']));
        $this->assertFalse($this->post->hasAllTags(['laravel', 'ruby']));
    }
}
```

## Feature Tests

### Complete Tagging Workflow Test

```php
namespace Humweb\Taggables\Tests\Feature;

use Humweb\Taggables\Tests\TestCase;
use Humweb\Taggables\Tests\Models\Post;
use Humweb\Taggables\Tests\Models\Product;
use Humweb\Taggables\Events\TagAttached;
use Humweb\Taggables\Events\TagDetached;
use Illuminate\Support\Facades\Event;

class TaggingWorkflowTest extends TestCase
{
    /** @test */
    public function it_handles_complete_tagging_workflow()
    {
        Event::fake();

        // Create models
        $post = Post::create(['title' => 'Laravel Tips']);
        $product = Product::create(['name' => 'Laravel Course']);

        // Tag models
        $post->tag(['laravel', 'tips', 'web']);
        $product->tag(['laravel', 'course']);

        // Verify events
        Event::assertDispatched(TagAttached::class, 5);

        // Query by tags
        $laravelItems = Post::withAnyTags(['laravel'])->get();
        $this->assertCount(1, $laravelItems);

        // Cross-model queries
        $this->assertEquals(2, Tag::find(1)->taggables()->count());

        // Update tags
        $post->retag(['php', 'tips']);
        Event::assertDispatched(TagDetached::class, 3);

        // Verify final state
        $this->assertCount(2, $post->fresh()->tags);
        $this->assertTrue($post->hasTag('php'));
        $this->assertFalse($post->hasTag('laravel'));
    }

    /** @test */
    public function it_handles_tag_types_correctly()
    {
        $post = Post::create(['title' => 'Test']);

        $post->tag(['important', 'urgent'], 'priority');
        $post->tag(['laravel', 'php'], 'technology');

        $this->assertCount(2, $post->tagsWithType('priority'));
        $this->assertCount(2, $post->tagsWithType('technology'));
        $this->assertCount(4, $post->tags);
    }
}
```

### Query Scopes Test

```php
namespace Humweb\Taggables\Tests\Feature;

use Humweb\Taggables\Tests\TestCase;
use Humweb\Taggables\Tests\Models\Post;

class QueryScopesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $post1 = Post::create(['title' => 'Post 1']);
        $post1->tag(['laravel', 'php']);

        $post2 = Post::create(['title' => 'Post 2']);
        $post2->tag(['laravel', 'vue']);

        $post3 = Post::create(['title' => 'Post 3']);
        $post3->tag(['php', 'mysql']);

        $post4 = Post::create(['title' => 'Post 4']);
        $post4->tag(['ruby', 'rails']);
    }

    /** @test */
    public function it_queries_with_any_tags()
    {
        $posts = Post::withAnyTags(['laravel', 'ruby'])->get();

        $this->assertCount(3, $posts);
        $this->assertContains('Post 1', $posts->pluck('title'));
        $this->assertContains('Post 2', $posts->pluck('title'));
        $this->assertContains('Post 4', $posts->pluck('title'));
    }

    /** @test */
    public function it_queries_with_all_tags()
    {
        $posts = Post::withAllTags(['laravel', 'php'])->get();

        $this->assertCount(1, $posts);
        $this->assertEquals('Post 1', $posts->first()->title);
    }

    /** @test */
    public function it_queries_without_tags()
    {
        $posts = Post::withoutTags(['laravel'])->get();

        $this->assertCount(2, $posts);
        $this->assertContains('Post 3', $posts->pluck('title'));
        $this->assertContains('Post 4', $posts->pluck('title'));
    }
}
```

## Performance Tests

```php
namespace Humweb\Taggables\Tests\Performance;

use Humweb\Taggables\Tests\TestCase;
use Humweb\Taggables\Tests\Models\Post;
use Humweb\Taggables\Models\Tag;

class LargeDatasetTest extends TestCase
{
    /** @test */
    public function it_handles_bulk_tagging_efficiently()
    {
        $startTime = microtime(true);

        // Create 1000 posts
        $posts = Post::factory()->count(1000)->create();

        // Tag them all
        foreach ($posts as $post) {
            $post->tag(['tag1', 'tag2', 'tag3']);
        }

        $duration = microtime(true) - $startTime;

        $this->assertLessThan(1, $duration, 'Bulk tagging took too long');
        $this->assertEquals(3000, DB::table('taggables')->count());
    }

    /** @test */
    public function it_queries_large_datasets_efficiently()
    {
        // Seed data
        Post::factory()->count(10000)->create()->each(function ($post) {
            $post->tag(Tag::inRandomOrder()->limit(5)->pluck('name'));
        });

        $startTime = microtime(true);

        $posts = Post::withAnyTags(['tag1', 'tag2'])->get();

        $duration = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $duration, 'Query took too long');
    }
}
```

## Test Helpers

### Base Test Case

```php
namespace Humweb\Taggables\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Humweb\Taggables\TaggablesServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->artisan('migrate')->run();
    }

    protected function getPackageProviders($app)
    {
        return [
            TaggablesServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
```

### Test Models

```php
// tests/Models/Post.php
namespace Humweb\Taggables\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Humweb\Taggables\Traits\HasTags;

class Post extends Model
{
    use HasTags;

    protected $fillable = ['title'];
}

// tests/Models/Product.php
namespace Humweb\Taggables\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Humweb\Taggables\Traits\HasTags;

class Product extends Model
{
    use HasTags;

    protected $fillable = ['name'];
}
```

## CI/CD Configuration

### GitHub Actions Workflow

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ${{ matrix.os }}
    strategy:
      matrix:
        os: [ubuntu-latest]
        php: [8.1, 8.2, 8.3]
        laravel: [10.*, 11.*]

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, pdo, pdo_sqlite
          coverage: xdebug

      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" --no-update
          composer update --prefer-dist --no-interaction

      - name: Run tests
        run: vendor/bin/pest --coverage

      - name: Upload coverage
        uses: codecov/codecov-action@v1
```

## Code Coverage Goals

- Overall coverage: > 90%
- Unit tests: 100% coverage for core functionality
- Feature tests: Cover all major workflows
- Edge cases: Comprehensive testing of error conditions

## Testing Best Practices

1. **Isolation**: Each test should be independent
2. **Clarity**: Test names should clearly describe what they test
3. **Speed**: Unit tests should be fast (< 100ms each)
4. **Coverage**: Aim for high coverage but focus on meaningful tests
5. **Maintainability**: Keep tests simple and easy to understand
