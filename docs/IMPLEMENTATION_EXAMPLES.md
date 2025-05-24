# Implementation Examples

## Core Tag Model Implementation

```php
<?php

namespace Humweb\Taggables\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'user_id', 'type', 'order_column', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = static::generateSlug($tag->name);
            }

            if (is_null($tag->order_column)) {
                $tag->order_column = static::getNextOrderNumber();
            }
        });
    }

    /**
     * Generate a unique slug for the tag
     */
    protected static function generateSlug(string $name): string
    {
        $slug = Str::slug($name);
        return $slug;
    }

    /**
     * Get the next order number
     */
    protected static function getNextOrderNumber(): int
    {
        return static::max('order_column') + 1;
    }

    /**
     * Find or create a tag by name
     */
    public static function findOrCreate(string $name, ?string $type = null, ?int $userId = null): self
    {
        $slug = Str::slug($name);

        $tag = static::where('slug', $slug)
            ->where('user_id', $userId)
            ->when($type, fn($query) => $query->where('type', $type))
            ->first();

        if (!$tag) {
            $tag = static::create([
                'name' => $name,
                'slug' => $slug,
                'user_id' => $userId,
                'type' => $type,
            ]);
        }

        return $tag;
    }

    /**
     * Find or create a tag for a specific user
     */
    public static function findOrCreateForUser(string $name, $user, ?string $type = null): self
    {
        $userId = is_object($user) ? $user->id : $user;
        return static::findOrCreate($name, $type, $userId);
    }

    /**
     * Find or create a global tag
     */
    public static function findOrCreateGlobal(string $name, ?string $type = null): self
    {
        return static::findOrCreate($name, $type, null);
    }

    /**
     * Find or create multiple tags
     */
    public static function findOrCreateMany(array $names, ?string $type = null, ?int $userId = null): Collection
    {
        return collect($names)->map(function ($name) use ($type, $userId) {
            return static::findOrCreate($name, $type, $userId);
        });
    }

    /**
     * Scope to filter tags for a specific user
     */
    public function scopeForUser(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get global tags only
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope to get user tags with global tags
     */
    public function scopeForUserWithGlobal(Builder $query, $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        });
    }

    /**
     * Scope to filter tags by type
     */
    public function scopeWithType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to search tags containing a string
     */
    public function scopeContaining($query, string $search)
    {
        return $query->where(function ($query) use ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('slug', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Get popular tags
     */
    public static function popularTags(int $limit = 20, ?int $userId = null): Collection
    {
        $query = static::withCount('taggables')
            ->orderBy('taggables_count', 'desc')
            ->limit($limit);

        if ($userId && config('taggable.user_scope.mix_user_and_global', true)) {
            $query->forUserWithGlobal($userId);
        } elseif ($userId) {
            $query->forUser($userId);
        } else {
            $query->global();
        }

        return $query->get();
    }

    /**
     * Get popular user tags
     */
    public static function popularUserTags(int $limit, int $userId): Collection
    {
        return static::withCount('taggables')
            ->forUser($userId)
            ->orderBy('taggables_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get popular global tags
     */
    public static function popularGlobalTags(int $limit): Collection
    {
        return static::withCount('taggables')
            ->global()
            ->orderBy('taggables_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Suggest tags based on partial input
     */
    public static function suggestTags(string $query, ?int $userId = null): Collection
    {
        $search = static::containing($query)->limit(10);

        if ($userId && config('taggable.user_scope.mix_user_and_global', true)) {
            $search->forUserWithGlobal($userId);
        } elseif ($userId) {
            $search->forUser($userId);
        }

        return $search->get();
    }

    /**
     * Check if tag is global
     */
    public function isGlobal(): bool
    {
        return is_null($this->user_id);
    }

    /**
     * Check if tag is owned by user
     */
    public function isOwnedBy($user): bool
    {
        $userId = is_object($user) ? $user->id : $user;
        return $this->user_id === $userId;
    }

    /**
     * Get tag cloud with weights
     */
    public static function tagCloud(?int $userId = null): Collection
    {
        $query = static::withCount('taggables');

        if ($userId && config('taggable.user_scope.mix_user_and_global', true)) {
            $query->forUserWithGlobal($userId);
        } elseif ($userId) {
            $query->forUser($userId);
        }

        $tags = $query->get();

        $maxCount = $tags->max('taggables_count');
        $minCount = $tags->min('taggables_count');

        return $tags->map(function ($tag) use ($maxCount, $minCount) {
            $weight = $minCount == $maxCount
                ? 1
                : ($tag->taggables_count - $minCount) / ($maxCount - $minCount);

            $tag->weight = round($weight * 10); // 0-10 scale
            return $tag;
        });
    }

    /**
     * Get unused tags
     */
    public function scopeUnusedTags($query, ?int $userId = null)
    {
        $query->has('taggables', '=', 0);

        if ($userId) {
            $query->forUser($userId);
        }

        return $query;
    }

    /**
     * Polymorphic relationship to taggable models
     */
    public function taggables()
    {
        return $this->morphedByMany(
            config('taggable.taggable.class_name', Taggable::class),
            'taggable'
        );
    }

    /**
     * Relationship to user
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}
```

