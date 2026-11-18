<?php

use App\Models\Garage\Brand;
use App\Services\Garage\BrandService;
use Illuminate\Support\Facades\DB;

it('returns no brand for an empty name', function (?string $name) {
    expect(app(BrandService::class)->resolve($name))->toBeNull()
        ->and(Brand::query()->count())->toBe(0);
})->with(['null' => null, 'empty' => '', 'spaces' => '   ']);

it('creates a brand with the typed case, trimmed, and its normalized name', function () {
    $brand = app(BrandService::class)->resolve('  DeWalt ');

    expect($brand?->name)->toBe('DeWalt')
        ->and($brand?->normalized_name)->toBe('dewalt')
        ->and(Brand::query()->count())->toBe(1);
});

it('reuses the brand whose normalized name matches', function () {
    $makita = Brand::factory()->create(['name' => 'Makita']);

    expect(app(BrandService::class)->resolve('MAKITA')?->id)->toBe($makita->id)
        ->and(Brand::query()->count())->toBe(1);
});

it('reuses the brand created by a concurrent request between its check and its insert', function () {
    // The concurrent request inserts "Bosch" right after our check found nothing.
    Brand::creating(function (Brand $brand) {
        Brand::flushEventListeners();
        DB::table('brands')->insert(['name' => 'Bosch', 'normalized_name' => 'bosch', 'created_at' => now(), 'updated_at' => now()]);
    });

    $brand = app(BrandService::class)->resolve('bosch');

    expect($brand?->name)->toBe('Bosch')
        ->and(Brand::query()->count())->toBe(1);
});

it('enforces the uniqueness of the normalized name in the database', function () {
    Brand::factory()->create(['name' => 'Festool']);

    expect(fn () => Brand::factory()->create(['name' => 'festool ']))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});
