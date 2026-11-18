<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;

/**
 * Validation of an uploaded image (book cover, tool photo), before
 * ResizedJpegStorage decodes it: type read from the content (JPEG, PNG or
 * WebP; neither GIF nor SVG), weight, and dimensions, which bound the memory
 * GD needs to decode it.
 */
final class ImageUploadRules
{
    public const MAX_KILOBYTES = 10 * 1024;

    /** Beyond this size, decoding the image would take too much memory. */
    public const MAX_PIXELS_PER_SIDE = 6000;

    /**
     * @return array{0: File, 1: Dimensions}
     */
    public static function rules(): array
    {
        return [
            File::image()->types(['jpeg', 'png', 'webp'])->max(self::MAX_KILOBYTES),
            Rule::dimensions()->maxWidth(self::MAX_PIXELS_PER_SIDE)->maxHeight(self::MAX_PIXELS_PER_SIDE),
        ];
    }
}
