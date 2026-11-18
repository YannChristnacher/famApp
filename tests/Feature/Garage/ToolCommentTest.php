<?php

use App\Http\Requests\Garage\ToolRequest;
use App\Models\Garage\Tool;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(fn () => Storage::fake('public'));

it('adds a nullable comment column to the tools', function () {
    expect(Schema::hasColumn('tools', 'comment'))->toBeTrue()
        ->and(Tool::factory()->create()->refresh()->comment)->toBeNull();
});

it('creates a tool with a trimmed comment, keeping its line breaks', function () {
    signIn();

    post(route('garage.tools.store'), [
        'name' => 'Perceuse',
        'comment' => "  Batterie fatiguée\nPrévoir le chargeur  ",
    ])->assertSessionHasNoErrors();

    expect(Tool::query()->sole()->comment)->toBe("Batterie fatiguée\nPrévoir le chargeur");
});

it('creates a tool without comment', function (array $comment) {
    signIn();

    post(route('garage.tools.store'), ['name' => 'Perceuse', ...$comment])
        ->assertSessionHasNoErrors();

    expect(Tool::query()->sole()->comment)->toBeNull();
})->with([
    'missing' => [[]],
    'null' => [['comment' => null]],
    'empty' => [['comment' => '']],
    'blank' => [['comment' => "  \n\t "]],
]);

it('replaces the comment of a tool, or removes it when blank', function (?string $sent, ?string $stored) {
    $user = signIn();
    $tool = Tool::factory()->for($user)->create(['name' => 'Perceuse', 'comment' => 'Ancien']);

    patch(route('garage.tools.update', $tool), ['name' => 'Perceuse', 'comment' => $sent])
        ->assertRedirect(route('garage.tools.show', $tool))
        ->assertSessionHasNoErrors();

    expect($tool->refresh()->comment)->toBe($stored);
})->with([
    'another comment' => [' Prêté à mamie ', 'Prêté à mamie'],
    'empty' => ['', null],
    'blank' => ['   ', null],
    'null' => [null, null],
]);

it('limits the comment to 2000 characters', function () {
    signIn();
    $longest = str_repeat('é', ToolRequest::COMMENT_MAX_LENGTH);

    post(route('garage.tools.store'), ['name' => 'Perceuse', 'comment' => $longest.'é'])
        ->assertSessionHasErrors(['comment' => 'Le texte de commentaire ne peut pas contenir plus de 2000 caractères.']);
    expect(Tool::query()->count())->toBe(0);

    post(route('garage.tools.store'), ['name' => 'Perceuse', 'comment' => $longest])
        ->assertSessionHasNoErrors();
    expect(Tool::query()->sole()->comment)->toBe($longest);
});

it('refuses a comment that is not a text, without changing the tool', function () {
    $user = signIn();
    $tool = Tool::factory()->for($user)->create(['comment' => 'Ancien']);

    patch(route('garage.tools.update', $tool), ['name' => 'Perceuse', 'comment' => ['a']])
        ->assertSessionHasErrors('comment');
    patch(route('garage.tools.update', $tool), ['name' => 'Perceuse', 'comment' => str_repeat('a', 2001)])
        ->assertSessionHasErrors('comment');

    expect($tool->refresh()->comment)->toBe('Ancien');
});

it('shows the comment on the page of a tool to another member, and on its edition form to the owner', function () {
    $paul = User::factory()->create(['name' => 'Paul']);
    $tool = Tool::factory()->for($paul)->create(['comment' => "Mèche de 8 mm\nusée"]);

    signIn();
    get(route('garage.tools.show', $tool))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tool.comment', "Mèche de 8 mm\nusée")
            ->where('editable', false));

    actingAs($paul);
    get(route('garage.tools.edit', $tool))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tool.comment', "Mèche de 8 mm\nusée"));
});

it('does not let another member change the comment', function (bool $admin) {
    $tool = Tool::factory()->create(['name' => 'Perceuse', 'comment' => 'Le mien']);
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    patch(route('garage.tools.update', $tool), ['name' => 'Perceuse', 'comment' => 'Piraté'])
        ->assertNotFound();

    expect($tool->refresh()->comment)->toBe('Le mien');
})->with(['member' => [false], 'admin' => [true]]);

it('leaves the comment out of the lists and of the global search', function () {
    $paul = User::factory()->create(['name' => 'Paul']);
    Tool::factory()->for($paul)->create(['name' => 'Perceuse', 'comment' => 'Zanzibar']);
    $user = signIn();
    Tool::factory()->for($user)->create(['name' => 'Scie', 'comment' => 'Zanzibar']);

    get(route('garage.tools.index'))
        ->assertInertia(fn (Assert $page) => $page->has('tools', 1)->missing('tools.0.comment'));
    get(route('family.members.show', ['member' => $paul, 'tab' => 'garage']))
        ->assertInertia(fn (Assert $page) => $page->has('tools', 1)->missing('tools.0.comment'));

    getJson(route('search', ['q' => 'Zanzibar']))->assertOk()->assertExactJson(['groups' => []]);
    getJson(route('search', ['q' => 'Perceuse']))
        ->assertOk()
        ->assertJsonPath('groups.0.results.0.label', 'Perceuse')
        ->assertJsonMissingPath('groups.0.results.0.comment')
        ->assertDontSee('Zanzibar');
});
