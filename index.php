<?php
/**
 * Mainframe Theme — Index Fallback
 *
 * WordPress requires index.php to exist in every theme. Under normal
 * operation this file is never reached — template_redirect in
 * inc/redirects.php handles all public routes before any template is loaded,
 * and dedicated templates (front-page.php, singular.php, archive.php, 404.php)
 * cover every other case.
 *
 * If this file is loaded for any reason, redirect to the home page silently.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

mainframe_redirect_home( mainframe_get_redirect_code() );
