<?php

function isSubmissionsOpen($conn) {
    // schedule_checker.php is included
    require_once __DIR__ . '/schedule_checker.php';
    return isSubmissionPeriodOpen($conn);
}