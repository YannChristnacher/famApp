<?php

namespace Database\Seeders\Demo;

use App\Services\Garage\ToolPhotoService;
use App\Services\Library\BookCoverService;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pictures of the demo data set, drawn locally with GD (a coloured plate and
 * the text, no download), then stored by the image pipeline of the
 * application like an upload: re-encoded JPEG under a random name on the
 * public disk.
 */
class DemoImageGenerator
{
    private const COVER_SIZE = [400, 600];

    private const PHOTO_SIZE = [800, 600];

    private const MIN_SIDE = 16;

    /** Background colours (RGB), picked from the text so they stay stable. */
    private const PALETTE = [
        [37, 99, 235], [5, 150, 105], [220, 38, 38], [217, 119, 6], [124, 58, 237],
        [8, 145, 178], [190, 24, 93], [71, 85, 105], [101, 163, 13], [234, 88, 12],
    ];

    /**
     * @param  float  $scale  Size factor of the pictures (small in the tests).
     */
    public function __construct(
        private readonly BookCoverService $covers,
        private readonly ToolPhotoService $photos,
        private readonly float $scale = 1.0,
    ) {}

    public function cover(string $title, string $author): string
    {
        $image = $this->plate(self::COVER_SIZE, $title);
        $this->write($image, $title, 5, 0.12);
        $this->write($image, $author, 3, 0.80);

        return $this->stored($image, $this->covers->store(...));
    }

    public function toolPhoto(string $name, ?string $brand, int $number): string
    {
        $image = $this->plate(self::PHOTO_SIZE, $name.$number);
        $this->write($image, $name, 5, 0.35);
        $this->write($image, trim(($brand ?? '').' - photo '.$number, ' -'), 3, 0.65);

        return $this->stored($image, $this->photos->store(...));
    }

    /**
     * @param  array{int, int}  $size
     */
    private function plate(array $size, string $seed): GdImage
    {
        $width = max(self::MIN_SIDE, (int) round($size[0] * $this->scale));
        $height = max(self::MIN_SIDE, (int) round($size[1] * $this->scale));
        $image = imagecreatetruecolor($width, $height);
        [$red, $green, $blue] = self::PALETTE[crc32($seed) % count(self::PALETTE)];

        imagefill($image, 0, 0, (int) imagecolorallocate($image, $red, $green, $blue));
        imagefilledrectangle(
            $image, 0, (int) ($height * 0.9), $width, $height,
            (int) imagecolorallocate($image, intdiv($red, 2), intdiv($green, 2), intdiv($blue, 2)),
        );

        return $image;
    }

    /**
     * Centred lines of text from the given height ratio. The built-in GD
     * fonts have no accents: the text is transliterated to ASCII.
     *
     * @param  int<1, 5>  $font
     */
    private function write(GdImage $image, string $text, int $font, float $top): void
    {
        $width = imagesx($image);
        $charWidth = imagefontwidth($font);
        $lineHeight = imagefontheight($font) + 4;
        $charsPerLine = max(1, intdiv($width - 2 * $charWidth, $charWidth));
        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $y = (int) (imagesy($image) * $top);

        foreach (explode("\n", wordwrap(Str::ascii($text), $charsPerLine, "\n", true)) as $line) {
            imagestring($image, $font, max(0, intdiv($width - strlen($line) * $charWidth, 2)), $y, $line, $white);
            $y += $lineHeight;
        }
    }

    /**
     * Hands the picture to the pipeline as an uploaded PNG file.
     *
     * @param  callable(UploadedFile): string  $store
     */
    private function stored(GdImage $image, callable $store): string
    {
        $path = tempnam(sys_get_temp_dir(), 'famapp-demo-');
        if ($path === false || ! imagepng($image, $path)) {
            throw new RuntimeException('Impossible de générer une image de démonstration.');
        }

        try {
            return $store(new UploadedFile($path, 'demo.png', 'image/png', null, true));
        } finally {
            @unlink($path);
        }
    }
}
