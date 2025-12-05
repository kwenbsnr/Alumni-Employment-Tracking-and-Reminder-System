<?php

function isSubmissionsOpen($conn) {
    require_once __DIR__ . '/schedule_checker.php';
    return isSubmissionPeriodOpen($conn);
}