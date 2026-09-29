<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/integrations.php';

$sent    = integ_send_reminders();
$updated = Brevo::refreshStatuses();
echo date('c') . " reminders sent: $sent, delivery statuses updated: $updated\n";
