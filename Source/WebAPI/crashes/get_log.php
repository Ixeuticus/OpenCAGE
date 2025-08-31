<?php

if (file_exists('../keys.php')) {
    require_once '../keys.php';
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database keys file not found. Please create keys.php']);
    exit;
}

$conn = new mysqli("localhost", $username, $password, "OpenCAGE");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT error_log FROM crashes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo htmlspecialchars($row['error_log']);
    } else {
        echo "Log not found.";
    }
    
    $stmt->close();
}

$conn->close();
?>