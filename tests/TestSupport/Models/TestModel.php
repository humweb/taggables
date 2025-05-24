<?php

namespace Humweb\Taggables\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Humweb\Taggables\Traits\HasTags;

class TestModel extends Model
{
    use HasFactory, HasTags;
    
    protected $fillable = ['name'];
    
    protected static function newFactory()
    {
        return \Humweb\Taggables\Database\Factories\TestModelFactory::new();
    }
} 
