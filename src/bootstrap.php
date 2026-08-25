<?php

/*
 * TCPDF configuration, applied before TCPDF's own config file can define these.
 *
 * Every constant is guarded, so an application that needs different values can
 * define them earlier (e.g. from a service provider that loads before
 * Composer's autoload files, or in a php.ini prepend).
 *
 * Two are worth knowing about:
 *
 * - K_TCPDF_CALLS_IN_HTML = false blocks TCPDF method calls embedded in HTML
 *   input, which would otherwise be a code-execution path for user content.
 * - K_TCPDF_THROW_EXCEPTION_ERROR = true turns TCPDF's internal die() into
 *   exceptions, so failures are catchable instead of killing the process.
 *
 * K_PATH_CACHE defaults to the system temp directory. On serverless targets
 * (AWS Lambda, Vercel, ...) only /tmp is writable, which this satisfies; but the
 * directory is per-instance and ephemeral, so never keep compiled font
 * definitions there - those belong in fontCachePath(), committed or built in CI.
 */

defined('K_TCPDF_EXTERNAL_CONFIG') || define('K_TCPDF_EXTERNAL_CONFIG', true);
defined('K_TCPDF_CALLS_IN_HTML') || define('K_TCPDF_CALLS_IN_HTML', false);
defined('K_TCPDF_THROW_EXCEPTION_ERROR') || define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
defined('K_PATH_CACHE') || define('K_PATH_CACHE', sys_get_temp_dir() . '/');
defined('PDF_UNIT') || define('PDF_UNIT', 'mm');
defined('PDF_PAGE_ORIENTATION') || define('PDF_PAGE_ORIENTATION', 'P');
defined('PDF_PAGE_FORMAT') || define('PDF_PAGE_FORMAT', 'A4');
defined('PDF_CREATOR') || define('PDF_CREATOR', 'TCPDF');
defined('PDF_AUTHOR') || define('PDF_AUTHOR', '');
defined('PDF_HEADER_TITLE') || define('PDF_HEADER_TITLE', '');
defined('PDF_HEADER_STRING') || define('PDF_HEADER_STRING', '');
defined('PDF_FONT_NAME_MAIN') || define('PDF_FONT_NAME_MAIN', 'helvetica');
defined('PDF_FONT_SIZE_MAIN') || define('PDF_FONT_SIZE_MAIN', 10);
defined('PDF_FONT_NAME_DATA') || define('PDF_FONT_NAME_DATA', 'helvetica');
defined('PDF_FONT_SIZE_DATA') || define('PDF_FONT_SIZE_DATA', 8);
defined('PDF_FONT_MONOSPACED') || define('PDF_FONT_MONOSPACED', 'courier');
defined('PDF_MARGIN_LEFT') || define('PDF_MARGIN_LEFT', 15);
defined('PDF_MARGIN_TOP') || define('PDF_MARGIN_TOP', 27);
defined('PDF_MARGIN_RIGHT') || define('PDF_MARGIN_RIGHT', 15);
defined('PDF_MARGIN_HEADER') || define('PDF_MARGIN_HEADER', 5);
defined('PDF_MARGIN_FOOTER') || define('PDF_MARGIN_FOOTER', 10);
defined('PDF_MARGIN_BOTTOM') || define('PDF_MARGIN_BOTTOM', 25);
defined('PDF_IMAGE_SCALE_RATIO') || define('PDF_IMAGE_SCALE_RATIO', 1.25);

/*
 * QR code definitions.
 *
 * TCPDF guards this whole block behind QRCODEDEFS so consumers can supply their
 * own values, which is the only way to reach QR_FIND_FROM_RANDOM: defining that
 * constant alone still lets TCPDF's block run, which emits a redefinition
 * warning on the first QR render.
 *
 * The one value that differs from TCPDF's default is QR_FIND_FROM_RANDOM. At
 * TCPDF's default of 2, QRcode::mask() discards six of the eight mask
 * candidates using rand(), so the same payload produces a different pattern on
 * every render. That silently defeats deterministic() -- and with it golden-file
 * tests and ETags -- for any document containing a code.
 *
 * Setting it to false scores all eight masks. Output becomes reproducible, and
 * the chosen mask is the genuinely best one rather than the best of two random
 * candidates, at the cost of four times the mask evaluation per code.
 *
 * Everything else below is TCPDF's own value. The remaining constants are
 * fixed by ISO/IEC 18004 (encoding modes, error-correction levels, version and
 * width maxima, capacity indices, and the N1-N4 mask demerit weights).
 * QrCodeDefinitionsTest asserts this list still matches TCPDF's, so a future
 * TCPDF adding a constant here fails the test suite instead of the renderer.
 */
defined('QR_FIND_FROM_RANDOM') || define('QR_FIND_FROM_RANDOM', false);
defined('QR_FIND_BEST_MASK') || define('QR_FIND_BEST_MASK', true);
defined('QR_DEFAULT_MASK') || define('QR_DEFAULT_MASK', 2);
defined('QR_MODE_NL') || define('QR_MODE_NL', -1);
defined('QR_MODE_NM') || define('QR_MODE_NM', 0);
defined('QR_MODE_AN') || define('QR_MODE_AN', 1);
defined('QR_MODE_8B') || define('QR_MODE_8B', 2);
defined('QR_MODE_KJ') || define('QR_MODE_KJ', 3);
defined('QR_MODE_ST') || define('QR_MODE_ST', 4);
defined('QR_ECLEVEL_L') || define('QR_ECLEVEL_L', 0);
defined('QR_ECLEVEL_M') || define('QR_ECLEVEL_M', 1);
defined('QR_ECLEVEL_Q') || define('QR_ECLEVEL_Q', 2);
defined('QR_ECLEVEL_H') || define('QR_ECLEVEL_H', 3);
defined('QRSPEC_VERSION_MAX') || define('QRSPEC_VERSION_MAX', 40);
defined('QRSPEC_WIDTH_MAX') || define('QRSPEC_WIDTH_MAX', 177);
defined('QRCAP_WIDTH') || define('QRCAP_WIDTH', 0);
defined('QRCAP_WORDS') || define('QRCAP_WORDS', 1);
defined('QRCAP_REMINDER') || define('QRCAP_REMINDER', 2);
defined('QRCAP_EC') || define('QRCAP_EC', 3);
defined('STRUCTURE_HEADER_BITS') || define('STRUCTURE_HEADER_BITS', 20);
defined('MAX_STRUCTURED_SYMBOLS') || define('MAX_STRUCTURED_SYMBOLS', 16);
defined('N1') || define('N1', 3);
defined('N2') || define('N2', 3);
defined('N3') || define('N3', 40);
defined('N4') || define('N4', 10);

// Defined last: it is what tells TCPDF the block above is already in place.
defined('QRCODEDEFS') || define('QRCODEDEFS', true);
