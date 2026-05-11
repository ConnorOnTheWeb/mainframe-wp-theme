<?php
/**
 * Mainframe Theme — Archive Template
 *
 * Archive pages (category, tag, date, author, custom taxonomy, etc.) have no
 * "show content" toggle in this theme — they always redirect to the home page.
 * Under normal operation the redirect fires earlier in template_redirect via
 * inc/redirects.php. This file exists as an explicit safety net so the
 * redirect is enforced even if template_redirect is bypassed.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

mainframe_redirect_home( mainframe_get_redirect_code() );
