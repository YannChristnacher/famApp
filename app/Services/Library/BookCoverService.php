<?php

namespace App\Services\Library;

use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cover images of the books, on the public disk. An upload is re-encoded with
 * GD as a JPEG at most MAX_WIDTH pixels wide (phone photos weigh several
 * megabytes), on a white background, without its metadata (EXIF, location).
 */
class BookCoverService
{
    public const DISK = 'public';

    public const DIRECTORY = 'covers';

    public const MAX_WIDTH = 800;

    private const JPEG_QUALITY = 85;

    /**
     * Stores the resized cover and returns its path on the public disk.
     */
    public function store(UploadedFile $file): string
    {
        $angle = $this->uprightAngle($file);

        // Resized before being rotated: a rotation copies the image, and
        // copying a full-size photo would double the memory used.
        $cover = $this->rotate($this->resize($this->read($file), $this->isQuarterTurn($angle)), $angle);

        $path = self::DIRECTORY.'/'.Str::random(40).'.jpg';
        $this->disk()->put($path, $this->encodeJpeg($cover));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            $this->disk()->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        return $path === null ? null : $this->disk()->url($path);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    private function read(UploadedFile $file): GdImage
    {
        $path = $file->getRealPath();
        $image = match ($file->getMimeType()) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([
                'cover' => 'L’image de couverture n’a pas pu être lue.',
            ]);
        }

        return $image;
    }

    /**
     * Angle (counter-clockwise, as imagerotate() expects) that turns upright a
     * JPEG stored sideways with an EXIF orientation tag, as phones do.
     */
    private function uprightAngle(UploadedFile $file): int
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return 0;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return match ($orientation) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };
    }

    private function isQuarterTurn(int $angle): bool
    {
        return $angle === 90 || $angle === 270;
    }

    private function rotate(GdImage $image, int $angle): GdImage
    {
        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * Never enlarges; always draws on a white canvas (transparent PNG or WebP).
     * Before a quarter turn, the height of the source becomes the width of the
     * cover: it is the side bounded by MAX_WIDTH.
     */
    private function resize(GdImage $source, bool $beforeQuarterTurn): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $finalWidth = $beforeQuarterTurn ? $height : $width;
        $scale = min(1, self::MAX_WIDTH / $finalWidth);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    private function encodeJpeg(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);

        return (string) ob_get_clean();
    }
}
