<?php

// Include deadline.php which contains isSubmissionPeriodOpen()
require_once __DIR__ . '/deadline.php';

function isSubmissionsOpen($conn) {
    return isSubmissionPeriodOpen($conn);
}