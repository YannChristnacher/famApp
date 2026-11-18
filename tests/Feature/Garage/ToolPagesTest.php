<?php

use App\Models\Garage\Brand;
use App\Models\Garage\ToolShelf;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

beforeEach(fn () => Storage::fake('public'));

it('renders the creation form with the shelves of the user and every brand of the family', function () {
    $user = signIn();
    $shelf = ToolShelf::factory()->for($user)->create(['name' => 'Établi']);
    ToolShelf::factory()->for(User::factory())->create(['name' => 'Étagère de Paul']);
    $makita = Brand::factory()->create(['name' => 'Makita']);
    $bosch = Brand::factory()->create(['name' => 'bosch']);

    get(route('garage.tools.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Garage/Tools/Create')
            ->where('shelves', [['id' => $shelf->id, 'name' => 'Établi', 'position' => 1]])
            ->where('brands', [['id' => $bosch->id, 'name' => 'bosch'], ['id' => $makita->id, 'name' => 'Makita']]));
});

it('renders the page of a tool with its photos and accessories in order', function () {
    $user = signIn();
    $shelf = ToolShelf::factory()->for($user)->create(['name' => 'Établi']);
    $tool = toolWithPhotos($user, ['front', 'side']);
    $tool->brand()->associate($bosch = Brand::factory()->create(['name' => 'Bosch']))->save();
    $tool->shelves()->attach($shelf);
    $tool->accessories()->createMany([['name' => 'Chargeur', 'position' => 2], ['name' => 'Mallette', 'position' => 1]]);
    $photos = $tool->photos()->get();
    $accessories = $tool->accessories()->get();

    $expected = [
        'id' => $tool->id,
        'name' => 'Perceuse',
        'brand' => ['id' => $bosch->id, 'name' => 'Bosch'],
        'thumbnail_url' => Storage::disk('public')->url('tools/front.jpg'),
        'shelves' => [['id' => $shelf->id, 'name' => 'Établi']],
        'photos' => [
            ['id' => $photos[0]->id, 'url' => Storage::disk('public')->url('tools/front.jpg')],
            ['id' => $photos[1]->id, 'url' => Storage::disk('public')->url('tools/side.jpg')],
        ],
        'accessories' => [
            ['id' => $accessories[0]->id, 'name' => 'Mallette'],
            ['id' => $accessories[1]->id, 'name' => 'Chargeur'],
        ],
    ];

    get(route('garage.tools.show', $tool))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Garage/Tools/Show')->where('tool', $expected));

    get(route('garage.tools.edit', $tool))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Garage/Tools/Edit')
            ->where('tool', $expected)
            ->where('shelves', [['id' => $shelf->id, 'name' => 'Établi', 'position' => 1]])
            ->where('brands', [['id' => $bosch->id, 'name' => 'Bosch']]));
});
