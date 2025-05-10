<?php
require_once 'config.php';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter parameters
$userType = isset($_GET['userType']) ? $_GET['userType'] : 'all';
$positionFilter = isset($_GET['position']) ? $_GET['position'] : 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .message-content {
            max-width: 300px;
            word-wrap: break-word;
        }
        #positionFilterGroup {
            display: none;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <h1 class="mb-4">Admin Dashboard</h1>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-4">
                    <label for="userType" class="form-label">User Type</label>
                    <select class="form-select" id="userType" name="userType">
                        <option value="all" <?= $userType == 'all' ? 'selected' : '' ?>>All Users</option>
                        <option value="candidate" <?= $userType == 'candidate' ? 'selected' : '' ?>>Candidate</option>
                        <option value="voter" <?= $userType == 'voter' ? 'selected' : '' ?>>Voter</option>
                    </select>
                </div>
                <div class="col-md-4" id="positionFilterGroup">
                    <label id="positionLabel" for="position" class="form-label">Candidate Position</label>
                    <select class="form-select" id="position" name="position">
                        <?php
                        $positions = [
                            'President' => 'President',
                            'Vice-President' => 'Vice-President',
                            'Secretary' => 'Secretary',
                            'Treasurer' => 'Treasurer',
                            'Auditor' => 'Auditor',
                            'Business Manager' => 'Business Manager',
                            'Press Relation Offic' => 'Press Relation Officer'  // key is DB value, value is display label
                        ];                        

                        if ($userType == 'all') {
                            echo '<option value="all"' . ($positionFilter == 'all' ? ' selected' : '') . '>All Users</option>';
                            echo '<option value="candidate"' . ($positionFilter == 'candidate' ? ' selected' : '') . '>Candidate Only</option>';
                            echo '<option value="voter"' . ($positionFilter == 'voter' ? ' selected' : '') . '>Voter Only</option>';
                        } else {
                            echo '<option value="all"' . ($positionFilter == 'all' ? ' selected' : '') . '>All Positions</option>';
                            foreach ($positions as $value => $label) {
                                $selected = ($positionFilter == $value) ? 'selected' : '';
                                echo "<option value=\"$value\" $selected>$label</option>";
                            }                                                      
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Section -->
    <div class="table-responsive">
        <?php
        if ($userType == 'all' || $userType == 'voter' || $userType == 'candidate') {
            if ($userType == 'all') {
                $sql = "SELECT 
                            'voter' as userType,
                            NULL as candidateID,
                            voterID,
                            firstName,
                            middleName,
                            lastName,
                            contactNumber,
                            NULL as position
                        FROM voter";

                if ($positionFilter == 'candidate') {
                    $sql = "SELECT 
                                'candidate' as userType,
                                candidateID,
                                NULL as voterID,
                                firstName,
                                middleName,
                                lastName,
                                contactNumber,
                                position
                            FROM candidate";
                } elseif ($positionFilter == 'voter') {
                    $sql = "SELECT 
                                'voter' as userType,
                                NULL as candidateID,
                                voterID,
                                firstName,
                                middleName,
                                lastName,
                                contactNumber,
                                NULL as position
                            FROM voter";
                } else {
                    $sql .= " UNION ALL
                            SELECT 
                                'candidate' as userType,
                                candidateID,
                                NULL as voterID,
                                firstName,
                                middleName,
                                lastName,
                                contactNumber,
                                position
                            FROM candidate";
                }

                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    echo '<h3>All Users</h3>';
                    echo '<table class="table table-striped table-bordered">';
                    echo '<thead><tr><th>User Type</th><th>ID</th><th>First Name</th><th>Middle Name</th><th>Last Name</th><th>Contact Number</th><th>Position</th></tr></thead>';
                    echo '<tbody>';

                    while ($row = $result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . ucfirst($row['userType']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['userType'] == 'candidate' ? $row['candidateID'] : $row['voterID']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['firstName']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['middleName']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['lastName']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['contactNumber']) . '</td>';
                        echo '<td>' . htmlspecialchars($positions[$row['position']] ?? $row['position']) . '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                } else {
                    echo '<div class="alert alert-info">No users found.</div>';
                }
            } elseif ($userType == 'voter') {
                $sql = "SELECT 
                            voterID,
                            firstName,
                            middleName,
                            lastName,
                            contactNumber
                        FROM voter";

                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    echo '<h3>Voters</h3>';
                    echo '<table class="table table-striped table-bordered">';
                    echo '<thead><tr><th>Voter ID</th><th>First Name</th><th>Middle Name</th><th>Last Name</th><th>Contact Number</th><th>Confirmation Messages</th></tr></thead>';
                    echo '<tbody>';

                    while ($row = $result->fetch_assoc()) {
                        $confirmationSql = "SELECT msg, senttime FROM ozekimessageout 
                                            WHERE receiver = ? AND msg LIKE 'You have voted for %'";
                        $stmt = $conn->prepare($confirmationSql);
                        $stmt->bind_param("s", $row['contactNumber']);
                        $stmt->execute();
                        $confirmationResult = $stmt->get_result();

                        $confirmations = [];
                        while ($conf = $confirmationResult->fetch_assoc()) {
                            $confirmations[] = htmlspecialchars($conf['msg']) . ' (' . $conf['senttime'] . ')';
                        }

                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['voterID']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['firstName']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['middleName']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['lastName']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['contactNumber']) . '</td>';
                        echo '<td class="message-content">' . (!empty($confirmations) ? implode('<br>', $confirmations) : 'No confirmations') . '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                } else {
                    echo '<div class="alert alert-info">No voters found.</div>';
                }
            } elseif ($userType == 'candidate') {
                $sql = "SELECT 
                            candidateID,
                            firstName,
                            middleName,
                            lastName,
                            contactNumber,
                            position,
                            codeName
                        FROM candidate
                        WHERE codeName REGEXP '^[a-z]+[0-9]+$'";

                if ($positionFilter != 'all') {
                    $sql .= " AND position = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $positionFilter);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $conn->query($sql);
                }

                if ($result->num_rows > 0) {
                    echo '<h3>Candidates</h3>';
                    echo '<table class="table table-striped table-bordered">';
                    echo '<thead><tr><th>Candidate ID</th><th>First Name</th><th>Middle Name</th><th>Last Name</th><th>Contact Number</th><th>Position</th><th>Code Name</th></tr></thead>';
                    echo '<tbody>';

                    while ($row = $result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['candidateID']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['firstName']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['middleName']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['lastName']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['contactNumber']) . '</td>';
                        echo '<td>' . htmlspecialchars($positions[$row['position']] ?? $row['position']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['codeName']) . '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';

                    // Vote count using voter_details table
                    echo '<h4 class="mt-4">Vote Counts (Based on Voter Details)</h4>';

                    $voteCountSql = "
                        SELECT 
                            c.position,
                            c.firstName,
                            c.lastName,
                            c.codeName,
                            COUNT(vd.candidate_code) as vote_count
                        FROM candidate c
                        LEFT JOIN voter_details vd ON vd.candidate_code = c.codeName
                    ";

                    if ($positionFilter != 'all') {
                        $voteCountSql .= " WHERE c.position = ?
                                        GROUP BY c.candidateID, c.position, c.firstName, c.lastName, c.codeName";
                        $stmt = $conn->prepare($voteCountSql);
                        $stmt->bind_param("s", $positionFilter);
                        $stmt->execute();
                        $voteCountResult = $stmt->get_result();
                    } else {
                        $voteCountSql .= " GROUP BY c.candidateID, c.position, c.firstName, c.lastName, c.codeName";
                        $voteCountResult = $conn->query($voteCountSql);
                    }

                    if ($voteCountResult->num_rows > 0) {
                        echo '<table class="table table-striped table-bordered">';
                        echo '<thead><tr><th>Position</th><th>Candidate</th><th>Code Name</th><th>Vote Count</th></tr></thead>';
                        echo '<tbody>';

                        while ($voteCount = $voteCountResult->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($positions[$voteCount['position']] ?? $voteCount['position']) . '</td>';
                            echo '<td>' . htmlspecialchars($voteCount['firstName'] . ' ' . $voteCount['lastName']) . '</td>';
                            echo '<td>' . htmlspecialchars($voteCount['codeName']) . '</td>';
                            echo '<td>' . htmlspecialchars($voteCount['vote_count']) . '</td>';
                            echo '</tr>';
                        }

                        echo '</tbody></table>';
                    } else {
                        echo '<div class="alert alert-info">No votes recorded for candidates.</div>';
                    }
                }
            }
        }
        ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const userTypeSelect = document.getElementById('userType');
        const positionFilterGroup = document.getElementById('positionFilterGroup');

        togglePositionFilter();
        userTypeSelect.addEventListener('change', togglePositionFilter);

        function togglePositionFilter() {
            const userType = userTypeSelect.value;
            const positionLabel = document.getElementById('positionLabel');

            if (userType === 'all' || userType === 'voter') {
                positionFilterGroup.style.display = 'none';
            } else {
                positionFilterGroup.style.display = 'block';
                positionLabel.textContent = 'Candidate Position';
            }
        }
    });
</script>
</body>
</html>
<?php
$conn->close();
?>
