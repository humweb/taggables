<?php

namespace Humweb\Taggables\Commands;

use Illuminate\Console\Command;
use Humweb\Taggables\Models\Tag;

class CleanupUnusedTagsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tags:cleanup 
                            {--user= : Cleanup tags for a specific user ID}
                            {--global : Cleanup only global tags}
                            {--force : Force deletion without confirmation}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up unused tags from the database';
    
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $query = Tag::unusedTags();
        
        if ($userId = $this->option('user')) {
            $query->forUser($userId);
            $this->info("Looking for unused tags for user ID: {$userId}");
        } elseif ($this->option('global')) {
            $query->global();
            $this->info("Looking for unused global tags");
        } else {
            $this->info("Looking for all unused tags");
        }
        
        $unusedTags = $query->get();
        
        if ($unusedTags->isEmpty()) {
            $this->info('No unused tags found.');
            return static::SUCCESS;
        }
        
        $this->info("Found {$unusedTags->count()} unused tags.");
        
        if (!$this->option('force')) {
            $this->table(
                ['ID', 'Name', 'User ID', 'Type'],
                $unusedTags->map(function ($tag) {
                    return [
                        $tag->id,
                        $tag->name,
                        $tag->user_id ?? 'Global',
                        $tag->type ?? 'None'
                    ];
                })
            );
            
            if (!$this->confirm('Do you want to delete these tags?')) {
                $this->info('Operation cancelled.');
                return static::SUCCESS;
            }
        }
        
        $count = $unusedTags->count();
        
        foreach ($unusedTags as $tag) {
            $tag->delete();
        }
        
        $this->info("Successfully deleted {$count} unused tags.");
        
        return static::SUCCESS;
    }
} 
