<?php

use App\Services\Garage\ToolPhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Storage::fake('public'));

it('stores a JPEG at most 1200 pixels wide under a generated name', function (int $width, int $height, array $expected) {
    $path = app(ToolPhotoService::class)->store(UploadedFile::fake()->image('Ma perceuse.png', $width, $height));

    expect($path)->toMatch('#^tools/[A-Za-z0-9]{40}\.jpg$#')
        ->and(storedImage($path))->toBe([...$expected, 'mime' => 'image/jpeg']);
})->with([
    'landscape photo' => [4000, 3000, [1200, 900]],
    'small image, not enlarged' => [500, 300, [500, 300]],
]);

it('turns a sideways photo upright and removes its EXIF metadata', function () {
    $path = app(ToolPhotoService::class)->store(photoWithExif(4032, 3024, 6));

    expect(storedImage($path))->toBe([1200, 1600, 'mime' => 'image/jpeg']);
    $stored = @exif_read_data(Storage::disk('public')->path($path));
    expect($stored === false ? [] : $stored)->not->toHaveKeys(['Orientation', 'GPSLatitudeRef'])
        ->and((string) Storage::disk('public')->get($path))->not->toContain('Exif');
});

it('reports an unreadable image as a validation error on the photos', function () {
    expect(fn () => app(ToolPhotoService::class)->store(UploadedFile::fake()->createWithContent('photo.jpg', 'nope')))
        ->toThrow(ValidationException::class, 'Une des photos n’a pas pu être lue.');
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('deletes stored photos and gives their public URL', function () {
    Storage::disk('public')->put('tools/a.jpg', 'a');
    $photos = app(ToolPhotoService::class);

    expect($photos->url('tools/a.jpg'))->toBe(Storage::disk('public')->url('tools/a.jpg'));
    $photos->delete(['tools/a.jpg']);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});
