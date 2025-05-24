<?php

namespace Humweb\Taggables\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

interface Taggable
{
    /**
     * Get all tags attached to the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function tags(): MorphToMany;
    
    /**
     * Attach tags to the model.
     *
     * @param mixed $tags
     * @param string|null $type
     * @param int|null $userId
     * @return self
     */
    public function tag($tags, ?string $type = null, ?int $userId = null): self;
    
    /**
     * Detach tags from the model.
     *
     * @param mixed $tags
     * @param string|null $type
     * @param int|null $userId
     * @return self
     */
    public function untag($tags = null, ?string $type = null, ?int $userId = null): self;
    
    /**
     * Sync tags on the model.
     *
     * @param mixed $tags
     * @param string|null $type
     * @param int|null $userId
     * @return self
     */
    public function syncTags($tags, ?string $type = null, ?int $userId = null): self;
    
    /**
     * Check if model has a specific tag.
     *
     * @param string $tag
     * @param int|null $userId
     * @return bool
     */
    public function hasTag(string $tag, ?int $userId = null): bool;
    
    /**
     * Check if model has any of the given tags.
     *
     * @param mixed $tags
     * @param int|null $userId
     * @return bool
     */
    public function hasAnyTag($tags, ?int $userId = null): bool;
    
    /**
     * Check if model has all of the given tags.
     *
     * @param mixed $tags
     * @param int|null $userId
     * @return bool
     */
    public function hasAllTags($tags, ?int $userId = null): bool;
} 
