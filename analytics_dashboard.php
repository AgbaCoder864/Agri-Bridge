<?php
include 'db.php';

// SQL Fix: Added a subquery to prevent "Multiplication Error" 
// (If one year has 5 farmer reports, it could multiply the official value by 5 without this)
$sql = "
SELECT 
    c.crop_name,
    ad.year,
    SUM(ad.value) AS official_total,
    (SELECT IFNULL(SUM(fs.value), 0) 
     FROM farmer_submissions fs 
     WHERE fs.crop_id = ad.crop_id AND fs.year = ad.year) AS farmer_total
FROM agricultural_data ad
JOIN crops c ON ad.crop_id = c.id
GROUP BY c.crop_name, ad.year
ORDER BY ad.year DESC, c.crop_name ASC
";

$result = $conn->query($sql);

$labels = [];
$official = [];
$farmer = [];

if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $labels[] = $row['crop_name'] . " " . $row['year'];
        $official[] = (float)$row['official_total'];
        $farmer[] = (float)$row['farmer_total'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriBridge | Analytics</title>
    <link rel="stylesheet" href="styles.css"> <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'nav.php'; ?>

    <h1>AgriBridge Intelligence Analytics</h1>

    <div class="card">
        <h2>Production Comparison: Official vs. Farmer</h2>
        <div class="chart-container" style="position: relative; height:40vh; width:100%">
            <canvas id="productionChart"></canvas>
        </div>
    </div>

    <div class="card">
        <h2>Data Breakdown Table</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Crop & Year</th>
                        <th>Official Production (Units)</th>
                        <th>Farmer Submissions (Units)</th>
                        <th>Data Gap</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for($i=0; $i < count($labels); $i++): 
                        $diff = $official[$i] - $farmer[$i];
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($labels[$i]); ?></strong></td>
                            <td><?php echo number_format($official[$i]); ?></td>
                            <td><?php echo number_format($farmer[$i]); ?></td>
                            <td style="color: <?php echo ($diff > 0) ? '#e74c3c' : '#2c7a3f'; ?>;">
                                <?php echo number_format($diff); ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('productionChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'Official Production',
                        data: <?php echo json_encode($official); ?>,
                        backgroundColor: '#f4b400', // AgriBridge Gold
                        borderRadius: 5
                    },
                    {
                        label: 'Farmer Submissions',
                        data: <?php echo json_encode($farmer); ?>,
                        backgroundColor: '#2c7a3f', // AgriBridge Green
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: { callback: function(value) { return value.toLocaleString(); } }
                    }
                }
            }
        });
    </script>

</body>
</html>