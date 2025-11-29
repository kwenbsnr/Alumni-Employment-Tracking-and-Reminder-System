<?php
function log_alumni_activity($conn, $user_id, $action_type, $description) {
    $stmt = $conn->prepare("
        INSERT INTO alumni_activity_log (user_id, action_type, description) 
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iss", $user_id, $action_type, $description);
    return $stmt->execute();
}

// Common activity types
define('ACTIVITY_LOGIN', 'login');
define('ACTIVITY_LOGOUT', 'logout');
define('ACTIVITY_PROFILE_UPDATED', 'profile_updated');
define('ACTIVITY_PROFILE_SUBMITTED', 'profile_submitted');
define('ACTIVITY_PROFILE_APPROVED', 'profile_approved');
define('ACTIVITY_PROFILE_REJECTED', 'profile_rejected');
define('ACTIVITY_PHOTO_UPDATED', 'profile_photo_updated');
define('ACTIVITY_DOCUMENT_UPLOADED', 'document_uploaded');
define('ACTIVITY_DOCUMENT_DELETED', 'document_deleted');
define('ACTIVITY_EMPLOYMENT_UPDATED', 'employment_updated');
define('ACTIVITY_EDUCATION_UPDATED', 'education_updated');