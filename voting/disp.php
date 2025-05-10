<?php
require_once 'config.php';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Fetch unique, non-empty positions (trimmed)
$sql = "SELECT DISTINCT TRIM(position) AS position FROM candidate WHERE position IS NOT NULL AND TRIM(position) != ''";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Candidate Positions</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap for responsive styling -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <h2 class="mb-4 text-center">Candidate Positions</h2>
    <div class="row justify-content-center">
      <div class="col-md-8">
        <ul class="list-group shadow">
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <?php echo htmlspecialchars($row['position']); ?>
                <span class="badge bg-primary rounded-pill">Available</span>
              </li>
            <?php endwhile; ?>
          <?php else: ?>
            <li class="list-group-item text-danger">No positions found.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Optional Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