## HasTags Trait Implementation

```php
<?php

namespace Humweb\Taggables\Traits;

use Humweb\Taggables\Models\Tag;
use Humweb\Taggables\Events\TagAttached;
use Humweb\Taggables\Events\TagDetached;
use Humweb\Taggables\Events\TagsSynced;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait HasTags
{
    /**
     * Get all tags attached to the model
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(
            config('taggable.tag_model', Tag::class),
            'taggable',
            config('taggable.tables.taggables', 'taggables'),
            'taggable_id',
            'tag_id'
        )->withTimestamps();
    }

    /**
     * Get tags for a specific user
     */
    public function userTags($userId): Collection
    {
        return $this->tags()->where('user_id', $userId)->get();
    }

    /**
     * Get only global tags
     */
    public function globalTags(): Collection
    {
        return $this->tags()->whereNull('user_id')->get();
    }

    /**
     * Attach tags to the model
     */
    public function tag($tags, ?string $type = null, ?int $userId = null): self
    {
        $tags = $this->parseTags($tags);

        $tagModels = Tag::findOrCreateMany($tags, $type, $userId);

        foreach ($tagModels as $tag) {
            if (!$this->tags()->where('tag_id', $tag->id)->exists()) {
                $this->tags()->attach($tag->id);
                event(new TagAttached($this, $tag));
            }
        }

        return $this;
    }

    /**
     * Attach tags as a specific user
     */
    public function tagAsUser($tags, $user, ?string $type = null): self
    {
        $userId = is_object($user) ? $user->id : $user;
        return $this->tag($tags, $type, $userId);
    }

    /**
     * Detach tags from the model
     */
    public function untag($tags = null, ?string $type = null, ?int $userId = null): self
    {
        if (is_null($tags)) {
            $query = $this->tags()
                ->when($type, fn($q) => $q->where('type', $type))
                ->when(!is_null($userId), fn($q) => $q->where('user_id', $userId));

            $tagIds = $query->pluck('tag_id');
            $this->tags()->detach($tagIds);

            foreach ($tagIds as $tagId) {
                event(new TagDetached($this, Tag::find($tagId)));
            }

            return $this;
        }

        $tags = $this->parseTags($tags);

        $tagModels = Tag::whereIn('slug', array_map([Str::class, 'slug'], $tags))
            ->when($type, fn($q) => $q->where('type', $type))
            ->when(!is_null($userId), fn($q) => $q->where('user_id', $userId))
            ->get();

        foreach ($tagModels as $tag) {
            $this->tags()->detach($tag->id);
            event(new TagDetached($this, $tag));
        }

        return $this;
    }

    /**
     * Detach tags as a specific user
     */
    public function untagAsUser($tags, $user, ?string $type = null): self
    {
        $userId = is_object($user) ? $user->id : $user;
        return $this->untag($tags, $type, $userId);
    }

    /**
     * Remove all tags and attach new ones
     */
    public function retag($tags, ?string $type = null, ?int $userId = null): self
    {
        $this->untag(null, $type, $userId);
        return $this->tag($tags, $type, $userId);
    }

    /**
     * Retag as a specific user
     */
    public function retagAsUser($tags, $user, ?string $type = null): self
    {
        $userId = is_object($user) ? $user->id : $user;
        return $this->retag($tags, $type, $userId);
    }

    /**
     * Sync tags (similar to Laravel's sync)
     */
    public function syncTags($tags, ?string $type = null, ?int $userId = null): self
    {
        $tags = $this->parseTags($tags);
        $tagModels = Tag::findOrCreateMany($tags, $type, $userId);

        $currentTags = $this->tags()
            ->when($type, fn($q) => $q->where('type', $type))
            ->when(!is_null($userId), fn($q) => $q->where('user_id', $userId))
            ->pluck('tag_id')
            ->toArray();

        $newTags = $tagModels->pluck('id')->toArray();

        // Detach tags that are no longer needed
        $toDetach = array_diff($currentTags, $newTags);
        if (!empty($toDetach)) {
            $this->tags()->detach($toDetach);
        }

        // Attach new tags
        $toAttach = array_diff($newTags, $currentTags);
        if (!empty($toAttach)) {
            $this->tags()->attach($toAttach);
        }

        event(new TagsSynced($this, $tagModels));

        return $this;
    }

    /**
     * Sync tags as a specific user
     */
    public function syncTagsAsUser($tags, $user, ?string $type = null): self
    {
        $userId = is_object($user) ? $user->id : $user;
        return $this->syncTags($tags, $type, $userId);
    }

    /**
     * Check if model has a specific tag
     */
    public function hasTag(string $tag, ?int $userId = null): bool
    {
        $slug = Str::slug($tag);

        $query = $this->tags()->where('slug', $slug);

        if ($userId && config('taggable.user_scope.mix_user_and_global', true)) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            });
        } elseif (!is_null($userId)) {
            $query->where('user_id', $userId);
        }

        return $query->exists();
    }

    /**
     * Check if model has a user-specific tag
     */
    public function hasUserTag(string $tag, $user): bool
    {
        $userId = is_object($user) ? $user->id : $user;
        $slug = Str::slug($tag);

        return $this->tags()
            ->where('slug', $slug)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Check if model has a global tag
     */
    public function hasGlobalTag(string $tag): bool
    {
        $slug = Str::slug($tag);

        return $this->tags()
            ->where('slug', $slug)
            ->whereNull('user_id')
            ->exists();
    }

    /**
     * Check if model has any of the given tags
     */
    public function hasAnyTag($tags, ?int $userId = null): bool
    {
        $tags = $this->parseTags($tags);
        $slugs = array_map([Str::class, 'slug'], $tags);

        $query = $this->tags()->whereIn('slug', $slugs);

        if ($userId && config('taggable.user_scope.mix_user_and_global', true)) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            });
        } elseif (!is_null($userId)) {
            $query->where('user_id', $userId);
        }

        return $query->exists();
    }

    /**
     * Check if model has all of the given tags
     */
    public function hasAllTags($tags, ?int $userId = null): bool
    {
        $tags = $this->parseTags($tags);
        $slugs = array_map([Str::class, 'slug'], $tags);

        $query = $this->tags()->whereIn('slug', $slugs);

        if ($userId && config('taggable.user_scope.mix_user_and_global', true)) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            });
        } elseif (!is_null($userId)) {
            $query->where('user_id', $userId);
        }

        $count = $query->count();

        return $count === count($slugs);
    }

    /**
     * Get tags with a specific type
     */
    public function tagsWithType(string $type, ?int $userId = null): Collection
    {
        $query = $this->tags()->where('type', $type);

        if (!is_null($userId)) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Parse tags from various formats
     */
    protected function parseTags($tags): array
    {
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if ($tags instanceof Collection) {
            $tags = $tags->toArray();
        }

        return array_map('trim', $tags);
    }

    /**
     * Scope to get models with any of the given tags
     */
    public function scopeWithAnyTags($query, $tags, ?string $type = null, ?int $userId = null)
    {
        $tags = is_array($tags) ? $tags : [$tags];
        $slugs = array_map([Str::class, 'slug'], $tags);

        return $query->whereHas('tags', function ($query) use ($slugs, $type, $userId) {
            $query->whereIn('slug', $slugs)
                  ->when($type, fn($q) => $q->where('type', $type));

            if ($userId && config('taggable.user_scope.mix_user_and_global', true)) {
                $query->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhereNull('user_id');
                });
            } elseif (!is_null($userId)) {
                $query->where('user_id', $userId);
            }
        });
    }

    /**
     * Scope to get models with all of the given tags
     */
    public function scopeWithAllTags($query, $tags, ?string $type = null, ?int $userId = null)
    {
        $tags = is_array($tags) ? $tags : [$tags];

        foreach ($tags as $tag) {
            $slug = Str::slug($tag);

            $query->whereHas('tags', function ($query) use ($slug, $type, $userId) {
                $query->where('slug', $slug)
                      ->when($type, fn($q) => $q->where('type', $type));

                if ($userId && config('taggable.user_scope.mix_user_and_global', true)) {
                    $query->where(function ($q) use ($userId) {
                        $q->where('user_id', $userId)->orWhereNull('user_id');
                    });
                } elseif (!is_null($userId)) {
                    $query->where('user_id', $userId);
                }
            });
        }

        return $query;
    }

    /**
     * Scope to get models with user tags
     */
    public function scopeWithUserTags($query, $tags, $user)
    {
        $userId = is_object($user) ? $user->id : $user;
        return $this->scopeWithAnyTags($query, $tags, null, $userId);
    }

    /**
     * Scope to get models with global tags
     */
    public function scopeWithGlobalTags($query, $tags)
    {
        $tags = is_array($tags) ? $tags : [$tags];
        $slugs = array_map([Str::class, 'slug'], $tags);

        return $query->whereHas('tags', function ($query) use ($slugs) {
            $query->whereIn('slug', $slugs)->whereNull('user_id');
        });
    }

    /**
     * Scope to get models without the given tags
     */
    public function scopeWithoutTags($query, $tags, ?string $type = null, ?int $userId = null)
    {
        $tags = is_array($tags) ? $tags : [$tags];
        $slugs = array_map([Str::class, 'slug'], $tags);

        return $query->whereDoesntHave('tags', function ($query) use ($slugs, $type, $userId) {
            $query->whereIn('slug', $slugs)
                  ->when($type, fn($q) => $q->where('type', $type));

            if (!is_null($userId)) {
                $query->where('user_id', $userId);
            }
        });
    }

    /**
     * Scope to get models tagged with a specific tag
     */
    public function scopeTaggedWith($query, string $tag, ?int $userId = null)
    {
        $slug = Str::slug($tag);

        return $query->whereHas('tags', function ($query) use ($slug, $userId) {
            $query->where('slug', $slug);

            if (!is_null($userId)) {
                $query->where('user_id', $userId);
            }
        });
    }
}
```

