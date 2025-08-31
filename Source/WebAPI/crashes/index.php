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

$sql = "SELECT id, application_version, game_version, datetime, os_name, cpu_name, ram_total, current_level, current_composite, current_entity FROM crashes ORDER BY datetime DESC";
$result = $conn->query($sql);

$crashes_by_version_sql = "SELECT application_version, game_version, COUNT(*) as count FROM crashes GROUP BY application_version, game_version ORDER BY count DESC";
$crashes_over_time_sql = "SELECT DATE(datetime) as date, COUNT(*) as count FROM crashes GROUP BY DATE(datetime) ORDER BY DATE(datetime)";

$crashes_by_entity_sql = "SELECT current_entity, COUNT(*) as count FROM crashes GROUP BY current_entity ORDER BY count DESC LIMIT 10";
$crashes_by_level_sql = "SELECT current_level, COUNT(*) as count FROM crashes GROUP BY current_level ORDER BY count DESC LIMIT 10";
$crashes_by_composite_sql = "SELECT current_level, current_composite, current_entity, COUNT(*) as count FROM crashes GROUP BY current_level, current_composite, current_entity ORDER BY count DESC LIMIT 10";

$versions_result = $conn->query($crashes_by_version_sql);
$time_result = $conn->query($crashes_over_time_sql);
$entities_result = $conn->query($crashes_by_entity_sql);
$levels_result = $conn->query($crashes_by_level_sql);
$composites_result = $conn->query($crashes_by_composite_sql);

$crashes_by_version = [];
while($row = $versions_result->fetch_assoc()) {
    $crashes_by_version[$row['application_version'] . ' / ' . $row['game_version']] = $row['count'];
}

$crashes_over_time = [];
while($row = $time_result->fetch_assoc()) {
    $crashes_over_time[$row['date']] = $row['count'];
}

$crashes_by_entity = [];
while($row = $entities_result->fetch_assoc()) {
    $crashes_by_entity[] = $row;
}

$crashes_by_level = [];
while($row = $levels_result->fetch_assoc()) {
    $crashes_by_level[] = $row;
}

$crashes_by_composite = [];
while($row = $composites_result->fetch_assoc()) {
    $crashes_by_composite[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crash Log Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 1400px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        .stats { display: flex; justify-content: space-around; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-box { background: #e9ecef; padding: 15px; border-radius: 5px; text-align: center; margin: 10px; flex: 1; min-width: 200px; }
        .stat-box h3 { margin: 0; font-size: 1.2em; color: #555; }
        .stat-box p { font-size: 2em; margin: 5px 0 0; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; cursor: pointer; }
        tr:hover { background-color: #f9f9f9; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
        .chart-container { display: flex; justify-content: center; gap: 20px; margin-bottom: 30px; }
        .chart { width: 45%; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <h1>Crash Log Dashboard</h1>

        <div class="stats">
            <div class="stat-box">
                <h3>Total Crashes</h3>
                <p><?php echo $result->num_rows; ?></p>
            </div>
            <div class="stat-box">
                <h3>Crashes by Version</h3>
                <?php foreach($crashes_by_version as $version => $count): ?>
                    <p><strong><?php echo htmlspecialchars($version); ?>:</strong> <?php echo htmlspecialchars($count); ?></p>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="chart-container">
            <div class="chart">
                <h2>Crashes Over Time</h2>
                <canvas id="crashesOverTimeChart"></canvas>
            </div>
        </div>

        <h2>Cumulative Crash Data</h2>
        <div class="stats">
            <div class="stat-box">
                <h3>Top 10 Entities</h3>
                <table>
                    <thead>
                        <tr><th>Entity</th><th>Crashes</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($crashes_by_entity as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['current_entity']); ?></td>
                                <td><?php echo htmlspecialchars($row['count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="stat-box">
                <h3>Top 10 Levels</h3>
                <table>
                    <thead>
                        <tr><th>Level</th><th>Crashes</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($crashes_by_level as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['current_level']); ?></td>
                                <td><?php echo htmlspecialchars($row['count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="stat-box">
                <h3>Top 10 Composites</h3>
                <table>
                    <thead>
                        <tr><th>Level</th><th>Composite</th><th>Entity</th><th>Crashes</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($crashes_by_composite as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['current_level']); ?></td>
                                <td><?php echo htmlspecialchars($row['current_composite']); ?></td>
                                <td><?php echo htmlspecialchars($row['current_entity']); ?></td>
                                <td><?php echo htmlspecialchars($row['count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <h2>All Crash Logs</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date/Time</th>
                    <th>App Version</th>
                    <th>Game Version</th>
                    <th>OS</th>
                    <th>CPU</th>
                    <th>RAM</th>
                    <th>Current Level</th>
                    <th>Current Composite</th>
                    <th>Current Entity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr data-id="<?php echo $row['id']; ?>">
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['datetime']); ?></td>
                        <td><?php echo htmlspecialchars($row['application_version']); ?></td>
                        <td><?php echo htmlspecialchars($row['game_version']); ?></td>
                        <td><?php echo htmlspecialchars($row['os_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['cpu_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['ram_total']); ?></td>
                        <td><?php echo htmlspecialchars($row['current_level']); ?></td>
                        <td><?php echo htmlspecialchars($row['current_composite']); ?></td>
                        <td><?php echo htmlspecialchars($row['current_entity']); ?></td>
                        <td><button onclick="showErrorLog(<?php echo htmlspecialchars($row['id']); ?>)">View Log</button></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10">No crash logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div id="errorModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Full Error Log</h2>
                <pre id="errorLogContent"></pre>
            </div>
        </div>
    </div>

    <script>
        const crashesOverTimeData = {
            labels: [<?php echo "'" . implode("', '", array_keys($crashes_over_time)) . "'"; ?>],
            datasets: [{
                label: 'Crashes Per Day',
                data: [<?php echo implode(", ", array_values($crashes_over_time)); ?>],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        };

        window.onload = function() {
            const ctxTime = document.getElementById('crashesOverTimeChart').getContext('2d');
            new Chart(ctxTime, {
                type: 'line',
                data: crashesOverTimeData,
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        };

        async function showErrorLog(id) {
            try {
                const response = await fetch(`get_log.php?id=${id}`);
                const data = await response.text();
                document.getElementById('errorLogContent').textContent = data;
                document.getElementById('errorModal').style.display = 'block';
            } catch (error) {
                console.error('Error fetching log:', error);
            }
        }

        function closeModal() {
            document.getElementById('errorModal').style.display = 'none';
        }
    </script>
</body>
</html>