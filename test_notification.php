<?php
require_once 'connect.php';
require_once 'api/utils/deadline.php';
require_once 'api/notification/notif_service.php';

// Test sending to a specific alumni
$testAlumni = [
    'user_id' => 1,
    'email' => 'josieoliveros013@gmail.com',
    'first_name' => 'Josie',
    'last_name' => 'Oliveros',
    'batch_year' => 2020
];

// Call your notification function
sendDeadlineReminder($testAlumni, $conn);
echo "Test notification sent!";