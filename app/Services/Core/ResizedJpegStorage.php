<?php

namespace App\Services\Core;

use App\Exceptions\Core\UnreadableImageException;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uploaded images (book covers, tool photos) on the public disk. An upload is
 * re-encoded with GD as a JPEG at most $maxWidth pixels wide once upright
 * (phone photos weigh several megabytes), on a white background, without its
 * metadata (EXIF, location), under a random name. The dimensions must have
 * been bounded by the validation before (App\Support\ImageUploadRules): GD
 * decodes the whole image in memory.
 */
class ResizedJpegStorage
{
    public const DISK = 'public';

    private const JPEG_QUALITY = 85;

    /**
     * Stores the resized image in the directory and returns its path.
     *
     * @throws UnreadableImageException
     */
    public function store(UploadedFile $file, string $directory, int $maxWidth): string
    {
        $angle = $this->uprightAngle($file);

        // Resized before being rotated: a rotation copies the image, and
        // copying a full-size photo would double the memory used.
        $image = $this->rotate($this->resize($this->read($file), $maxWidth, $this->isQuarterTurn($angle)), $angle);

        $path = $directory.'/'.Str::random(40).'.jpg';
        $this->disk()->put($path, $this->encodeJpeg($image));

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
            throw new UnreadableImageException('The uploaded file is not a readable image.');
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
     * image: it is the side bounded by $maxWidth.
     */
    private function resize(GdImage $source, int $maxWidth, bool $beforeQuarterTurn): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $finalWidth = $beforeQuarterTurn ? $height : $width;
        $scale = min(1, $maxWidth / $finalWidth);
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
