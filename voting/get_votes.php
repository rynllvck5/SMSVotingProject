<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed"]));
}

// First, get all valid codeNames from candidates table
$validCodes = [];
$codeQuery = "SELECT codeName FROM candidate";
$codeResult = $conn->query($codeQuery);
if ($codeResult->num_rows > 0) {
    while ($row = $codeResult->fetch_assoc()) {
        $validCodes[strtoupper($row['codeName'])] = true;
    }
}

// Then fetch only VALID votes (Remark is set by SQL Trigger) with sender information
$sql = "SELECT msg, sender FROM ozekimessagein WHERE Remarks = 'VALID'";
$result = $conn->query($sql);

$votes = [];
$voterDetails = []; // New array to store voter details per candidate

// Process each valid vote
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $msg = strtoupper(trim($row["msg"]));
        $sender = $row["sender"];
        $msg = str_replace(["VOTE ", "VOTED "], "", $msg);
        $voteEntries = explode(",", $msg);

        $categories = []; // Track votes per category in this message
        $validVotes = []; // Stores valid votes for counting

        foreach ($voteEntries as $vote) {
            $vote = trim($vote);
            // First check if this is a valid candidate code
            if (isset($validCodes[$vote])) {
                // Then validate position rules
                if (preg_match('/^([PVSTAB]|PRO)(\d+)$/', $vote, $matches)) {
                    $category = $matches[1]; // P, V, S, T, A, B, or PRO
                    $candidate = $matches[1] . $matches[2]; // Example: "P1", "V2", "PRO3"
                
                    // Ensure only one vote per position
                    if (isset($categories[$category])) {
                        $categories[$category] = false; // Mark category as invalid (duplicate)
                    } else {
                        $categories[$category] = $candidate; // Store the candidate
                    }
                }
            }
        }

        // Process only valid votes (categories with a single unique vote that exists in candidates table)
        foreach ($categories as $category => $candidate) {
            if ($candidate !== false && isset($validCodes[$candidate])) {
                // Count the vote
                $votes[$candidate] = ($votes[$candidate] ?? 0) + 1;
                
                // Store voter information (sender phone number)
                if (!isset($voterDetails[$candidate])) {
                    $voterDetails[$candidate] = [];
                }
                $voterDetails[$candidate][] = $sender;
            }
        }
    }
}

// Store voter details in a persistent table for later retrieval
$conn->query("DROP TABLE IF EXISTS voter_details");
$conn->query("CREATE TABLE voter_details (
    candidate_code VARCHAR(10),
    sender VARCHAR(20),
    INDEX (candidate_code)
)");

if (!empty($voterDetails)) {
    $insertValues = [];
    foreach ($voterDetails as $candidate => $senders) {
        foreach ($senders as $sender) {
            $insertValues[] = "('" . $conn->real_escape_string($candidate) . "', '" . $conn->real_escape_string($sender) . "')";
        }
    }
    if (!empty($insertValues)) {
        $conn->query("INSERT INTO voter_details (candidate_code, sender) VALUES " . implode(",", $insertValues));
    }
}

$conn->close();
echo json_encode($votes);
?>