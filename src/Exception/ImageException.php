<?php

namespace Nadar\PdfGenerator\Exception;

use RuntimeException;

/**
 * Base class for image placement failures.
 *
 * A runtime rather than a configuration problem: the layout can be perfectly
 * valid and the CDN still down.
 */
class ImageException extends RuntimeException implements PdfGeneratorException
{
}
