<?php

namespace App\Services\Core\Search;

use Illuminate\Contracts\Container\Container;

/**
 * The search sources of the modules, in the order of their groups in the
 * palette. Filled by SearchServiceProvider; a source is resolved by the
 * container, so it may inject its own services.
 */
class SearchRegistry
{
    /** @var list<class-string<SearchSource>> */
    private array $sources = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<SearchSource>  $source
     */
    public function register(string $source): void
    {
        if (! in_array($source, $this->sources, true)) {
            $this->sources[] = $source;
        }
    }

    /**
     * @return list<SearchSource>
     */
    public function sources(): array
    {
        return array_map(fn (string $source): SearchSource => $this->container->make($source), $this->sources);
    }
}
