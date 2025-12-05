<?php

function isSubmissionPeriodOpen($conn) {
    $statusCheck = $conn->query("SELECT is_open FROM submission_status LIMIT 1");
    if ($statusCheck->num_rows > 0) {
        $status = $statusCheck->fetch_assoc();
        return (bool)$status['is_open'];
    }
    return false; // Default to closed if no status found
}