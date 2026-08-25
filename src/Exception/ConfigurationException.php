<?php

namespace Nadar\PdfGenerator\Exception;

use RuntimeException;

/**
 * Something about the document's configuration is wrong: a bad path, an
 * unusable value, an impossible layout.
 *
 * The base class for most of this package's failures.
 */
class ConfigurationException extends RuntimeException implements PdfGeneratorException
{
}
