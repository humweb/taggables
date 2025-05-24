# Laravel Taggables Package Plan

## Overview

A Laravel package that allows users to create tags and tag different entities with them using polymorphic relationships. The package will provide traits, models, and helper methods for comprehensive tagging functionality with support for both global and user-scoped tags.

## Core Features

### 1. Tag Management

- **Tag Model** with properties:
  - `id` (primary key)
  - `name` (string)
  - `slug` (string, auto-generated)
  - `user_id` (nullable, foreign key) - for user-scoped tags
  - `type` (string, nullable) - for categorizing tags
  - `order_column` (integer) - for sorting
  - `metadata` (json, nullable) - for additional data
  - `created_at`, `updated_at` timestamps

### 2. Polymorphic Relationships

- **Taggables Pivot Table**:
  - `tag_id` (foreign key to tags)
  - `taggable_id` (polymorphic ID)
  - `taggable_type` (polymorphic type)
  - `created_at` timestamp

### 3. Model Traits

#### HasTags Trait

Main trait for making models taggable with methods:

**Tagging Operations:**

- `tag($tags, $type = null, $userId = null)` - Add tags (optionally user-scoped)
- `tagAsUser($tags, $user, $type = null)` - Add user-scoped tags
- `untag($tags = null, $type = null, $userId = null)` - Remove tags
- `untagAsUser($tags, $user, $type = null)` - Remove user-scoped tags
- `retag($tags, $type = null, $userId = null)` - Replace all current tags
- `retagAsUser($tags, $user, $type = null)` - Replace user-scoped tags
- `syncTags($tags, $type = null, $userId = null)` - Sync tags
- `syncTagsAsUser($tags, $user, $type = null)` - Sync user-scoped tags
- `attachTag($tag)` - Attach a single tag
- `detachTag($tag)` - Detach a single tag

**Tag Queries:**

- `tags()` - Polymorphic relationship
- `userTags($userId)` - Get tags for specific user
- `globalTags()` - Get only global tags (where user_id is null)
- `tagsWithType($type, $userId = null)` - Get tags of specific type
- `hasTag($tag, $userId = null)` - Check if model has a specific tag
- `hasUserTag($tag, $user)` - Check if model has a user-specific tag
- `hasGlobalTag($tag)` - Check if model has a global tag
- `hasAnyTag($tags, $userId = null)` - Check if model has any of the given tags
- `hasAllTags($tags, $userId = null)` - Check if model has all given tags

**Scopes:**

- `scopeWithAnyTags($query, $tags, $type = null, $userId = null)`
- `scopeWithAllTags($query, $tags, $type = null, $userId = null)`
- `scopeWithoutTags($query, $tags, $type = null, $userId = null)`
- `scopeWithUserTags($query, $tags, $user)`
- `scopeWithGlobalTags($query, $tags)`
- `scopeTaggedWith($query, $tag, $userId = null)` - Simple single tag filter

### 4. Tag Model Features

**Creation & Management:**

- `findOrCreate($name, $type = null, $userId = null)` - Find or create tag
- `findOrCreateForUser($name, $user, $type = null)` - Find or create user tag
- `findOrCreateGlobal($name, $type = null)` - Find or create global tag
- `findOrCreateMany($names, $type = null, $userId = null)` - Bulk find or create
- `findFromString($name, $type = null, $userId = null)` - Find tag from string

**Scopes:**

- `scopeForUser($query, $userId)` - Filter tags for specific user
- `scopeGlobal($query)` - Filter only global tags
- `scopeWithType($query, $type)` - Filter by type
- `scopeContaining($query, $name)` - Search tags containing string
- `scopeUsedBy($query, $modelClass)` - Tags used by specific model type
- `scopeUsedMoreThan($query, $count)` - Popular tags

**Helpers:**

- Auto-generate slug from name
- Get usage count (overall or per user)
- Get models using this tag
- Check if tag is global or user-scoped

### 5. Additional Features

#### User-Scoped Tags

- Personal tags visible only to the user who created them
- Global tags visible to all users
- Mixed tag queries (user tags + global tags)
- Tag sharing between users (future enhancement)

#### Tag Types

- Support for categorizing tags (e.g., 'category', 'skill', 'topic')
- Separate tag pools for different contexts

#### Tag Suggestions

- `suggestTags($query, $userId = null)` - Suggest tags (user + global)
- `suggestUserTags($query, $userId)` - Suggest only user's tags
- `suggestGlobalTags($query)` - Suggest only global tags
- `relatedTags($userId = null)` - Get related tags based on co-occurrence

#### Tag Cloud/Statistics

