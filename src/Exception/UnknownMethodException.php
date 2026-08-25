<?php

namespace Nadar\PdfGenerator\Exception;

/** A method proxied to TCPDF does not exist on the document class. */
class UnknownMethodException extends ConfigurationException
{
    public function __construct(string $method)
    {
        parent::__construct(sprintf('Unknown TCPDF method "%s" on proxy.', $method));
    }
}
