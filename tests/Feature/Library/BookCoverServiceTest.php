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

/**
 * A JPEG photo with an EXIF block (big-endian TIFF): the Orientation tag and a
 * GPS sub-directory holding GPSLatitudeRef, as phones write them.
 */
function photoWithExif(int $width, int $height, int $orientation): UploadedFile
{
    // Kept in a variable: the temporary file disappears with the fake upload.
    $source = UploadedFile::fake()->image('photo.jpg', $width, $height);
    $jpeg = (string) file_get_contents($source->getRealPath());
    $gpsOffset = 8 + 2 + 2 * 12 + 4;
    $ifd0 = pack('n', 2)
        .pack('nnN', 0x0112, 3, 1).pack('n', $orientation)."\x00\x00"
        .pack('nnNN', 0x8825, 4, 1, $gpsOffset)
        .pack('N', 0);
    $gps = pack('n', 1).pack('nnN', 0x0001, 2, 2)."N\x00\x00\x00".pack('N', 0);
    $exif = "Exif\x00\x00"."MM\x00\x2A".pack('N', 8).$ifd0.$gps;

    return UploadedFile::fake()->createWithContent(
        'photo.jpg',
        substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2),
    );
}

it('turns a sideways phone photo upright from its EXIF orientation, 800 pixels wide once upright', function (int $width, int $height, int $orientation, array $expected) {
    $path = app(BookCoverService::class)->store(photoWithExif($width, $height, $orientation));

    expect(storedImage($path))->toBe([...$expected, 'mime' => 'image/jpeg']);
})->with([
    'portrait stored sideways, rotated 90° clockwise' => [4032, 3024, 6, [800, 1067]],
    'portrait stored sideways, rotated 90° counter-clockwise' => [4032, 3024, 8, [800, 1067]],
    'small sideways photo, not enlarged' => [600, 400, 6, [400, 600]],
    'upside down' => [1600, 800, 3, [800, 400]],
    'upright' => [1600, 800, 1, [800, 400]],
]);

it('removes the EXIF metadata, location included, from the stored cover', function () {
    $photo = photoWithExif(1200, 900, 6);
    $source = exif_read_data($photo->getRealPath());
    expect($source)->toHaveKeys(['Orientation', 'GPSLatitudeRef']);

    $path = app(BookCoverService::class)->store($photo);

    $stored = @exif_read_data(Storage::disk('public')->path($path));
    expect($stored === false ? [] : $stored)->not->toHaveKeys(['Orientation', 'GPSLatitudeRef', 'GPSVersion'])
        ->and((string) Storage::disk('public')->get($path))->not->toContain('Exif');
});
