<?php

namespace App\Services\Garage;

use App\Exceptions\Core\UnreadableImageException;
use App\Services\Core\ResizedJpegStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Photo files of the tools, on the public disk: re-encoded by
 * ResizedJpegStorage as a JPEG at most MAX_WIDTH pixels wide, without
 * metadata, under a random name.
 */
class ToolPhotoService
{
    public const DIRECTORY = 'tools';

    public const MAX_WIDTH = 1200;

    public function __construct(private readonly ResizedJpegStorage $images) {}

    /**
     * @throws ValidationException When the file is not a readable image.
     */
    public function store(UploadedFile $file): string
    {
        try {
            return $this->images->store($file, self::DIRECTORY, self::MAX_WIDTH);
        } catch (UnreadableImageException) {
            throw ValidationException::withMessages([
                'photos' => 'Une des photos n’a pas pu être lue.',
            ]);
        }
    }

    /**
     * @param  iterable<string>  $paths
     */
    public function delete(iterable $paths): void
    {
        foreach ($paths as $path) {
            $this->images->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        return $this->images->url($path);
    }
}
