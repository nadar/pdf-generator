# nadar/pdf-generator

Pixel-perfect template-overlay PDF generation on top of TCPDF and FPDI.

## License notes

This package is MIT licensed. It depends on `tecnickcom/tcpdf` which is LGPL-3.0-or-later, so consumers must comply with TCPDF's LGPL obligations.

## Security defaults

`src/bootstrap.php` sets `K_TCPDF_CALLS_IN_HTML=false` and `K_TCPDF_THROW_EXCEPTION_ERROR=true` by default. This secures HTML rendering and changes TCPDF global error behavior from `die()` to exceptions.
