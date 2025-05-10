<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once 'config.php';

// Validate input
if (!isset($_GET['candidate']) || empty(trim($_GET['candidate']))) {
    http_response_code(400);
    die(json_encode([
        "error" => "Candidate code is required",
        "received" => $_GET['candidate'] ?? null,
        "debug" => $_GET
    ]));
}

$candidateCode = strtoupper(trim($_GET['candidate']));

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

// Check if voter_details table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'voter_details'");
if ($tableCheck->num_rows == 0) {
    $conn->close();
    http_response_code(500);
    die(json_encode(["error" => "Vote data not initialized. Please call get_votes.php first."]));
}

// Get voters who voted for this candidate
$sql = "SELECT 
          v.voterID,
          v.firstName,
          v.lastName,
          v.contactNumber
        FROM voter v
        JOIN voter_details vd ON v.contactNumber = vd.sender
        WHERE vd.candidate_code = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    die(json_encode(["error" => "Prepare failed: " . $conn->error]));
}

if (!$stmt->bind_param("s", $candidateCode)) {
    http_response_code(500);
    die(json_encode(["error" => "Bind failed: " . $stmt->error]));
}

if (!$stmt->execute()) {
    http_response_code(500);
    die(json_encode(["error" => "Execute failed: " . $stmt->error]));
}

$result = $stmt->get_result();
$voters = [];

while ($row = $result->fetch_assoc()) {
    $voters[] = [
        'voterID' => $row['voterID'],
        'firstName' => $row['firstName'],
        'lastName' => $row['lastName'],
        'contactNumber' => $row['contactNumber']
    ];
}

$stmt->close();
$conn->close();

if (empty($voters)) {
    http_response_code(404);
    die(json_encode(["message" => "No voters found for candidate $candidateCode"]));
}

echo json_encode($voters);
?>