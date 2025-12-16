<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");

// Load notification service
require_once dirname(__DIR__) . '/api/notification/notif_service.php';

function syncAlumniStatus($conn, $user_id) {
    require_once '../api/utils/submission_status.php';
    
    $new_status = getSubmissionStatus($conn, $user_id);
    
    error_log("Status sync for user $user_id: $new_status");
    
    return $new_status;
}

function shouldSendNotification($conn) {
    if (!function_exists('isSubmissionPeriodOpen')) {
        require_once dirname(__DIR__) . '/api/utils/deadline.php';
    }
    return isSubmissionPeriodOpen($conn);
}

// Get referrer to determine which page the action came from
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$came_from_batch = strpos($referrer, 'batch_alumni.php') !== false;
$came_from_all = strpos($referrer, 'all_alumni.php') !== false;

// Handle bulk rejection from unified modal
if (isset($_GET['type']) && $_GET['type'] === 'document_bulk_reject' && isset($_GET['rejections'])) {
    $user_id = intval($_GET['user_id']);
    $admin_id = $_SESSION["user_id"];
    
    // Decode the rejections parameter
    $rejections = $_GET['rejections'];
    if (is_string($rejections)) {
        $rejections = json_decode(urldecode($rejections), true);
    }
    
    // Ensure it's an array
    if (!is_array($rejections)) {
        $rejections = [];
    }
    
    if (empty($rejections)) {
        $_SESSION['message'] = "No documents selected for rejection";
        $_SESSION['message_type'] = "error";
    } else {
        $conn->begin_transaction();
        
        try {
            $rejected_count = 0;
            $rejection_reasons = [];
            
            foreach ($rejections as $rejection) {
                if (!isset($rejection['doc_id']) || !isset($rejection['reason'])) {
                    continue;
                }
                
                $doc_id = intval($rejection['doc_id']);
                $reason = trim($rejection['reason']);
                
                if (empty($reason)) {
                    continue;
                }
                
                // Update the specific document
                $stmt = $conn->prepare("UPDATE alumni_documents SET document_status = 'Rejected', rejection_reason = ?, rejected_at = NOW() WHERE doc_id = ?");
                $stmt->bind_param("si", $reason, $doc_id);
                $stmt->execute();
                $stmt->close();
                
                $rejected_count++;
                $rejection_reasons[] = $reason;
            }
            
            // Update alumni_profile submitted_at timestamp
            $updateTimestampStmt = $conn->prepare("UPDATE alumni_profile SET submitted_at = NOW() WHERE user_id = ?");
            $updateTimestampStmt->bind_param("i", $user_id);
            $updateTimestampStmt->execute();
            $updateTimestampStmt->close();
            
            // Send rejection notification to alumni
            try {
                // Combine rejection reasons
                $combined_reason = implode("; ", array_unique($rejection_reasons));
                send_rejection_notification($conn, $user_id, $combined_reason);
                error_log("Rejection notification sent to alumni user: $user_id");
            } catch (Exception $e) {
                error_log("Failed to send rejection notification: " . $e->getMessage());
            }
            
            // Log the action
            $details = "Rejected $rejected_count document(s) via bulk rejection";
            $logStmt = $conn->prepare("INSERT INTO update_log (updated_by, updated_id, update_type, update_details, updated_at) VALUES (?, ?, 'reject', ?, NOW())");
            $logStmt->bind_param("iis", $admin_id, $user_id, $details);
            $logStmt->execute();
            $logStmt->close();
            
            $conn->commit();
            
            $_SESSION['message'] = "Successfully rejected $rejected_count document(s)";
            $_SESSION['message_type'] = "success";
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
} 
// Handle regular status updates
elseif (isset($_GET['user_id']) && isset($_GET['status'])) {
    $user_id = intval($_GET['user_id']);
    $status = $_GET['status'];
    $reason = $_GET['reason'] ?? '';
    $doc_id = $_GET['doc_id'] ?? null;
    $type = $_GET['type'] ?? 'profile'; // 'profile' or 'document'
    $admin_id = $_SESSION["user_id"];

    // Validate status against database ENUM values
    $valid_statuses = ['Approved', 'Rejected', 'Pending'];
    if (!in_array($status, $valid_statuses)) {
        $_SESSION['message'] = "Invalid status parameter";
        $_SESSION['message_type'] = "error";
    } else {
        // Validate rejection reason for Rejected status
        if ($status === 'Rejected' && empty($reason)) {
            $_SESSION['message'] = "Rejection reason is required when rejecting documents";
            $_SESSION['message_type'] = "error";
        } else {
            // Start transaction for atomic operations
            $conn->begin_transaction();
            
            try {
                if ($type === 'document' && $doc_id) {
                    // --- DOCUMENT-SPECIFIC UPDATE ---
                    // Get current document status before update
                    $currentDocQuery = $conn->prepare("SELECT document_status, user_id FROM alumni_documents WHERE doc_id = ?");
                    $currentDocQuery->bind_param("i", $doc_id);
                    $currentDocQuery->execute();
                    $currentDocResult = $currentDocQuery->get_result();
                    $currentDocRow = $currentDocResult->fetch_assoc();
                    $currentDocStatus = $currentDocRow['document_status'] ?? '';
                    $doc_user_id = $currentDocRow['user_id'] ?? $user_id;
                    $currentDocQuery->close();
                    
                    // Update the specific document
                    if ($status === 'Rejected') {
                        $stmt = $conn->prepare("UPDATE alumni_documents SET document_status = ?, rejection_reason = ?, rejected_at = NOW() WHERE doc_id = ?");
                        $stmt->bind_param("ssi", $status, $reason, $doc_id);
                    } else {
                        // For approved or pending, clear rejection data
                        $stmt = $conn->prepare("UPDATE alumni_documents SET document_status = ?, rejection_reason = NULL, rejected_at = NULL WHERE doc_id = ?");
                        $stmt->bind_param("si", $status, $doc_id);
                    }
                    
                    $stmt->execute();
                    $stmt->close();
                    
                    // Update alumni_profile submitted_at timestamp when ANY document is approved OR rejected
                    if ($status === 'Approved' || $status === 'Rejected') {
                        $updateTimestampStmt = $conn->prepare("UPDATE alumni_profile SET submitted_at = NOW() WHERE user_id = ?");
                        $updateTimestampStmt->bind_param("i", $doc_user_id);
                        $updateTimestampStmt->execute();
                        $updateTimestampStmt->close();
                    }
                    
                    // SEND NOTIFICATION TO ALUMNI BASED ON STATUS
                    try {
                        if ($status === 'Approved') {
                            send_approval_notification($conn, $doc_user_id);
                            error_log("Approval notification sent to alumni user: $doc_user_id");
                        } elseif ($status === 'Rejected') {
                            send_rejection_notification($conn, $doc_user_id, $reason);
                            error_log("Rejection notification sent to alumni user: $doc_user_id");
                        }
                    } catch (Exception $e) {
                        error_log("Failed to send notification: " . $e->getMessage());
                    }
                    
                    // LOG THE ACTION - Document specific
                    $update_type = '';
                    $details = '';
                    
                    if ($status === 'Approved') {
                        $update_type = 'approve';
                        $details = "Approved specific document (ID: $doc_id)";
                    } elseif ($status === 'Rejected') {
                        $update_type = 'reject';
                        $details = "Rejected specific document (ID: $doc_id)";
                        if (!empty($reason)) {
                            $details .= " - Reason: " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
                        }
                    } elseif ($status === 'Pending') {
                        $update_type = 'update';
                        // Provide context for undo action
                        if ($currentDocStatus === 'Approved') {
                            $details = "Undo approval - Reverted document to pending status (ID: $doc_id)";
                        } elseif ($currentDocStatus === 'Rejected') {
                            $details = "Undo rejection - Reverted document to pending status (ID: $doc_id)";
                        } else {
                            $details = "Changed document status to pending (ID: $doc_id)";
                        }
                    }
                    
                    // Insert into update_log
                    $logStmt = $conn->prepare("INSERT INTO update_log (updated_by, updated_id, update_type, update_details, updated_at) VALUES (?, ?, ?, ?, NOW())");
                    $logStmt->bind_param("iiss", $admin_id, $doc_user_id, $update_type, $details);
                    $logStmt->execute();
                    $logStmt->close();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Set success message
                    if ($status === 'Pending') {
                        $_SESSION['message'] = "Document reverted to pending successfully";
                    } elseif ($status === 'Approved') {
                        $_SESSION['message'] = "Document approved successfully";
                    } else {
                        $_SESSION['message'] = "Document rejected successfully" . ($reason ? " - Reason: " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') : "");
                    }
                    $_SESSION['message_type'] = "success";
                    
                } 
                // Handle bulk document actions
                elseif ($type === 'document_bulk' && isset($_GET['doc_ids'])) {
                    $doc_ids = explode(',', $_GET['doc_ids']);
                    $valid_doc_ids = array_filter(array_map('intval', $doc_ids));
                    
                    if (empty($valid_doc_ids)) {
                        throw new Exception("No valid document IDs provided");
                    }
                    
                    // Update all specified documents
                    $placeholders = implode(',', array_fill(0, count($valid_doc_ids), '?'));
                    $types = str_repeat('i', count($valid_doc_ids));
                    
                    if ($status === 'Rejected') {
                        // For bulk reject, we need individual reasons - this should use the bulk_reject handler above
                        throw new Exception("Bulk rejection requires individual reasons for each document");
                    } else {
                        // For bulk approve or revert to pending
                        $query = "UPDATE alumni_documents SET document_status = ?, rejection_reason = NULL, rejected_at = NULL WHERE doc_id IN ($placeholders)";
                        $stmt = $conn->prepare($query);
                        $params = array_merge([$status], $valid_doc_ids);
                        $stmt->bind_param(str_repeat('s', 1) . $types, ...$params);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    // Update alumni_profile submitted_at timestamp
                    if ($status === 'Approved' || $status === 'Rejected') {
                        $updateTimestampStmt = $conn->prepare("UPDATE alumni_profile SET submitted_at = NOW() WHERE user_id = ?");
                        $updateTimestampStmt->bind_param("i", $user_id);
                        $updateTimestampStmt->execute();
                        $updateTimestampStmt->close();
                    }
                    
                    // SEND NOTIFICATION TO ALUMNI FOR BULK APPROVAL
                    try {
                        if ($status === 'Approved') {
                            send_approval_notification($conn, $user_id);
                            error_log("Bulk approval notification sent to alumni user: $user_id");
                        }
                    } catch (Exception $e) {
                        error_log("Failed to send bulk notification: " . $e->getMessage());
                    }
                    
                    // LOG THE ACTION - Bulk document
                    $update_type = '';
                    $details = '';
                    
                    if ($status === 'Approved') {
                        $update_type = 'approve';
                        $details = "Approved " . count($valid_doc_ids) . " document(s) via bulk action";
                    } elseif ($status === 'Pending') {
                        $update_type = 'update';
                        $details = "Reverted " . count($valid_doc_ids) . " document(s) to pending status via bulk action";
                    }
                    
                    // Insert into update_log
                    $logStmt = $conn->prepare("INSERT INTO update_log (updated_by, updated_id, update_type, update_details, updated_at) VALUES (?, ?, ?, ?, NOW())");
                    $logStmt->bind_param("iiss", $admin_id, $user_id, $update_type, $details);
                    $logStmt->execute();
                    $logStmt->close();
                    
                    $conn->commit();
                    
                    $_SESSION['message'] = "Successfully updated " . count($valid_doc_ids) . " document(s) to " . strtolower($status);
                    $_SESSION['message_type'] = "success";
                    
                } else {
                    // --- PROFILE-LEVEL UPDATE (legacy) ---
                    // Get current document status before update for undo context
                    $currentStatusQuery = $conn->prepare("SELECT document_status FROM alumni_documents WHERE user_id = ? LIMIT 1");
                    $currentStatusQuery->bind_param("i", $user_id);
                    $currentStatusQuery->execute();
                    $currentStatusResult = $currentStatusQuery->get_result();
                    $currentStatusRow = $currentStatusResult->fetch_assoc();
                    $currentStatus = $currentStatusRow['document_status'] ?? '';
                    $currentStatusQuery->close();

                    // Update ALL documents for this user
                    if ($status === 'Rejected') {
                        $stmt = $conn->prepare("UPDATE alumni_documents SET document_status = ?, rejection_reason = ?, rejected_at = NOW() WHERE user_id = ?");
                        $stmt->bind_param("ssi", $status, $reason, $user_id);
                    } else {
                        // For approved or pending, clear rejection data
                        $stmt = $conn->prepare("UPDATE alumni_documents SET document_status = ?, rejection_reason = NULL, rejected_at = NULL WHERE user_id = ?");
                        $stmt->bind_param("si", $status, $user_id);
                    }

                    if ($stmt->execute()) {
                        // Update alumni_profile submitted_at timestamp when documents are approved OR rejected
                        if ($status === 'Approved' || $status === 'Rejected') {
                            $updateTimestampStmt = $conn->prepare("UPDATE alumni_profile SET submitted_at = NOW() WHERE user_id = ?");
                            $updateTimestampStmt->bind_param("i", $user_id);
                            $updateTimestampStmt->execute();
                            $updateTimestampStmt->close();
                        }
                        
                        // SEND NOTIFICATION TO ALUMNI
                        try {
                            if ($status === 'Approved') {
                                send_approval_notification($conn, $user_id);
                                error_log("Profile-level approval notification sent to alumni user: $user_id");
                            } elseif ($status === 'Rejected') {
                                send_rejection_notification($conn, $user_id, $reason);
                                error_log("Profile-level rejection notification sent to alumni user: $user_id");
                            }
                        } catch (Exception $e) {
                            error_log("Failed to send profile-level notification: " . $e->getMessage());
                        }
                        
                        // LOG THE ACTION - Profile level
                        $update_type = '';
                        $details = '';
                        
                        if ($status === 'Approved') {
                            $update_type = 'approve';
                            $details = "Approved all alumni documents";
                        } elseif ($status === 'Rejected') {
                            $update_type = 'reject';
                            $details = "Rejected all alumni documents";
                            if (!empty($reason)) {
                                $details .= " - Reason: " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
                            }
                        } elseif ($status === 'Pending') {
                            $update_type = 'update';
                            // Provide context for undo action
                            if ($currentStatus === 'Approved') {
                                $details = "Undo approval - Reverted all documents to pending status";
                            } elseif ($currentStatus === 'Rejected') {
                                $details = "Undo rejection - Reverted all documents to pending status";
                            } else {
                                $details = "Changed all document statuses to pending";
                            }
                        }
                        
                        // Insert into update_log
                        $logStmt = $conn->prepare("INSERT INTO update_log (updated_by, updated_id, update_type, update_details, updated_at) VALUES (?, ?, ?, ?, NOW())");
                        $logStmt->bind_param("iiss", $admin_id, $user_id, $update_type, $details);
                        $logStmt->execute();
                        $logStmt->close();
                        
                        // Commit both operations
                        $conn->commit();
                        
                        if ($status === 'Pending') {
                            $_SESSION['message'] = "Documents reverted to pending successfully";
                        } elseif ($status === 'Approved') {
                            $_SESSION['message'] = "Documents approved successfully";
                        } else {
                            $_SESSION['message'] = "Documents rejected successfully" . ($reason ? " - Reason: " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') : "");
                        }
                        $_SESSION['message_type'] = "success";
                    } else {
                        throw new Exception("Database update error: " . $conn->error);
                    }
                    $stmt->close();
                }
                
            } catch (Exception $e) {
                // Rollback on any error
                $conn->rollback();
                $_SESSION['message'] = "Error: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
        }
    }
} else {
    $_SESSION['message'] = "Invalid request parameters";
    $_SESSION['message_type'] = "error";
}

// === SMART REDIRECT BACK TO ORIGINAL PAGE WITH ALL FILTERS ===

$redirect_url = "all_alumni.php"; // default fallback

if ($came_from_batch && isset($_GET['batch'])) {
    // Rebuild batch page URL with all current GET parameters
    $batch = $_GET['batch'];
    $query_params = [
        'batch' => $batch,
        'search' => $_GET['search'] ?? '',
        'employment_status' => $_GET['employment_status'] ?? '',
        'submission_status' => $_GET['submission_status'] ?? ''
    ];
    // Remove empty values
    $query_params = array_filter($query_params, function($v) { return $v !== ''; });
    $redirect_url = "batch_alumni.php?" . http_build_query($query_params);
}
elseif ($came_from_all) {
    // Rebuild all_alumni.php with filters
    $query_params = [
        'search' => $_GET['search'] ?? '',
        'employment_status' => $_GET['employment_status'] ?? '',
        'submission_status' => $_GET['submission_status'] ?? ''
    ];
    $query_params = array_filter($query_params, function($v) { return $v !== ''; });
    $redirect_url = "all_alumni.php" . ($query_params ? "?" . http_build_query($query_params) : "");
}

// Add success/error message parameter to redirect URL
if (isset($_SESSION['message'])) {
    $message_type = $_SESSION['message_type'] ?? 'success';
    $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . "message=" . urlencode($_SESSION['message']) . "&message_type=" . $message_type;
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

header("Location: $redirect_url");
exit();
?>