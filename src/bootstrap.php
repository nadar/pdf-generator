<?php

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
