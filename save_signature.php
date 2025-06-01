<?php
// save_signature.php
session_start();
include_once 'config/database.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Permintaan tidak sah.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $application_id = filter_var($input['application_id'] ?? null, FILTER_VALIDATE_INT);
    $role = filter_var($input['role'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $name = filter_var($input['name'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $position = filter_var($input['position'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $signature_data = $input['signature_data'] ?? null;
    $signature_date = date('Y-m-d'); // Current date

    // Basic validation
    if (!$application_id || empty($role) || empty($name) || empty($position) || empty($signature_data)) {
        $response['message'] = 'Data yang diperlukan tidak lengkap.';
        echo json_encode($response);
        exit();
    }

    // Security check: Only allow 'applicant' role if not logged in as admin
    // For 'approver', 'returner', 'receiver' roles, require admin login
    $isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

    if ($role !== 'applicant' && !$isAdmin) {
        $response['message'] = 'Anda tidak mempunyai kebenaran untuk menandatangani sebagai peranan ini.';
        echo json_encode($response);
        exit();
    }

    try {
        // Check if a signature for this role and application already exists
        $stmtCheck = $pdo->prepare("SELECT signature_id FROM signatures WHERE application_id = :application_id AND role = :role");
        $stmtCheck->bindParam(':application_id', $application_id, PDO::PARAM_INT);
        $stmtCheck->bindParam(':role', $role);
        $stmtCheck->execute();

        if ($stmtCheck->fetch()) {
            // Update existing signature
            $stmt = $pdo->prepare("UPDATE signatures SET name = :name, position = :position, signature_date = :signature_date, signature_data = :signature_data WHERE application_id = :application_id AND role = :role");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':position', $position);
            $stmt->bindParam(':signature_date', $signature_date);
            $stmt->bindParam(':signature_data', $signature_data);
            $stmt->bindParam(':application_id', $application_id, PDO::PARAM_INT);
            $stmt->bindParam(':role', $role);
            $stmt->execute();
            $response = ['status' => 'success', 'message' => 'Tandatangan berjaya dikemas kini.'];
        } else {
            // Insert new signature
            $stmt = $pdo->prepare("INSERT INTO signatures (application_id, role, name, position, signature_date, signature_data) VALUES (:application_id, :role, :name, :position, :signature_date, :signature_data)");
            $stmt->bindParam(':application_id', $application_id, PDO::PARAM_INT);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':position', $position);
            $stmt->bindParam(':signature_date', $signature_date);
            $stmt->bindParam(':signature_data', $signature_data);
            $stmt->execute();
            $response = ['status' => 'success', 'message' => 'Tandatangan berjaya disimpan.'];
        }
    } catch (PDOException $e) {
        $response['message'] = 'Ralat pangkalan data: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
