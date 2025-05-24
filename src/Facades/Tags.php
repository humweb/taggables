<?php

namespace Humweb\Taggables\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Humweb\Taggables\Models\Tag findOrCreate(string $name, ?string $type = null, ?int $userId = null)
 * @method static \Humweb\Taggables\Models\Tag findOrCreateForUser(string $name, $user, ?string $type = null)
 * @method static \Humweb\Taggables\Models\Tag findOrCreateGlobal(string $name, ?string $type = null)
 * @method static \Illuminate\Support\Collection findOrCreateMany(array $names, ?string $type = null, ?int $userId = null)
 * @method static \Illuminate\Support\Collection popularTags(int $limit = 20, ?int $userId = null)
 * @method static \Illuminate\Support\Collection popularUserTags(int $limit, int $userId)
 * @method static \Illuminate\Support\Collection popularGlobalTags(int $limit)
 * @method static \Illuminate\Support\Collection tagCloud(?int $userId = null)
 * @method static \Illuminate\Support\Collection suggestTags(string $query, ?int $userId = null)
 *
 * @see \Humweb\Taggables\Models\Tag
 */
class Tags extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \Humweb\Taggables\Models\Tag::class;
    }
} 
