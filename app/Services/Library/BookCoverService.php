<?php

namespace App\Services\Library;

use App\Exceptions\Core\UnreadableImageException;
use App\Services\Core\ResizedJpegStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Cover images of the books, on the public disk: re-encoded by
 * ResizedJpegStorage as a JPEG at most MAX_WIDTH pixels wide, on a white
 * background, without its metadata (EXIF, location).
 */
class BookCoverService
{
    public const DISK = ResizedJpegStorage::DISK;

    public const DIRECTORY = 'covers';

    public const MAX_WIDTH = 800;

    public function __construct(private readonly ResizedJpegStorage $images) {}

    /**
     * Stores the resized cover and returns its path on the public disk.
     *
     * @throws ValidationException When the file is not a readable image.
     */
    public function store(UploadedFile $file): string
    {
        try {
            return $this->images->store($file, self::DIRECTORY, self::MAX_WIDTH);
        } catch (UnreadableImageException) {
            throw ValidationException::withMessages([
                'cover' => 'L’image de couverture n’a pas pu être lue.',
            ]);
        }
    }

    public function delete(?string $path): void
    {
        $this->images->delete($path);
    }

    public function url(?string $path): ?string
    {
        return $this->images->url($path);
    }
}
