<?php

use Humweb\Taggables\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a slug automatically', function () {
    $tag = Tag::create(['name' => 'Laravel Framework']);
    
    expect($tag->slug)->toBe('laravel-framework');
});


it('can find popular tags', function () {
    $popularTag = Tag::factory()->create(['name' => 'Popular']);
    $unpopularTag = Tag::factory()->create(['name' => 'Unpopular']);
    
    // Create some test models and tag them
    $models = \Humweb\Taggables\Tests\TestSupport\Models\TestModel::factory()
        ->count(5)
        ->create();
    
    foreach ($models as $model) {
        $model->attachTag($popularTag);
    }
    
    $models->first()->attachTag($unpopularTag);
    
    $popular = Tag::popularTags(1);
    
    expect($popular)->toHaveCount(1);
    expect($popular->first()->id)->toBe($popularTag->id);
});

it('can suggest tags based on partial input', function () {
    Tag::create(['name' => 'Laravel']);
    Tag::create(['name' => 'Laravel Nova']);
    Tag::create(['name' => 'Vue.js']);
    
    $suggestions = Tag::suggestTags('lara');
    
    expect($suggestions)->toHaveCount(2);
    expect($suggestions->pluck('name')->toArray())->toContain('Laravel', 'Laravel Nova');
});

it('can get tag cloud with weights', function () {
    $tag1 = Tag::factory()->create(['name' => 'Most Popular']);
    $tag2 = Tag::factory()->create(['name' => 'Medium Popular']);
    $tag3 = Tag::factory()->create(['name' => 'Least Popular']);
    
    // Create models and tag them
    $models = \Humweb\Taggables\Tests\TestSupport\Models\TestModel::factory()
        ->count(10)
        ->create();
    
    // Tag 1 gets 10 uses
    foreach ($models as $model) {
        $model->attachTag($tag1);
    }
    
    // Tag 2 gets 5 uses
    foreach ($models->take(5) as $model) {
        $model->attachTag($tag2);
    }
    
    // Tag 3 gets 1 use
    $models->first()->attachTag($tag3);
    
    $cloud = Tag::tagCloud();
    
    expect($cloud)->toHaveCount(3);
    
    $weights = $cloud->pluck('weight', 'name')->toArray();
    expect($weights['Most Popular'])->toEqual(10); // Max weight
    expect($weights['Medium Popular'])->toBeGreaterThan(0)->toBeLessThan(10);
    expect($weights['Least Popular'])->toEqual(0); // Min weight
});

it('can scope to user tags', function () {
    Tag::create(['name' => 'Global', 'user_id' => null]);
    Tag::create(['name' => 'User 1 Tag', 'user_id' => 1]);
    Tag::create(['name' => 'User 2 Tag', 'user_id' => 2]);
    
    $userTags = Tag::forUser(1)->get();
    
    expect($userTags)->toHaveCount(1);
    expect($userTags->first()->name)->toBe('User 1 Tag');
});

it('can scope to global tags', function () {
    Tag::create(['name' => 'Global 1', 'user_id' => null]);
    Tag::create(['name' => 'Global 2', 'user_id' => null]);
    Tag::create(['name' => 'User Tag', 'user_id' => 1]);
    
    $globalTags = Tag::global()->get();
    
    expect($globalTags)->toHaveCount(2);
    expect($globalTags->pluck('name')->toArray())->toContain('Global 1', 'Global 2');
});

it('can get user tags with global tags', function () {
    Tag::create(['name' => 'Global', 'user_id' => null]);
    Tag::create(['name' => 'User 1 Tag', 'user_id' => 1]);
    Tag::create(['name' => 'User 2 Tag', 'user_id' => 2]);
    
    $tags = Tag::forUserWithGlobal(1)->get();
    
    expect($tags)->toHaveCount(2);
    expect($tags->pluck('name')->toArray())->toContain('Global', 'User 1 Tag');
});

it('can find unused tags', function () {
    $usedTag = Tag::factory()->create(['name' => 'Used']);
    $unusedTag = Tag::factory()->create(['name' => 'Unused']);
    
    $model = \Humweb\Taggables\Tests\TestSupport\Models\TestModel::factory()->create();
    $model->attachTag($usedTag);
    
    $unused = Tag::unusedTags()->get();
    
    expect($unused)->toHaveCount(1);
    expect($unused->first()->id)->toBe($unusedTag->id);
});

it('can check if tag is global', function () {
    $globalTag = Tag::create(['name' => 'Global', 'user_id' => null]);
    $userTag = Tag::create(['name' => 'User Tag', 'user_id' => 1]);
    
    expect($globalTag->isGlobal())->toBeTrue();
    expect($userTag->isGlobal())->toBeFalse();
});

it('can check if tag is owned by user', function () {
    $tag = Tag::create(['name' => 'My Tag', 'user_id' => 1]);
    
    expect($tag->isOwnedBy(1))->toBeTrue();
    expect($tag->isOwnedBy(2))->toBeFalse();
    
    // Test with user object
    $user = new stdClass();
    $user->id = 1;
    expect($tag->isOwnedBy($user))->toBeTrue();
}); 
