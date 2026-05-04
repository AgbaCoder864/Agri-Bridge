<?php
include 'db.php';

// Fetch production AND coordinates for the map
$sql = "
SELECT 
    r.region_name,
    r.latitude,
    r.longitude,
    SUM(fs.value) AS total_production,
    COUNT(fs.id) AS report_count
FROM regions r
LEFT JOIN farmer_submissions fs ON r.id = fs.region_id
GROUP BY r.id, r.region_name, r.latitude, r.longitude
HAVING total_production > 0
";

$result = $conn->query($sql);

$region_data = [];
$map_markers = [];

while($row = $result->fetch_assoc()){
    $region_data[$row['region_name']] = $row['total_production'];
    $map_markers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriBridge | Ghana Agri Map</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
</head>
<body>
    <?php include 'nav.php'; ?>

    <h1>Ghana Agricultural Intelligence Map</h1>

    <div class="dashboard">
        <div class="card">
            <h2>Regional Production Density</h2>
            <div id="ghanaMap" style="height: 500px; border-radius: 8px;"></div>
        </div>

        <div class="card">
            <h2>Production Volume by Region</h2>
            <canvas id="regionChart"></canvas>
        </div>
    </div>

    <script>
        // --- MAP LOGIC ---
        // Center of Ghana: [7.9465, -1.0232]
        var map = L.map('ghanaMap').setView([7.9465, -1.0232], 7);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        const markers = <?php echo json_encode($map_markers); ?>;
        
        markers.forEach(region => {
            if(region.latitude && region.longitude) {
                // Create a circle marker where the size depends on production
                L.circle([region.latitude, region.longitude], {
                    color: '#2c7a3f',
                    fillColor: '#2c7a3f',
                    fillOpacity: 0.5,
                    radius: Math.sqrt(region.total_production) * 100 // Scale circle to data
                }).addTo(map)
                .bindPopup(`
                    <strong>${region.region_name}</strong><br>
                    Total Production: ${Number(region.total_production).toLocaleString()} kg<br>
                    Total Reports: ${region.report_count}
                `);
            }
        });

        // --- CHART LOGIC ---
        const regionLabels = <?php echo json_encode(array_keys($region_data)); ?>;
        const regionValues = <?php echo json_encode(array_values($region_data)); ?>;

        new Chart(document.getElementById('regionChart'), {
            type: 'bar',
            data: {
                labels: regionLabels,
                datasets: [{
                    label: 'Production (kg)',
                    data: regionValues,
                    backgroundColor: '#2c7a3f',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>

</body>
</html>