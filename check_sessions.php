<?php
header('Content-Type: application/json');

if (!isset($_GET['class']) || empty($_GET['class'])) {
    echo json_encode(["message" => "Invalid class selection."]);
    exit;
}

$class = basename($_GET['class']); // Protegge contro path traversal
$folderPath = "simulatedclass/$class";

if (!is_dir($folderPath)) {
    echo json_encode(["message" => "Class directory not found."]);
    exit;
}

$sessionFiles = glob("$folderPath/{$class}_session_*");
if (empty($sessionFiles)) {
    echo json_encode(["message" => "No session files found for $class. It will create a new one."]);
} else {
    $sessions = array_map('basename', $sessionFiles);
    echo json_encode(["sessions" => $sessions]);
}
?>
