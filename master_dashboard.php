<?php
include 'db.php';

/* =========================
   DATA LOGIC
   ========================= */
// Total Submissions
$totalQuery = "SELECT COUNT(*) AS total FROM farmer_submissions";
$totalSubmissions = $conn->query($totalQuery)->fetch_assoc()['total'];

// Participation Leaderboard
$leaderboardQuery = "SELECT r.region_name, COUNT(fs.id) AS submissions FROM farmer_submissions fs JOIN regions r ON fs.region_id = r.id GROUP BY r.region_name ORDER BY submissions DESC LIMIT 5";
$leaderboardResult = $conn->query($leaderboardQuery);

// AI Insights Logic
$insightQuery = "SELECT c.crop_name, SUM(ad.value) AS official_value, IFNULL(SUM(fs.value),0) AS farmer_value FROM crops c LEFT JOIN agricultural_data ad ON ad.crop_id=c.id LEFT JOIN farmer_submissions fs ON fs.crop_id=c.id GROUP BY c.crop_name";
$insightResult = $conn->query($insightQuery);
$insights = [];
$cropLabels = [];
$officialValues = [];
$farmerValues = [];

while($row = $insightResult->fetch_assoc()){
    $official = $row['official_value'];
    $farmer = $row['farmer_value'];
    $crop = $row['crop_name'];
    
    // Data for Chart
    $cropLabels[] = $crop;
    $officialValues[] = $official;
    $farmerValues[] = $farmer;

    if($official > 0){
        $diff = $farmer - $official;
        $percent = round(($diff / $official) * 100, 1);
        if($percent > 10) $insights[] = "<strong>$crop</strong> farmer reports are $percent% higher than official estimates.";
        else if($percent < -10) $insights[] = "<strong>$crop</strong> farmer reports are ".abs($percent)."% lower than official estimates.";
        else $insights[] = "<strong>$crop</strong> production levels from farmers closely match official data.";
    }
}

// Prediction Logic
$trendQuery = "SELECT c.crop_name, ad.year, SUM(ad.value) AS official_value, IFNULL(SUM(fs.value),0) AS farmer_value FROM crops c LEFT JOIN agricultural_data ad ON ad.crop_id=c.id LEFT JOIN farmer_submissions fs ON fs.crop_id=c.id AND fs.year=ad.year GROUP BY c.crop_name, ad.year ORDER BY c.crop_name, ad.year";
$trendResult = $conn->query($trendQuery);
$trendValues = [];
while($row = $trendResult->fetch_assoc()){
    $trendValues[$row['crop_name']][$row['year']] = ($row['official_value']*0.7) + ($row['farmer_value']*0.3);
}
$predictions = [];
foreach($trendValues as $crop => $values){
    $data = array_values($values);
    $n = count($data);
    if($n >= 2) $predictions[$crop] = round($data[$n-1] + ($data[$n-1] - $data[$n-2]));
}

// Map Data
$mapData = [];
$mapRes = $conn->query("SELECT r.region_name, r.latitude, r.longitude, COUNT(fs.id) AS submissions FROM farmer_submissions fs JOIN regions r ON fs.region_id=r.id GROUP BY r.region_name");
while($row = $mapRes->fetch_assoc()) $mapData[] = $row;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriBridge Analytics</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
</head>
<body>
    <?php include 'nav.php'; ?>

    <h1>AgriBridge Intelligence Dashboard</h1>

    <div class="dashboard">
        <div class="cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="stat-card">
                <h3>Total Farmer Reports</h3>
                <div class="stat-number"><?php echo number_format($totalSubmissions); ?></div>
                <p>Submissions to date</p>
            </div>
            <div class="stat-card">
                <h3>Active Regions</h3>
                <div class="stat-number"><?php echo $leaderboardResult->num_rows; ?></div>
                <p>Reporting data</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card">
                <h2>AI Agricultural Insights</h2>
                <?php foreach($insights as $insight): ?>
                    <div class="insight-box"><?php echo $insight; ?></div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h2>Next Season Predictions (<?php echo date('Y') + 1; ?>)</h2>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach($predictions as $crop => $val): ?>
                        <li style="padding: 10px; border-bottom: 1px solid rgba(0,0,0,0.05);">
                            <strong><?php echo $crop; ?>:</strong> <?php echo number_format($val); ?> units (Projected)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <h2>Official Data vs. Farmer Reported Data</h2>
            <canvas id="productionChart" style="max-height: 400px;"></canvas>
        </div>

        <div class="card">
            <h2>Regional Participation Leaderboard</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>Rank</th><th>Region</th><th>Submissions</th></tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; while($row = $leaderboardResult->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $rank++; ?></td>
                                <td><?php echo $row['region_name']; ?></td>
                                <td><?php echo number_format($row['submissions']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Live Farmer Submission Map</h2>
            <div id="map" style="height: 450px;"></div>
        </div>
    </div>

    <script>
        // Chart.js - Official vs Farmer Comparison
        const ctx = document.getElementById('productionChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($cropLabels); ?>,
                datasets: [
                    {
                        label: 'Official Production',
                        data: <?php echo json_encode($officialValues); ?>,
                        backgroundColor: '#f4b400'
                    },
                    {
                        label: 'Farmer Submissions',
                        data: <?php echo json_encode($farmerValues); ?>,
                        backgroundColor: '#2c7a3f'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });

        // Leaflet Map Initialization
        const mapData = <?php echo json_encode($mapData); ?>;
        var map = L.map('map').setView([7.9465, -1.0232], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        mapData.forEach(region => {
            if(region.latitude && region.longitude){
                L.marker([region.latitude, region.longitude])
                    .addTo(map)
                    .bindPopup(`<b>${region.region_name}</b><br>Submissions: ${region.submissions}`);
            }
        });
    </script>

</body>
</html>