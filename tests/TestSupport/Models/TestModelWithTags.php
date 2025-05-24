<?php

namespace Humweb\Taggables\Tests\TestSupport\Models;

use Humweb\Taggables\Traits\HasTags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Humweb\Taggables\Tests\TestSupport\Factories\TestModelFactory;

class TestModelWithTags extends Model
{
    use HasFactory, HasTags;

    protected $table = 'test_models'; // Ensure this table exists via migrations

    protected $fillable = ['name'];

    protected static function newFactory()
    {
        return TestModelFactory::new();
    }
} 
