<?php

namespace Nadar\PdfGenerator\Exception;

class UnknownMethodException extends ConfigurationException
{
    public function __construct(string $method)
    {
        parent::__construct(sprintf('Unknown TCPDF method "%s" on proxy.', $method));
    }
}