## Usage Examples in a Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Humweb\Taggables\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $post = Post::create($request->only(['title', 'content']));

        // Global tagging (available to all users)
        $post->tag($request->global_tags);

        // User-specific tagging
        $post->tagAsUser($request->personal_tags, Auth::user());

        // Tag with type
        $post->tag($request->categories, 'category');
        $post->tagAsUser($request->personal_categories, Auth::user(), 'category');

        return response()->json($post->load('tags'));
    }

    public function update(Request $request, Post $post)
    {
        $post->update($request->only(['title', 'content']));

        // Sync global tags
        if ($request->has('global_tags')) {
            $post->syncTags($request->global_tags);
        }

        // Sync user tags
        if ($request->has('personal_tags')) {
            $post->syncTagsAsUser($request->personal_tags, Auth::user());
        }

        return response()->json($post->load('tags'));
    }

    public function index(Request $request)
    {
        $query = Post::query();
        $userId = Auth::id();

        // Filter by tags (includes user + global tags by default)
        if ($request->has('tags')) {
            $query->withAnyTags($request->tags, null, $userId);
        }

        // Filter by user tags only
        if ($request->has('user_tags')) {
            $query->withUserTags($request->user_tags, Auth::user());
        }

        // Filter by global tags only
        if ($request->has('global_tags')) {
            $query->withGlobalTags($request->global_tags);
        }

        // Filter by all tags
        if ($request->has('all_tags')) {
            $query->withAllTags($request->all_tags, null, $userId);
        }

        // Exclude tags
        if ($request->has('exclude_tags')) {
            $query->withoutTags($request->exclude_tags, null, $userId);
        }

        return response()->json(
            $query->with('tags')->paginate()
        );
    }

    public function suggestions(Request $request)
    {
        $userId = Auth::id();

        // Get mixed suggestions (user + global)
        $suggestions = Tag::suggestTags($request->query('q'), $userId);

        return response()->json($suggestions);
    }

    public function userSuggestions(Request $request)
    {
        $userId = Auth::id();

        // Get only user's tags
        $suggestions = Tag::suggestTags($request->query('q'), $userId)
            ->filter(fn($tag) => $tag->user_id === $userId);

        return response()->json($suggestions);
    }

    public function popular()
    {
        $userId = Auth::id();

        // Get popular tags (mixed)
        $popularTags = Tag::popularTags(20, $userId);

        // Separate user and global popular tags
        $userPopular = Tag::popularUserTags(10, $userId);
        $globalPopular = Tag::popularGlobalTags(10);

        return response()->json([
            'mixed' => $popularTags,
            'user' => $userPopular,
            'global' => $globalPopular,
        ]);
    }

    public function cloud()
    {
        $userId = Auth::id();
        $tagCloud = Tag::tagCloud($userId);

        return view('tags.cloud', compact('tagCloud'));
    }

    public function userTags()
    {
        $userId = Auth::id();

        $tags = Tag::forUser($userId)
            ->withCount('taggables')
            ->orderBy('name')
            ->get();

        return response()->json($tags);
    }
}
```

## Migration Examples

### Tags Table Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('taggable.tables.tags', 'tags'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type')->nullable()->index();
            $table->integer('order_column')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Unique constraint: same slug can exist for different users/types
            $table->unique(['slug', 'user_id', 'type']);
            $table->index(['user_id', 'type', 'order_column']);
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('taggable.tables.tags', 'tags'));
    }
};
```

### Taggables Pivot Table Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('taggable.tables.taggables', 'taggables'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->timestamp('created_at')->nullable();

            $table->unique(['tag_id', 'taggable_id', 'taggable_type'], 'taggables_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('taggable.tables.taggables', 'taggables'));
    }
};
```

## Configuration Example

```php
<?php

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
