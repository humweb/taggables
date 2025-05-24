<?php

use Humweb\Taggables\Tests\TestSupport\Models\TestModel;
use Humweb\Taggables\Models\Tag;
use Humweb\Taggables\Events\TagAttached;
use Humweb\Taggables\Events\TagDetached;
use Humweb\Taggables\Events\TagsSynced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('fires event when tag is attached', function () {
    Event::fake([TagAttached::class]);
    
    $model = TestModel::create(['name' => 'Test']);
    $model->tag('laravel');
    
    Event::assertDispatched(TagAttached::class, function ($event) use ($model) {
        return $event->taggable->id === $model->id
            && $event->tag->name === 'laravel';
    });
});

it('fires event when tag is detached', function () {
    Event::fake([TagDetached::class]);
    
    $model = TestModel::create(['name' => 'Test']);
    $model->tag('laravel');
    $model->untag('laravel');
    
    Event::assertDispatched(TagDetached::class, function ($event) use ($model) {
        return $event->taggable->id === $model->id
            && $event->tag->name === 'laravel';
    });
});

it('fires event when tags are synced', function () {
    Event::fake([TagsSynced::class]);
    
    $model = TestModel::create(['name' => 'Test']);
    $model->syncTags(['laravel', 'php']);
    
    Event::assertDispatched(TagsSynced::class, function ($event) use ($model) {
        return $event->taggable->id === $model->id
            && $event->tags->count() === 2;
    });
});

it('fires multiple attach events for multiple tags', function () {
    Event::fake([TagAttached::class]);
    
    $model = TestModel::create(['name' => 'Test']);
    $model->tag(['laravel', 'php', 'vue']);
    
    Event::assertDispatchedTimes(TagAttached::class, 3);
});

it('does not fire event when attaching existing tag', function () {
    Event::fake([TagAttached::class]);
    
    $model = TestModel::create(['name' => 'Test']);
    $model->tag('laravel');
    
    Event::assertDispatchedTimes(TagAttached::class, 1);
    
    // Try to attach the same tag again
    $model->tag('laravel');
    
    // Should still be only 1 event
    Event::assertDispatchedTimes(TagAttached::class, 1);
}); 
