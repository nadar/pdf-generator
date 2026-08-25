<?php

namespace Nadar\PdfGenerator;

/**
 * Linear (1D) barcode symbologies.
 *
 * The backing values are the type codes TCPDF's `write1DBarcode()` expects.
 */
enum Barcode1D: string
{
    /** Code 128 with automatic subset switching. The general-purpose default. */
    case Code128 = 'C128';
    case Code128A = 'C128A';
    case Code128B = 'C128B';
    case Code128C = 'C128C';
    case Code39 = 'C39';
    case Code93 = 'C93';
    case Interleaved25 = 'I25';
    case Ean8 = 'EAN8';
    case Ean13 = 'EAN13';
    case UpcA = 'UPCA';
    case UpcE = 'UPCE';
    /** Swiss/German postal interleaved 2 of 5. */
    case Postnet = 'POSTNET';
}
