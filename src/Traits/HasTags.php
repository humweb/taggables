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
        )->withPivot('created_at');
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
        
        if (empty($tags)) {
            return $this;
        }
        
        $tagClass = config('taggable.tag_model', Tag::class);
        $tagModels = $tagClass::findOrCreateMany($tags, $type, $userId);
        
        foreach ($tagModels as $tag) {
            if (!$this->tags()->where('tag_id', $tag->id)->exists()) {
                $this->tags()->attach($tag->id, ['created_at' => now()]);
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
            
            $tagClass = config('taggable.tag_model', Tag::class);
            foreach ($tagIds as $tagId) {
                $tag = $tagClass::find($tagId);
                if ($tag) {
                    event(new TagDetached($this, $tag));
                }
            }
            
            return $this;
        }
        
        $tags = $this->parseTags($tags);
        
        if (empty($tags)) {
            return $this;
        }
        
        $tagClass = config('taggable.tag_model', Tag::class);
        $tagModels = $tagClass::whereIn('slug', array_map([Str::class, 'slug'], $tags))
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
        $tagClass = config('taggable.tag_model', Tag::class);
        $tagModels = $tagClass::findOrCreateMany($tags, $type, $userId);
        
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
            $this->tags()->attach($toAttach, ['created_at' => now()]);
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
     * Attach a single tag
     */
    public function attachTag($tag): self
    {
        if (!$tag instanceof Tag) {
            return $this;
        }
        
        if (!$this->tags()->where('tag_id', $tag->id)->exists()) {
            $this->tags()->attach($tag->id, ['created_at' => now()]);
            event(new TagAttached($this, $tag));
        }
        
        return $this;
    }
    
    /**
     * Detach a single tag
     */
    public function detachTag($tag): self
    {
        if (!$tag instanceof Tag) {
            return $this;
        }
        
        $this->tags()->detach($tag->id);
        event(new TagDetached($this, $tag));
        
        return $this;
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
        
        if (!is_array($tags)) {
            $tags = [$tags];
        }
        
        return array_filter(array_map('trim', $tags));
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
