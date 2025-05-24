# Laravel Taggable Package Architecture

## Overview Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        Your Laravel App                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────┐     uses      ┌──────────────────────┐       │
│  │   Post      │──────────────>│   HasTags Trait      │       │
│  │   Model     │               └──────────────────────┘       │
│  └─────────────┘                         │                     │
│                                          │                     │
│  ┌─────────────┐     uses               ↓                     │
│  │  Product    │──────────────>┌──────────────────────┐       │
│  │   Model     │               │ Polymorphic Relations│       │
│  └─────────────┘               └──────────────────────┘       │
│                                          │                     │
│  ┌─────────────┐                        │                     │
│  │   User      │───────────────────────┐│                     │
│  │   Model     │                       ││                     │
│  └─────────────┘                       ││                     │
└──────────────────────────────────────────┼┼────────────────────┘
                                          ││
┌─────────────────────────────────────────▼▼─────────────────────┐
│                    Taggable Package                            │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌──────────────┐         ┌─────────────────┐                │
│  │  Tag Model   │<───────>│ Taggable Pivot  │                │
│  └──────────────┘         └─────────────────┘                │
│         │                                                      │
│         │ belongs to (optional)                               │
│         │                                                      │
│  ┌──────▼───────────────────────────────┐                    │
│  │         Tag Features                  │                    │
│  ├───────────────────────────────────────┤                    │
│  │ • findOrCreate()                      │                    │
│  │ • findOrCreateForUser()               │                    │
│  │ • findOrCreateGlobal()                │                    │
│  │ • popularTags()                       │                    │
│  │ • popularUserTags()                   │                    │
│  │ • popularGlobalTags()                 │                    │
│  │ • tagCloud()                          │                    │
│  │ • suggestTags()                       │                    │
│  │ • withType()                          │                    │
│  │ • forUser()                           │                    │
│  │ • global()                            │                    │
│  └───────────────────────────────────────┘                    │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

## Database Schema

```
┌─────────────────────┐         ┌──────────────────────┐
│       tags          │         │     taggables        │
├─────────────────────┤         ├──────────────────────┤
│ id                  │<────────│ tag_id               │
│ name                │         │ taggable_id          │
│ slug                │         │ taggable_type        │
│ user_id (nullable)  │         │ created_at           │
│ type                │         └──────────────────────┘
│ order_column        │                    │
│ metadata            │                    │ morphs to
│ created_at          │                    ↓
│ updated_at          │         ┌──────────────────────┐
└─────────────────────┘         │   Any Model with      │
           │                    │   HasTags trait       │
           │                    └──────────────────────┘
           │ user_id
           ↓
    ┌─────────────┐
    │    users    │
    └─────────────┘
```

## Tag Scoping Logic

```
┌────────────────────────────────────┐
│         Tag Query Request          │
└────────────────────────────────────┘
                │
                ▼
┌────────────────────────────────────┐
│     User Context Provided?         │
└────────────────────────────────────┘
        │              │
        │ Yes          │ No
        ▼              ▼
┌──────────────┐  ┌──────────────┐
│ Mix User &   │  │ Global Tags  │
│ Global Tags? │  │    Only      │
└──────────────┘  └──────────────┘
        │
   ┌────┴────┐
   │Yes │ No │
   ▼    ▼    ▼
┌─────┐┌─────┐┌─────┐
│User ││User ││Global│
│  +  ││Only ││Only │
│Global│     │      │
└─────┘└─────┘└─────┘
```

## Key Components

### 1. Models

- **Tag Model**: Core model for storing tags with optional user ownership
- **Taggable Pivot**: Polymorphic pivot model

### 2. Traits

- **HasTags**: Main trait that adds tagging functionality to models with user support

### 3. Service Classes

- **TagService**: Core service for tag operations
- **TaggablesServiceProvider**: Laravel service provider

### 4. Query Scopes

Available on models using HasTags trait:

