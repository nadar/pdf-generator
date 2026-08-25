<?php

namespace Nadar\PdfGenerator\Exception;

/**
 * The template file does not exist under `templatePath()`.
 *
 * `page()` and `stamp()` take a basename relative to that directory, not a
 * full path.
 */
class TemplateNotFoundException extends TemplateException
{
}
