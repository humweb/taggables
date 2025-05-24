<?php

namespace Humweb\Taggables\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Taggable extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'taggables';
    
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tag_id',
        'taggable_id',
        'taggable_type',
    ];
    
    /**
     * Get the table name from config.
     *
     * @return string
     */
    public function getTable()
    {
        return config('taggable.tables.taggables', 'taggables');
    }
} 
