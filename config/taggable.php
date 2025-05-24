<?php

return [
    /*
     * The tag model to use
     */
    'tag_model' => \Humweb\Taggables\Models\Tag::class,
    
    /*
     * Table names
     */
    'tables' => [
        'tags' => 'tags',
        'taggables' => 'taggables',
    ],
    
    /*
     * Slug generation
     */
    'slugger' => null, // null defaults to Str::slug
    
    /*
     * Tag name validation rules
     */
    'rules' => [
        'name' => ['required', 'string', 'max:255'],
    ],
    
    /*
     * Auto-delete unused tags
     */
    'delete_unused_tags' => false,
    
    /*
     * User scoping configuration
     */
    'user_scope' => [
        // Enable user-scoped tags
        'enabled' => true,
        
        // Allow creation of global tags (null user_id)
        'allow_global_tags' => false,
        
        // Include global tags when querying user tags
        'mix_user_and_global' => true,
    ],
    
    /*
     * Cache configuration
     */
    'cache' => [
        'enabled' => true,
        'key_prefix' => 'taggable',
        'ttl' => 3600,
    ],
]; 
