<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Headers of an Inertia visit made by a page built with the current assets.
 *
 * @return array<string, string>
 */
function inertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ];
}

/**
 * Names of the bookshelves of a user, in their order (positions must be 1..n).
 *
 * @return list<string>
 */
function bookshelfNames(User $user): array
{
    $bookshelves = Bookshelf::query()
        ->whereBelongsTo($user)
        ->orderBy('position')
        ->get();

    expect($bookshelves->pluck('position')->all())->toBe($bookshelves->isEmpty() ? [] : range(1, $bookshelves->count()));

    return $bookshelves->pluck('name')->values()->all();
}

/**
 * Creates a member and signs them in.
 */
function signIn(): User
{
    $user = User::factory()->create();
    actingAs($user);

    return $user;
}

/**
 * A transparent PNG made with GD (UploadedFile::fake()->image() is opaque).
 */
function transparentPng(int $width, int $height): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));

    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();

    return UploadedFile::fake()->createWithContent('cover.png', $png);
}

/**
 * Width, height and MIME type of an image stored on the public disk.
 *
 * @return array{0: int, 1: int, mime: string}
 */
function storedImage(string $path): array
{
    $size = getimagesizefromstring((string) Storage::disk('public')->get($path));
    if ($size === false) {
        throw new RuntimeException("{$path} is not an image.");
    }

    return [$size[0], $size[1], 'mime' => $size['mime']];
}

/**
 * A volume as returned by the Google Books API (search item or volume).
 *
 * @param  array<string, mixed>  $volumeInfo  Merged into the default volumeInfo.
 * @return array<string, mixed>
 */
function googleVolume(string $id = 'zyTCAlFPjgYC', array $volumeInfo = []): array
{
    return [
        'kind' => 'books#volume',
        'id' => $id,
        'etag' => 'f0zKg75Mx/I',
        'selfLink' => "https://www.googleapis.com/books/v1/volumes/{$id}",
        'volumeInfo' => array_merge([
            'title' => 'Le Hobbit',
            'authors' => ['J. R. R. Tolkien'],
            'publisher' => 'Gallimard',
            'publishedDate' => '2012',
            'industryIdentifiers' => [
                ['type' => 'ISBN_13', 'identifier' => '9782070368228'],
                ['type' => 'ISBN_10', 'identifier' => '207036822X'],
            ],
            'imageLinks' => [
                'smallThumbnail' => "http://books.google.com/books/content?id={$id}&printsec=frontcover&img=1&zoom=5&source=gbs_api",
                'thumbnail' => "http://books.google.com/books/content?id={$id}&printsec=frontcover&img=1&zoom=1&source=gbs_api",
            ],
            'language' => 'fr',
        ], $volumeInfo),
    ];
}

/**
 * A search answer of the Google Books API holding the given volumes.
 *
 * @param  list<array<string, mixed>>  $volumes
 * @return array<string, mixed>
 */
function googleSearch(array $volumes): array
{
    return $volumes === []
        ? ['kind' => 'books#volumes', 'totalItems' => 0]
        : ['kind' => 'books#volumes', 'totalItems' => count($volumes), 'items' => $volumes];
}

/**
 * Bytes of a JPEG image made with GD.
 */
function jpegBytes(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 40, 40));

    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}
