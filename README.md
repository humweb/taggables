# Taggables Package for Laravel

[![tests](https://github.com/humweb/taggables/actions/workflows/run-tests.yml/badge.svg)](https://github.com/humweb/taggables/actions/workflows/run-tests.yml)
[![codecov](https://codecov.io/gh/humweb/taggables/graph/badge.svg)](https://codecov.io/gh/humweb/taggables)

A powerful and flexible tagging package for Laravel applications with polymorphic relationships support and user-scoped tags.

## Features

- 🏷️ **Polymorphic tagging** - Tag any Eloquent model
- 👤 **User-scoped tags** - Personal tags for each user alongside global tags
- 📁 **Tag types** - Organize tags into categories
- 🔍 **Advanced queries** - Filter models by tags with ease
- 🚀 **Performance optimized** - Eager loading and query optimization
- 📊 **Tag statistics** - Popular tags, tag clouds, and more
- 🎯 **Type hinting** - Full IDE support with proper return types
- ✨ **Laravel conventions** - Follows Laravel best practices

## Installation

You can install the package via composer:

```bash
composer require humweb/taggables
```

Publish the migrations:

```bash
php artisan vendor:publish --tag="taggable-migrations"
```

Run the migrations:

```bash
php artisan migrate
```

Optionally, publish the config file:

```bash
php artisan vendor:publish --tag="taggable-config"
```

## Usage

### Making a Model Taggable

Add the `HasTags` trait to any model you want to make taggable:

```php
use Humweb\Taggables\Traits\HasTags;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasTags;
}
```

### Basic Tagging Operations

```php
$post = Post::find(1);

// Add global tags (available to all users)
$post->tag('laravel');
$post->tag(['php', 'javascript']);

// Add user-specific tags
$post->tagAsUser(['favorite', 'important'], $user);

// Remove tags
$post->untag('laravel');
$post->untag(['php', 'javascript']);
$post->untag(); // Remove all tags

// Remove user-specific tags
$post->untagAsUser(['favorite'], $user);

// Replace all tags
$post->retag(['vue', 'tailwind']);
$post->retagAsUser(['personal', 'work'], $user);

// Sync tags (like Laravel's sync method)
$post->syncTags(['laravel', 'php', 'mysql']);
$post->syncTagsAsUser(['todo', 'urgent'], $user);
```

### User-Scoped Tags

The package supports both global tags (available to all users) and user-specific tags:

```php
// Create a global tag
$post->tag('laravel'); // Available to all users

// Create a user-specific tag
$post->tagAsUser('personal-project', $user);

// Mix global and user tags
$post->tag(['php', 'laravel']); // Global
$post->tagAsUser(['favorite', 'todo'], $user); // User-specific

// Get only user tags
$userTags = $post->userTags($user->id);

// Get only global tags
$globalTags = $post->globalTags();

// Get all tags (user + global)
$allTags = $post->tags; // Returns both by default
```

### Checking Tags

```php
// Check if the model has a specific tag (checks user + global by default)
if ($post->hasTag('laravel', $user->id)) {
    // ...
}

// Check for global tag only
if ($post->hasGlobalTag('laravel')) {
    // ...
}

// Check for user tag only
if ($post->hasUserTag('favorite', $user)) {
    // ...
}

// Check if the model has any of the given tags
if ($post->hasAnyTag(['laravel', 'php'], $user->id)) {
    // ...
}

// Check if the model has all of the given tags
if ($post->hasAllTags(['laravel', 'php'], $user->id)) {
    // ...
}
```

### Querying by Tags

```php
// Get all posts with any of the given tags (includes user + global tags)
$posts = Post::withAnyTags(['laravel', 'php'], null, $userId)->get();

// Get posts with user-specific tags only
$posts = Post::withUserTags(['favorite', 'todo'], $user)->get();

// Get posts with global tags only
$posts = Post::withGlobalTags(['laravel', 'php'])->get();

// Get all posts with all of the given tags
$posts = Post::withAllTags(['laravel', 'php'], null, $userId)->get();

// Get all posts without the given tags
$posts = Post::withoutTags(['draft', 'archived'], null, $userId)->get();

// Simple single tag query
$posts = Post::taggedWith('laravel', $userId)->get();
```

### Using Tag Types

Tag types allow you to categorize your tags:

```php
// Tag with type
$post->tag(['important', 'urgent'], 'priority');
$post->tagAsUser(['personal', 'work'], $user, 'category');

// Get tags of a specific type
$priorityTags = $post->tagsWithType('priority');
$userCategories = $post->tagsWithType('category', $user->id);

// Query by tags with type
$posts = Post::withAnyTags(['important', 'urgent'], 'priority', $userId)->get();
```

### Working with the Tag Model

```php
use Humweb\Taggables\Models\Tag;

// Find or create a tag
$tag = Tag::findOrCreate('laravel'); // Global tag
$tag = Tag::findOrCreateForUser('personal', $user); // User tag
$tag = Tag::findOrCreateGlobal('php'); // Explicitly global

// Find or create with type
$tag = Tag::findOrCreate('important', 'priority', $user->id);

// Find or create multiple tags
$tags = Tag::findOrCreateMany(['laravel', 'php', 'mysql'], null, $user->id);

// Search tags
$tags = Tag::containing('lara')->get();

// Get tags by user
$userTags = Tag::forUser($user->id)->get();
$globalTags = Tag::global()->get();
$mixedTags = Tag::forUserWithGlobal($user->id)->get();

// Get tags by type
$priorityTags = Tag::withType('priority')->get();

// Get popular tags
$popularTags = Tag::popularTags(10, $user->id); // Mixed popular tags
$userPopular = Tag::popularUserTags(10, $user->id); // User's popular tags
$globalPopular = Tag::popularGlobalTags(10); // Global popular tags

// Get tag cloud (tags with usage weight)
$tagCloud = Tag::tagCloud($user->id); // Mixed cloud
$globalCloud = Tag::tagCloud(); // Global only

// Get unused tags
$unusedTags = Tag::unusedTags($user->id)->get();

// Check tag ownership
$tag = Tag::find(1);
if ($tag->isGlobal()) {
    // This is a global tag
} elseif ($tag->isOwnedBy($user)) {
    // This is the user's tag
}
```

### Tag Suggestions

```php
// Get tag suggestions based on partial input
$suggestions = Tag::suggestTags('lara', $user->id); // Returns user + global tags
$suggestions = Tag::suggestTags('lara'); // Returns only global tags

// Get related tags (tags often used together)
$tag = Tag::findOrCreate('laravel');
$relatedTags = $tag->relatedTags($user->id);
```

### Events

The package fires events during tagging operations:

- `TagAttached` - Fired when a tag is attached to a model
- `TagDetached` - Fired when a tag is detached from a model
- `TagsSynced` - Fired when tags are synced

```php
use Humweb\Taggables\Events\TagAttached;

// In your EventServiceProvider
protected $listen = [
    TagAttached::class => [
        SendTagNotification::class,
    ],
];
```

### Artisan Commands

Clean up unused tags:

```bash
php artisan tags:cleanup

# Clean up only user tags
php artisan tags:cleanup --user=123

# Clean up only global tags
php artisan tags:cleanup --global
```

## Advanced Usage

### Custom Tag Model

You can extend the Tag model for additional functionality:

```php
namespace App\Models;

use Humweb\Taggables\Models\Tag as BaseTag;

class Tag extends BaseTag
{
    // Add your custom methods
    public function isPublic(): bool
    {
        return $this->isGlobal() || $this->metadata['public'] ?? false;
    }
}
```

Update the config to use your custom model:

```php
// config/taggable.php
return [
    'tag_model' => \App\Models\Tag::class,
];
```

### Eager Loading

```php
// Eager load all tags
$posts = Post::with('tags')->get();

// Eager load only user tags
$posts = Post::with(['tags' => function ($query) use ($userId) {
    $query->where('user_id', $userId);
}])->get();

// Eager load only global tags
$posts = Post::with(['tags' => function ($query) {
    $query->whereNull('user_id');
}])->get();

// Eager load tags with specific type
$posts = Post::with(['tags' => function ($query) {
    $query->where('type', 'technology');
}])->get();
```

### Caching

The package supports caching for better performance:

```php
// config/taggable.php
return [
    'cache' => [
        'enabled' => true,
        'key_prefix' => 'taggable',
        'ttl' => 3600, // 1 hour
    ],
];
```

## Configuration

The full configuration file:

```php
return [
    // The tag model to use
    'tag_model' => \Humweb\Taggables\Models\Tag::class,

    // Table names
    'tables' => [
        'tags' => 'tags',
        'taggables' => 'taggables',
    ],

    // Slug generation
    'slugger' => null, // null defaults to Str::slug

    // Tag name validation rules
    'rules' => [
        'name' => ['required', 'string', 'max:255'],
    ],

    // Auto-delete unused tags
    'delete_unused_tags' => false,

    // User scoping configuration
    'user_scope' => [
        // Enable user-scoped tags
        'enabled' => true,

        // Allow creation of global tags (null user_id)
        'allow_global_tags' => true,

        // Include global tags when querying user tags
        'mix_user_and_global' => true,
    ],

    // Cache configuration
    'cache' => [
        'enabled' => true,
        'key_prefix' => 'taggable',
        'ttl' => 3600,
    ],
];
```

## Migration Notes

The tags table includes a `user_id` column for user-scoped tags:

```php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug');
    $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('type')->nullable()->index();
    $table->integer('order_column')->default(0);
    $table->json('metadata')->nullable();
    $table->timestamps();

    // Same slug can exist for different users/types
    $table->unique(['slug', 'user_id', 'type']);
    $table->index(['user_id', 'type', 'order_column']);
});
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ryan Shofner](https://github.com/ryun)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
