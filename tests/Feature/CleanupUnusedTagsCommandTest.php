<?php

namespace Humweb\Taggables\Tests\Feature\Commands;

use Humweb\Taggables\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Humweb\Taggables\Tests\TestSupport\Models\TestModel;

uses(RefreshDatabase::class);

it('handles no unused tags', function () {
    $this->artisan('tags:cleanup')
        ->expectsOutput('No unused tags found.')
        ->assertExitCode(0);
});

it('cleans up unused tags with confirmation', function () {
    $usedTag = Tag::factory()->create(['name' => 'Used Tag']);
    $unusedTag1 = Tag::factory()->create(['name' => 'Unused Tag 1']);
    $unusedTag2 = Tag::factory()->create(['name' => 'Unused Tag 2']);

    $model = TestModel::factory()->create();
    $model->attachTag($usedTag);

    $this->artisan('tags:cleanup')
        ->expectsTable(
            ['ID', 'Name', 'User ID', 'Type'],
            [
                [$unusedTag1->id, $unusedTag1->name, 'Global', 'None'],
                [$unusedTag2->id, $unusedTag2->name, 'Global', 'None'],
            ]
        )
        ->expectsConfirmation('Do you want to delete these tags?', 'yes')
        ->expectsOutput('Successfully deleted 2 unused tags.')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('tags', ['id' => $unusedTag1->id]);
    $this->assertDatabaseMissing('tags', ['id' => $unusedTag2->id]);
    $this->assertDatabaseHas('tags', ['id' => $usedTag->id]);
});

it('cancels cleanup if user does not confirm', function () {
    $unusedTag = Tag::factory()->create(['name' => 'Unused Tag']);

    $this->artisan('tags:cleanup')
        ->expectsTable(
            ['ID', 'Name', 'User ID', 'Type'],
            [
                [$unusedTag->id, $unusedTag->name, 'Global', 'None'],
            ]
        )
        ->expectsConfirmation('Do you want to delete these tags?', 'no')
        ->expectsOutput('Operation cancelled.')
        ->assertExitCode(0);

    $this->assertDatabaseHas('tags', ['id' => $unusedTag->id]);
});

it('cleans up unused tags with force option', function () {
    $unusedTag = Tag::factory()->create(['name' => 'Unused Tag']);

    $this->artisan('tags:cleanup --force')
        ->expectsOutput('Found 1 unused tags.')
        ->expectsOutput('Successfully deleted 1 unused tags.')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('tags', ['id' => $unusedTag->id]);
});

it('cleans up unused tags for a specific user', function () {
    $user1Tag = Tag::factory()->create(['name' => 'User 1 Tag', 'user_id' => 1]);
    $user2Tag = Tag::factory()->create(['name' => 'User 2 Tag', 'user_id' => 2]);
    $globalUnusedTag = Tag::factory()->create(['name' => 'Global Unused']);

    $this->artisan('tags:cleanup --user=1 --force')
        ->expectsOutput('Looking for unused tags for user ID: 1')
        ->expectsOutput('Found 1 unused tags.')
        ->expectsOutput('Successfully deleted 1 unused tags.')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('tags', ['id' => $user1Tag->id]);
    $this->assertDatabaseHas('tags', ['id' => $user2Tag->id]);
    $this->assertDatabaseHas('tags', ['id' => $globalUnusedTag->id]);
});

it('cleans up unused global tags', function () {
    $userTag = Tag::factory()->create(['name' => 'User Tag', 'user_id' => 1]);
    $globalUnusedTag1 = Tag::factory()->create(['name' => 'Global Unused 1', 'user_id' => null]);
    $globalUsedTag = Tag::factory()->create(['name' => 'Global Used', 'user_id' => null]);
    
    $model = TestModel::factory()->create();
    $model->attachTag($globalUsedTag);


    $this->artisan('tags:cleanup --global --force')
        ->expectsOutput('Looking for unused global tags')
        ->expectsOutput('Found 1 unused tags.') // Only Global Unused 1
        ->expectsOutput('Successfully deleted 1 unused tags.')
        ->assertExitCode(0);

    $this->assertDatabaseHas('tags', ['id' => $userTag->id]);
    $this->assertDatabaseMissing('tags', ['id' => $globalUnusedTag1->id]);
    $this->assertDatabaseHas('tags', ['id' => $globalUsedTag->id]);
});

it('handles specific user with no unused tags', function () {
    Tag::factory()->create(['name' => 'User 2 Tag', 'user_id' => 2]);
    $usedTagUser1 = Tag::factory()->create(['name' => 'Used Tag User 1', 'user_id' => 1]);
    $model = TestModel::factory()->create();
    $model->attachTag($usedTagUser1);


    $this->artisan('tags:cleanup --user=1 --force')
        ->expectsOutput('Looking for unused tags for user ID: 1')
        ->expectsOutput('No unused tags found.')
        ->assertExitCode(0);
});

it('handles global with no unused tags', function () {
    Tag::factory()->create(['name' => 'User Tag', 'user_id' => 1]);
    $globalUsedTag = Tag::factory()->create(['name' => 'Global Used', 'user_id' => null]);
    $model = TestModel::factory()->create();
    $model->attachTag($globalUsedTag);

    $this->artisan('tags:cleanup --global --force')
        ->expectsOutput('Looking for unused global tags')
        ->expectsOutput('No unused tags found.')
        ->assertExitCode(0);
}); 
