<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed", "success" => false]));
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die(json_encode(["error" => "Invalid JSON input", "success" => false]));
}

$requiredFields = ['firstName', 'lastName', 'contactNumber', 'type'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        die(json_encode(["error" => "Missing required field: $field", "success" => false]));
    }
}

// Add debug logging to verify received data
error_log("Received registration data: " . print_r($data, true));

$firstName = $conn->real_escape_string(trim($data['firstName']));
$middleName = isset($data['middleName']) ? $conn->real_escape_string(trim($data['middleName'])) : '';
$lastName = $conn->real_escape_string(trim($data['lastName']));
$contactNumber = trim($data['contactNumber']);
$type = strtolower(trim($data['type'])); // Normalize the type value

// Validate and format contact number
if (!preg_match('/^9\d{9}$/', $contactNumber)) {
    die(json_encode(["error" => "Invalid contact number format. Must be 10 digits starting with 9", "success" => false]));
}

// Format the contact number with +63 prefix
$formattedContactNumber = '+63' . $contactNumber;

$conn->begin_transaction();

try {
    $voterId = null;
    $response = ["success" => true, "type" => $type];

    // Check if contact exists in voter table (using formatted number)
    $checkVoter = $conn->prepare("SELECT voterID FROM voter WHERE contactNumber = ?");
    $checkVoter->bind_param("s", $formattedContactNumber);
    $checkVoter->execute();
    $voterResult = $checkVoter->get_result();
    $isExistingVoter = $voterResult->num_rows > 0;
    $checkVoter->close();

    // Debug log the registration type
    error_log("Processing registration type: " . $type);

    if ($type === 'voter') {
        if ($isExistingVoter) {
            throw new Exception("This contact number is already registered as a voter");
        }

        // Insert new voter with formatted contact number
        $stmt = $conn->prepare("INSERT INTO voter (firstName, middleName, lastName, contactNumber) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $firstName, $middleName, $lastName, $formattedContactNumber);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to register voter: " . $stmt->error);
        }
        
        $voterId = $stmt->insert_id;
        $stmt->close();
        $response["voterId"] = $voterId;
    } elseif ($type === 'candidate') {
        // Debug log for candidate registration
        error_log("Starting candidate registration for contact: " . $formattedContactNumber);
    
        // Check if already registered as candidate (using formatted number)
        $checkCandidate = $conn->prepare("SELECT candidateID FROM candidate WHERE contactNumber = ?");
        $checkCandidate->bind_param("s", $formattedContactNumber);
        $checkCandidate->execute();
        $candidateResult = $checkCandidate->get_result();
        
        if ($candidateResult->num_rows > 0) {
            throw new Exception("This contact number is already registered as a candidate");
        }
        $checkCandidate->close();
    
        // Validate candidate fields
        if (empty($data['position'])) {
            throw new Exception("Position is required for candidate registration");
        }
        
        if (empty($data['image'])) {
            throw new Exception("Image is required for candidate registration");
        }

        // Process candidate data
        $position = $conn->real_escape_string(trim($data['position']));
        $imageData = $data['image'];
        
        if (!preg_match('/^data:image\/(jpeg|png|jpg);base64,/', $imageData, $matches)) {
            throw new Exception("Invalid image format. Only JPEG or PNG allowed");
        }
        
        $imageData = str_replace('data:image/'.$matches[1].';base64,', '', $imageData);
        $imageData = base64_decode($imageData);
        
        if ($imageData === false) {
            throw new Exception("Failed to decode image data");
        }

        // Map positions to their code prefixes
        $positionPrefixMap = [
            'President' => 'p',
            'Vice-President' => 'v',
            'Secretary' => 's',
            'Treasurer' => 't',
            'Auditor' => 'a',
            'Business Manager' => 'b',
            'Press Relation Offic' => 'pro'
        ];

        // Get the prefix for the current position
        $prefix = $positionPrefixMap[$position] ?? '';

        if (empty($prefix)) {
            throw new Exception("Invalid position: " . $position);
        }

        // Count existing candidates for this position to determine the next number
        $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM candidate WHERE position = ?");
        $countStmt->bind_param("s", $position);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $countRow = $countResult->fetch_assoc();
        $nextNumber = $countRow['count'] + 1;
        $countStmt->close();

        // Generate the codeName
        $codeName = $prefix . $nextNumber;

        // For candidates, we don't care if they exist in voter table
        // We just need their voter ID (existing or new)
        if (!$isExistingVoter) {
            $stmt = $conn->prepare("INSERT INTO voter (firstName, middleName, lastName, contactNumber) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $firstName, $middleName, $lastName, $formattedContactNumber);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to register voter: " . $stmt->error);
            }
            
            $voterId = $stmt->insert_id;
            $stmt->close();
        } else {
            // Get existing voter ID
            $row = $voterResult->fetch_assoc();
            $voterId = $row['voterID'];
            error_log("Using existing voter ID: " . $voterId);
        }
        $response["voterId"] = $voterId;

        // Insert into candidate table with formatted contact number and codeName
        $stmt = $conn->prepare("INSERT INTO candidate (firstName, middleName, lastName, contactNumber, picture, position, codeName) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $null = null;
        $stmt->bind_param("ssssbss", $firstName, $middleName, $lastName, $formattedContactNumber, $null, $position, $codeName);
        $stmt->send_long_data(4, $imageData);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to register candidate: " . $stmt->error);
        }
        
        $candidateId = $stmt->insert_id;
        $stmt->close();
        
        $response["candidateId"] = $candidateId;
        $response["position"] = $position;
        $response["codeName"] = $codeName;
        
        error_log("Successfully registered candidate with ID: " . $candidateId . " and codeName: " . $codeName);
    } else {
        throw new Exception("Invalid registration type: " . $type);
    }
    
    $conn->commit();
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(["error" => $e->getMessage(), "success" => false]);
}

$conn->close();
?>