<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
query('DELETE FROM password_resets WHERE expires_at<UTC_TIMESTAMP()');
query('DELETE FROM email_verifications WHERE expires_at<UTC_TIMESTAMP()');
query('DELETE FROM login_attempts WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)');
query('DELETE FROM analytics_events WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 90 DAY)');
echo "Expired tokens and time-limited activity records purged. Financial and audit records retained.\n";
