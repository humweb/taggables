<?php

namespace Humweb\Taggables\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TagsSynced
{
    /**
     * The model that had tags synced.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $taggable;
    
    /**
     * The tags that were synced.
     *
     * @var \Illuminate\Support\Collection
     */
    public $tags;
    
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $taggable
     * @param  \Illuminate\Support\Collection  $tags
     * @return void
     */
    public function __construct(Model $taggable, Collection $tags)
    {
        $this->taggable = $taggable;
        $this->tags = $tags;
    }
} 