- `popularTags($limit = 20, $userId = null)` - Get most used tags
- `popularUserTags($limit, $userId)` - Get user's most used tags
- `popularGlobalTags($limit)` - Get most used global tags
- `tagCloud($userId = null)` - Get tags with usage weights
- `unusedTags($userId = null)` - Get tags not attached to any model

## Database Schema

### Tags Table Migration

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

    $table->unique(['slug', 'user_id', 'type']);
    $table->index(['user_id', 'type', 'order_column']);
});
```

### Taggables Table Migration

```php
Schema::create('taggables', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->morphs('taggable');
    $table->timestamp('created_at')->nullable();

    $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
});
```

## Configuration File

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

    // User scoping
    'user_scope' => [
        'enabled' => true,
        'allow_global_tags' => true, // Allow creation of global tags
        'mix_user_and_global' => true, // Include global tags in user queries
    ],

    // Cache configuration
    'cache' => [
        'enabled' => true,
        'key_prefix' => 'taggable',
        'ttl' => 3600,
    ],
];
```

## Usage Examples

### Basic Usage

```php
use Humweb\Taggables\Traits\HasTags;

class Post extends Model
{
    use HasTags;
}

// Global tags (available to all users)
$post = Post::find(1);
$post->tag(['laravel', 'php']); // Creates global tags
$post->untag('laravel');
$post->retag(['vue', 'javascript']);

// User-scoped tags
$post->tagAsUser(['personal', 'favorite'], $user);
$post->untagAsUser(['personal'], $user);

// Mixed operations
$post->tag(['laravel'], null, $user->id); // User-scoped tag
$post->tag(['php']); // Global tag

// Query by tags
$posts = Post::withAnyTags(['laravel', 'php'])->get(); // Includes user + global
$posts = Post::withUserTags(['personal'], $user)->get(); // Only user tags
$posts = Post::withGlobalTags(['laravel'])->get(); // Only global tags
```

### Advanced Usage

```php
// Using tag types with user scope
$post->tagAsUser(['important', 'urgent'], $user, 'priority');
$post->tagsWithType('priority', $user->id);

// Tag suggestions
$suggestions = Tag::suggestTags('larav', $user->id); // User + global tags
$userSuggestions = Tag::suggestUserTags('larav', $user->id);
$globalSuggestions = Tag::suggestGlobalTags('larav');

// Popular tags
$popularTags = Tag::popularTags(10, $user->id); // User's popular + global popular
$userPopular = Tag::popularUserTags(10, $user->id);
$globalPopular = Tag::popularGlobalTags(10);

// Check tag ownership
$tag = Tag::find(1);
if ($tag->isGlobal()) {
    // This is a global tag
} elseif ($tag->isOwnedBy($user)) {
    // This is the user's tag
}

// Tag cloud
$tagCloud = Tag::tagCloud($user->id); // Mixed cloud
```

## Package Structure

```
src/
├── Commands/
│   └── CleanupUnusedTagsCommand.php
├── Contracts/
│   └── Taggable.php
├── Events/
│   ├── TagAttached.php
│   ├── TagDetached.php
│   └── TagsSynced.php
├── Facades/
│   └── Tags.php
├── Models/
│   ├── Tag.php
│   └── Taggable.php
├── Scopes/
│   └── UserTagScope.php
├── Traits/
│   └── HasTags.php
├── TaggablesServiceProvider.php
└── TagService.php

config/
└── taggable.php

database/
└── migrations/
    ├── create_tags_table.php
    └── create_taggables_table.php

tests/
├── Unit/
├── Feature/
└── TestCase.php
```

## Testing Strategy

- Unit tests for Tag model methods (with user scoping)
- Unit tests for HasTags trait methods (with user scoping)
- Feature tests for tagging operations (global vs user tags)
- Integration tests for complex queries
- Performance tests for large datasets
- Tests for tag visibility and permissions

## Performance Considerations

- Eager loading support for tags relationship
- Query optimization with proper indexes (including user_id)
- Optional caching for popular tags (per user and global)
- Batch operations for bulk tagging
- Efficient user+global tag queries

## Future Enhancements

1. **Tag Sharing** - Share personal tags with specific users
2. **Tag Permissions** - Fine-grained control over tag operations
3. **Tag Groups** - Group tags for users or teams
4. **Tag Hierarchies** - Parent/child tag relationships
5. **Tag Aliases** - Multiple names for the same tag
6. **Tag Localization** - Multi-language tag support
7. **Tag Approval Workflow** - Moderate user-created tags
8. **Tag Merge** - Merge duplicate tags across users
9. **Tag Analytics** - Track tag usage over time per user
