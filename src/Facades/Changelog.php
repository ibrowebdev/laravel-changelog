<?php

namespace Ibrohim\Changelog\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ibrohim\Changelog\Models\ChangelogRepository addRepository(string $owner, string $repo, string $secret, string $branch = 'main')
 * @method static \Ibrohim\Changelog\Models\ChangelogRepository|null findRepository(string $owner, string $repo)
 * @method static \Illuminate\Database\Eloquent\Collection repositories()
 * @method static \Illuminate\Database\Eloquent\Collection publishedEntries(?int $limit = null)
 * @method static \Illuminate\Database\Eloquent\Collection draftEntries()
 * @method static \Illuminate\Database\Eloquent\Collection entriesByType(string $type)
 * @method static \Ibrohim\Changelog\Models\ChangelogEntry createEntry(array $attributes)
 * @method static \Ibrohim\Changelog\Models\ChangelogEntry publish(int $entryId)
 * @method static \Ibrohim\Changelog\Models\ChangelogEntry unpublish(int $entryId)
 * @method static int pendingCount()
 * @method static array validTypes()
 *
 * @see \Ibrohim\Changelog\ChangelogManager
 */
class Changelog extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * This string must match the key used in the ServiceProvider's
     * $this->app->singleton('changelog', ...) binding. The Facade
     * resolves the 'changelog' key from the container and proxies
     * all static method calls to the resolved ChangelogManager instance.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'changelog';
    }
}
