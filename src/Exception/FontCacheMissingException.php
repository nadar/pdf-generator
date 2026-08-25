<?php

namespace Nadar\PdfGenerator\Exception;

/**
 * A declared face has no compiled TCPDF definition.
 *
 * The message names the face, the exact file that was looked for, and the
 * command that produces it. The cache must exist before the first render -
 * commit it, or build it in CI.
 */
class FontCacheMissingException extends FontException
{
}
