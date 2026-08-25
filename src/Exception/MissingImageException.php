<?php

namespace Nadar\PdfGenerator\Exception;

/**
 * An image source could not be read.
 *
 * Thrown when a local file is missing/unreadable or a remote fetch fails, and
 * no placeholder was configured. Pass `placeholder:` on the
 * {@see \Nadar\PdfGenerator\ImageBox} or an `$onMissing` callback to
 * {@see \Nadar\PdfGenerator\PdfGenerator::image()} to degrade gracefully - CDN
 * images do fail in production.
 */
final class MissingImageException extends ImageException
{
}
