<?php

namespace Humweb\Taggables\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class Tag extends Model
{
    use HasFactory;
    
    protected $fillable = ['name', 'slug', 'user_id', 'type'];
    
    public function getTable()
    {
        return config('taggable.tables.tags', 'tags');
    }
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = static::generateSlug($tag->name);
            }
        });
    }
    
    /**
     * Generate a unique slug for the tag
     */
    protected static function generateSlug(string $name): string
    {
        $slugger = config('taggable.slugger');
        
        if (is_callable($slugger)) {
            return $slugger($name);
        }
        
        return Str::slug($name);
    }
    

    /**
     * Find or create a tag by name
     */
    public static function findOrCreate(string $name, ?string $type = null, ?int $userId = null): self
    {
        $slug = static::generateSlug($name);
        
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
        
        if ($tags->isEmpty()) {
            return $tags;
        }
        
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
     * Get all taggable models for this tag
     */
    public function taggables()
    {
        return $this->hasMany(Taggable::class, 'tag_id');
    }
    
    /**
     * Relationship to user
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'));
    }
} 