- `withAnyTags()` - With optional user context
- `withAllTags()` - With optional user context
- `withUserTags()` - User tags only
- `withGlobalTags()` - Global tags only
- `withoutTags()` - With optional user context
- `taggedWith()` - With optional user context

### 5. Events

- `TagAttached`
- `TagDetached`
- `TagsSynced`

## Data Flow

### Tagging a Model

```
User Action
    ↓
$post->tag(['laravel', 'php'], null, $userId)
    ↓
HasTags::tag()
    ↓
Tag::findOrCreate() for each tag with user_id
    ↓
Attach tags via morphToMany relation
    ↓
Fire TagAttached event
```

### Querying by Tags with User Context

```
Post::withAnyTags(['laravel'], null, $userId)
    ↓
scopeWithAnyTags()
    ↓
Check config for mix_user_and_global
    ↓
Build query with user context
    ├─> If mix enabled: WHERE user_id = ? OR user_id IS NULL
    └─> If mix disabled: WHERE user_id = ?
    ↓
Join with taggables table
    ↓
Join with tags table
    ↓
Filter by tag names/slugs
    ↓
Return query builder
```

## Package Structure

```
taggable-package/
├── config/
│   └── taggable.php              # Package configuration
├── database/
│   └── migrations/
│       ├── create_tags_table.php # Includes user_id column
│       └── create_taggables_table.php
├── src/
│   ├── Commands/
│   │   └── CleanupUnusedTagsCommand.php
│   ├── Contracts/
│   │   └── Taggable.php          # Interface for taggable models
│   ├── Events/
│   │   ├── TagAttached.php
│   │   ├── TagDetached.php
│   │   └── TagsSynced.php
│   ├── Facades/
│   │   └── Tags.php              # Laravel facade
│   ├── Models/
│   │   ├── Tag.php               # Tag eloquent model with user support
│   │   └── Taggable.php          # Pivot model
│   ├── Traits/
│   │   └── HasTags.php           # Main trait with user-scoped methods
│   ├── TagService.php            # Core service class
│   └── TaggablesServiceProvider.php
└── tests/
    ├── Feature/
    │   ├── UserScopedTagsTest.php
    │   └── GlobalTagsTest.php
    ├── Unit/
    └── TestCase.php
```

## Integration Points

### 1. Model Integration

Any Eloquent model can use the `HasTags` trait:

```php
class Post extends Model {
    use HasTags;
}
```

### 2. User Integration

Tags can be scoped to users:

```php
// Global tag
$post->tag('laravel');

// User-scoped tag
$post->tagAsUser('favorite', $user);
```

### 3. Service Provider Registration

The package auto-registers via Laravel's package discovery.

### 4. Configuration

Users can customize:

- Tag model class
- Table names
- Slug generation
- Validation rules
- User scoping behavior
- Caching settings

### 5. Event Integration

Users can listen to package events in their EventServiceProvider.

## Performance Considerations

1. **Indexes**:

   - Composite unique index on `tags.slug`, `tags.user_id`, `tags.type`
   - Index on `tags.user_id`, `tags.type`, `tags.order_column`
   - Unique composite index on taggables pivot

2. **Eager Loading**:

   - Support for `with('tags')`
   - Support for user-specific eager loading
   - Optimized queries to prevent N+1 issues

3. **Caching**:

   - Optional caching for popular tags (per user and global)
   - Tag suggestions caching
   - Configurable TTL

4. **Batch Operations**:
   - Bulk tag creation
   - Bulk attach/detach operations
   - Efficient user context queries

## User Scoping Configuration

The package behavior can be configured via `config/taggable.php`:

```php
'user_scope' => [
    // Enable user-scoped tags
    'enabled' => true,

    // Allow creation of global tags (null user_id)
    'allow_global_tags' => true,

    // Include global tags when querying user tags
    'mix_user_and_global' => true,
],
```

This allows for flexible implementation:

- **Personal tag system**: Set `allow_global_tags` to false
- **Mixed system**: Keep defaults for user + global tags
- **Global only**: Set `enabled` to false
