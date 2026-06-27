<?php
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
define('COP_REST_API_SERVER_URL', 'https://sardabir.online/wp-json/license/v1/validate/');





require_once __DIR__ . '/functions/general.php';
require_once __DIR__ . '/functions/metabox.php';
require_once __DIR__ . '/functions/post_schedule.php';
require_once __DIR__ . '/functions/feeds.php';
require_once __DIR__ . '/functions/license.php';

require_once __DIR__ . '/functions/crons.php';
require_once __DIR__ . '/functions/scraper.php';
require_once __DIR__ . '/functions/report.php';
require_once __DIR__ . '/functions/template.php';
require_once __DIR__ . '/functions/monitoring.php';
require_once __DIR__ . '/functions/dashboard_widgets.php';
