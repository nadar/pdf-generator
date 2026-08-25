<?php

namespace Nadar\PdfGenerator;

/**
 * QR code error-correction level.
 *
 * Higher levels survive more damage but pack more modules into the same box,
 * which matters at print size: for a full URL in a ~20 mm square, `M` stays
 * comfortably scannable while `H` visibly thickens the module grid.
 */
enum EccLevel: string
{
    /** ~7% recovery. Loosest grid, use when the code is small and unobstructed. */
    case L = 'L';

    /** ~15% recovery. The sensible default for print. */
    case M = 'M';

    /** ~25% recovery. */
    case Q = 'Q';

    /** ~30% recovery. Needed when a logo overlaps the code. */
    case H = 'H';
}
