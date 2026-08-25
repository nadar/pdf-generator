<?php

namespace Nadar\PdfGenerator\Exception;

/**
 * A template's page size differs from what was asserted.
 *
 * Worth asserting in print work: a re-export at the wrong page size shifts
 * every measured coordinate, and nothing else notices.
 */
class TemplateSizeMismatchException extends TemplateException
{
}
