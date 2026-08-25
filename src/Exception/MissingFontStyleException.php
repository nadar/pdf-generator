<?php

namespace Nadar\PdfGenerator\Exception;

/**
 * A required bold/italic (or custom weight) face is not registered.
 *
 * Raised at configuration time by `FontSet::role()`, and at write time when
 * HTML markup needs a face that does not exist. Never silently substituted:
 * TCPDF's synthetic bold/italic is a no-op for embedded subset fonts, so the
 * output would look correct in code and wrong in print.
 */
class MissingFontStyleException extends FontException
{
}
