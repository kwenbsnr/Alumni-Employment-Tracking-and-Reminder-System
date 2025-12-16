<?php
/**
 * Unified submission status function - Single source of truth
 * Returns: 'No Recent Update', 'Pending', 'Approved', or 'Rejected'
 */
function getSubmissionStatus($conn, $user_id) {
    $status = 'No Recent Update'; // Default
    
    // Check if alumni has any documents
    $checkStmt = $conn->prepare("
        SELECT COUNT(*) as doc_count 
        FROM alumni_documents 
        WHERE user_id = ?
    ");
    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($row['doc_count'] == 0) {
        return 'No Recent Update';
    }
    
    // Get document counts by status
    $statusStmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN document_status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN document_status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN document_status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
            COUNT(*) as total_docs
        FROM alumni_documents 
        WHERE user_id = ?
    ");
    $statusStmt->bind_param("i", $user_id);
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    $statusRow = $statusResult->fetch_assoc();
    $statusStmt->close();
    
    // Status determination logic (SINGLE SOURCE OF TRUTH)
    if ($statusRow['rejected_count'] > 0) {
        $status = 'Rejected';
    } elseif ($statusRow['approved_count'] == $statusRow['total_docs']) {
        $status = 'Approved';
    } elseif ($statusRow['pending_count'] > 0 || $statusRow['approved_count'] > 0) {
        $status = 'Pending';
    }
    
    return $status;
}
?>