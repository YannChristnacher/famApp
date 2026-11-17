<?php

use App\Services\Library\BookCoverService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Storage::fake('public'));

it('stores a JPEG at most 800 pixels wide under a generated name', function (int $width, int $height, array $expected) {
    $path = app(BookCoverService::class)->store(UploadedFile::fake()->image('Ma Photo.jpg', $width, $height));

    expect($path)->toMatch('#^covers/[A-Za-z0-9]{40}\.jpg$#')
        ->and(storedImage($path))->toBe([...$expected, 'mime' => 'image/jpeg']);
})->with([
    'portrait photo' => [3024, 4032, [800, 1067]],
    'landscape photo' => [4000, 2000, [800, 400]],
    'small image, not enlarged' => [400, 600, [400, 600]],
    'exactly 800 wide' => [800, 1000, [800, 1000]],
]);

it('converts a WebP image to JPEG', function () {
    $path = app(BookCoverService::class)->store(UploadedFile::fake()->image('cover.webp', 1200, 1800));

    expect(storedImage($path))->toBe([800, 1200, 'mime' => 'image/jpeg']);
});

it('puts a transparent PNG on a white background', function () {
    $path = app(BookCoverService::class)->store(transparentPng(1000, 1000));

    $image = imagecreatefromstring((string) Storage::disk('public')->get($path));
    expect($image)->not->toBeFalse()
        ->and(storedImage($path))->toBe([800, 800, 'mime' => 'image/jpeg']);

    /** @var GdImage $image */
    $rgb = imagecolorat($image, 400, 400);
    expect([($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF])->each->toBeGreaterThan(250);
});

it('reports a file that is not a readable image as a validation error on the cover', function () {
    $file = UploadedFile::fake()->createWithContent('cover.jpg', 'not an image');

    expect(fn () => app(BookCoverService::class)->store($file))
        ->toThrow(ValidationException::class, 'L’image de couverture n’a pas pu être lue.');
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('deletes a stored cover and ignores a missing one', function () {
    Storage::disk('public')->put('covers/old.jpg', 'cover');
    $covers = app(BookCoverService::class);

    $covers->delete('covers/old.jpg');
    $covers->delete(null);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('turns a sideways phone photo upright from its EXIF orientation', function (int $orientation, array $expected) {
    $photo = UploadedFile::fake()->image('photo.jpg', 1600, 800);
    $jpeg = (string) file_get_contents($photo->getRealPath());
    // APP1 segment with a big-endian TIFF header and a single Orientation (0x0112) entry.
    $tiff = "MM\x00\x2A\x00\x00\x00\x08"."\x00\x01"."\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00"."\x00\x00\x00\x00";
    $exif = "Exif\x00\x00".$tiff;
    $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

    $path = app(BookCoverService::class)->store(UploadedFile::fake()->createWithContent('photo.jpg', $withExif));

    expect(storedImage($path))->toBe([...$expected, 'mime' => 'image/jpeg']);
})->with([
    'rotated 90° clockwise' => [6, [400, 800]],
    'rotated 90° counter-clockwise' => [8, [400, 800]],
    'upside down' => [3, [800, 400]],
    'upright' => [1, [800, 400]],
]);
