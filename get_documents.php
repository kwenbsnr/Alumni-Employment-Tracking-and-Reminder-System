<?php
session_start();
include("../connect.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

$alumni_id = isset($_GET['alumni_id']) ? intval($_GET['alumni_id']) : 0;
$stmt = $conn->prepare("SELECT doc_id, document_type, file_path, document_status, rejection_reason FROM alumni_documents WHERE alumni_id = ?");
$stmt->bind_param("i", $alumni_id);
$stmt->execute();
$result = $stmt->get_result();
$docs = [];
while ($row = $result->fetch_assoc()) {
    $docs[] = $row;
}

header('Content-Type: application/json');
echo json_encode($docs);
$conn->close();
?>