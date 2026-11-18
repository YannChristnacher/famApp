<?php

namespace App\Exceptions\Core;

use RuntimeException;

/**
 * An uploaded file that GD cannot decode as a JPEG, PNG or WebP image. Each
 * module turns it into a French validation error on its own field.
 */
class UnreadableImageException extends RuntimeException {}
