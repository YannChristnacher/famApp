<?php

namespace App\Providers;

use App\Services\Core\MemberSearchSource;
use App\Services\Core\Search\SearchRegistry;
use App\Services\Garage\ToolSearchSource;
use App\Services\Library\BookSearchSource;
use Illuminate\Support\ServiceProvider;

/**
 * The search sources of the modules, in the order of their groups in the
 * palette of the header. A new module registers its own source here.
 */
class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SearchRegistry::class);
    }

    public function boot(SearchRegistry $registry): void
    {
        $registry->register(BookSearchSource::class);
        $registry->register(ToolSearchSource::class);
        $registry->register(MemberSearchSource::class);
    }
}
