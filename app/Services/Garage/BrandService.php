<?php

namespace App\Services\Garage;

use App\Models\Garage\Brand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The brands shared by the family. A typed brand is matched on its normalized
 * name; the first member who types it chooses its case.
 */
class BrandService
{
    /**
     * Every brand, by name, for the tool form.
     *
     * @return Collection<int, Brand>
     */
    public function all(): Collection
    {
        return Brand::query()->orderBy('normalized_name')->orderBy('id')->get();
    }

    /**
     * The brand matching the typed name, created when none matches; null for
     * an empty name. When a concurrent request creates the same brand between
     * the check and the insert, its brand is returned.
     */
    public function resolve(?string $name): ?Brand
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return $this->find($name) ?? $this->create($name);
    }

    protected function find(string $name): ?Brand
    {
        return Brand::query()->where('normalized_name', Brand::normalizeName($name))->first();
    }

    private function create(string $name): Brand
    {
        try {
            // Savepoint when called inside a transaction: the failed insert
            // must not abort it.
            return DB::transaction(fn () => Brand::query()->create(['name' => $name]));
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'normalized_name')) {
                throw $exception;
            }

            return Brand::query()->useWritePdo()->where('normalized_name', Brand::normalizeName($name))->firstOrFail();
        }
    }
}
