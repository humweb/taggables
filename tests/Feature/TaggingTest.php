<?php

use Humweb\Taggables\Tests\TestSupport\Models\TestModel;
use Humweb\Taggables\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can tag a model', function () {
    $model = TestModel::create(['name' => 'Test']);
    
    $model->tag('laravel');
    
    expect($model->tags)->toHaveCount(1);
    expect($model->tags->first()->name)->toBe('laravel');
});

it('can tag with multiple tags', function () {
    $model = TestModel::create(['name' => 'Test']);
    
    $model->tag(['laravel', 'php', 'web']);
    
    expect($model->tags)->toHaveCount(3);
    expect($model->tags->pluck('name')->toArray())->toEqualCanonicalizing(['laravel', 'php', 'web']);
});

it('can tag as user', function () {
    $model = TestModel::create(['name' => 'Test']);
    $userId = 1;
    
    $model->tagAsUser(['personal', 'favorite'], $userId);
    
    expect($model->tags)->toHaveCount(2);
    expect($model->tags->every(fn($tag) => $tag->user_id === $userId))->toBeTrue();
});

it('can mix global and user tags', function () {
    $model = TestModel::create(['name' => 'Test']);
    $userId = 1;
    
    $model->tag('laravel'); // Global tag
    $model->tagAsUser('favorite', $userId); // User tag
    
    expect($model->tags)->toHaveCount(2);
    expect($model->globalTags())->toHaveCount(1);
    expect($model->userTags($userId))->toHaveCount(1);
});

it('can untag', function () {
    $model = TestModel::create(['name' => 'Test']);
    
    $model->tag(['laravel', 'php', 'web']);
    $model->untag('php');
    
    expect($model->tags)->toHaveCount(2);
    expect($model->hasTag('php'))->toBeFalse();
});

it('can check if model has tag', function () {
    $model = TestModel::create(['name' => 'Test']);
    
    $model->tag('laravel');
    
    expect($model->hasTag('laravel'))->toBeTrue();
    expect($model->hasTag('php'))->toBeFalse();
});

it('can sync tags', function () {
    $model = TestModel::create(['name' => 'Test']);
    
    $model->tag(['laravel', 'php']);
    $model->syncTags(['laravel', 'vue', 'javascript']);
    
    $model->refresh();
    
    expect($model->tags)->toHaveCount(3);
    expect($model->hasTag('php'))->toBeFalse();
    expect($model->hasTag('vue'))->toBeTrue();
});

it('can query by tags', function () {
    TestModel::create(['name' => 'Post 1'])->tag(['laravel', 'php']);
    TestModel::create(['name' => 'Post 2'])->tag(['vue', 'javascript']);
    TestModel::create(['name' => 'Post 3'])->tag(['laravel', 'vue']);
    
    $laravelPosts = TestModel::withAnyTags(['laravel'])->get();
    expect($laravelPosts)->toHaveCount(2);
    
    $laravelAndVuePosts = TestModel::withAllTags(['laravel', 'vue'])->get();
    expect($laravelAndVuePosts)->toHaveCount(1);
});

it('can retag model', function () {
    $model = TestModel::create(['name' => 'Test']);
    
    $model->tag(['laravel', 'php']);
    expect($model->tags)->toHaveCount(2);
    
    $model->retag(['vue', 'javascript']);
    $model->refresh();
    
    expect($model->tags)->toHaveCount(2);
    expect($model->hasTag('laravel'))->toBeFalse();
    expect($model->hasTag('vue'))->toBeTrue();
});

it('can check if model has any tag', function () {
    $model = TestModel::create(['name' => 'Test']);
    
    $model->tag(['laravel', 'php']);
    
    expect($model->hasAnyTag(['laravel', 'vue']))->toBeTrue();
    expect($model->hasAnyTag(['vue', 'javascript']))->toBeFalse();
});

it('can check if model has all tags', function () {
    $model = TestModel::create(['name' => 'Test']);
    
    $model->tag(['laravel', 'php', 'web']);
    
    expect($model->hasAllTags(['laravel', 'php']))->toBeTrue();
    expect($model->hasAllTags(['laravel', 'vue']))->toBeFalse();
});

it('can get tags with type', function () {
    $model = TestModel::create(['name' => 'Test']);
    
    $model->tag('laravel', 'framework');
    $model->tag('personal', 'category');
    
    expect($model->tagsWithType('framework'))->toHaveCount(1);
    expect($model->tagsWithType('category'))->toHaveCount(1);
});

it('can query models without specific tags', function () {
    TestModel::create(['name' => 'Post 1'])->tag(['laravel', 'php']);
    TestModel::create(['name' => 'Post 2'])->tag(['vue', 'javascript']);
    TestModel::create(['name' => 'Post 3'])->tag(['react']);
    
    $withoutLaravel = TestModel::withoutTags(['laravel'])->get();
    expect($withoutLaravel)->toHaveCount(2);
    expect($withoutLaravel->pluck('name')->toArray())->toEqualCanonicalizing(['Post 2', 'Post 3']);
});

it('generates unique slugs for tags', function () {
    $tag1 = Tag::findOrCreate('Laravel Framework');
    $tag2 = Tag::findOrCreate('Laravel Framework'); // Same name
    
    expect($tag1->id)->toBe($tag2->id); // Should be the same tag
    expect($tag1->slug)->toBe('laravel-framework');
});

it('can attach and detach single tag models', function () {
    $model = TestModel::create(['name' => 'Test']);
    $tag = Tag::findOrCreate('laravel');
    
    $model->attachTag($tag);
    expect($model->tags)->toHaveCount(1);
    
    $model->detachTag($tag);
    $model->refresh();
    expect($model->tags)->toHaveCount(0);
});

it('handles comma-separated tags', function () {
    $model = TestModel::create(['name' => 'Test']);
    
    $model->tag('laravel, php, web development');
    
    expect($model->tags)->toHaveCount(3);
    expect($model->tags->pluck('name')->toArray())->toContain('laravel', 'php', 'web development');
});

it('can create user-specific and global tags with same name', function () {
    $tag1 = Tag::findOrCreate('favorite', null, 1); // User 1's tag
    $tag2 = Tag::findOrCreate('favorite', null, 2); // User 2's tag
    $tag3 = Tag::findOrCreate('favorite', null, null); // Global tag
    
    expect($tag1->id)->not->toBe($tag2->id);
    expect($tag1->id)->not->toBe($tag3->id);
    expect($tag1->user_id)->toBe(1);
    expect($tag2->user_id)->toBe(2);
    expect($tag3->user_id)->toBeNull();
}); 
