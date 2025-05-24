<?php

namespace Humweb\Taggables\Events;

use Humweb\Taggables\Models\Tag;
use Illuminate\Database\Eloquent\Model;

class TagAttached
{
    /**
     * The model that was tagged.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $taggable;
    
    /**
     * The tag that was attached.
     *
     * @var \Humweb\Taggables\Models\Tag
     */
    public $tag;
    
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $taggable
     * @param  \Humweb\Taggables\Models\Tag  $tag
     * @return void
     */
    public function __construct(Model $taggable, Tag $tag)
    {
        $this->taggable = $taggable;
        $this->tag = $tag;
    }
} 
