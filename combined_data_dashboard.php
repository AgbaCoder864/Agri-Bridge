<?php
include 'db.php';

// Improved SQL: More robust grouping and clearer logic
$sql = "
SELECT 
    c.crop_name,
    r.region_name,
    ad.year,
    ad.value AS official_value,
    ad.unit,
    IFNULL(SUM(fs.value), 0) AS farmer_value
FROM agricultural_data ad
JOIN crops c ON ad.crop_id = c.id
JOIN regions r ON ad.region_id = r.id
LEFT JOIN farmer_submissions fs 
    ON fs.crop_id = ad.crop_id 
    AND fs.region_id = ad.region_id 
    AND fs.year = ad.year
GROUP BY ad.id, c.crop_name, r.region_name, ad.year, ad.value, ad.unit
ORDER BY ad.year DESC, c.crop_name ASC, r.region_name ASC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriBridge | Data Comparison</title>
    <link rel="stylesheet" href="styles.css"> </head>
<body>
    <?php include 'nav.php'; ?>

    <h1>Data Integrity Comparison</h1>

    <div class="card">
        <h2>Official vs. Farmer-Reported Production</h2>
        <p style="text-align: center; color: #666; margin-bottom: 20px;">
            Comparing MOFA/FAO official statistics against real-time farmer submissions.
        </p>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Crop</th>
                        <th>Region</th>
                        <th>Official Production</th>
                        <th>Farmer Submissions</th>
                        <th>Unit</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        // Logic to check the "Status" of data matching
                        $official = $row['official_value'];
                        $farmer = $row['farmer_value'];
                        
                        $status = "Matching";
                        $status_color = "#2c7a3f"; // Green

                        if ($farmer == 0) {
                            $status = "No Farmer Data";
                            $status_color = "#999";
                        } elseif (abs($farmer - $official) > ($official * 0.2)) {
                            $status = "High Variance";
                            $status_color = "#f4b400"; // Gold/Warning
                        }
                    ?>
                        <tr>
                            <td><?php echo $row['year']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['crop_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['region_name']); ?></td>
                            <td><?php echo number_format($official); ?></td>
                            <td><?php echo number_format($farmer); ?></td>
                            <td><?php echo htmlspecialchars($row['unit']); ?></td>
                            <td style="color: <?php echo $status_color; ?>; font-weight: bold;">
                                <?php echo $status; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7">No comparison data available.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>