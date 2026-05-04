<?php
include 'db.php';

// Fetch crops and regions for filters
$crops_res = $conn->query("SELECT id, crop_name FROM crops ORDER BY crop_name");
$regions_res = $conn->query("SELECT id, region_name FROM regions ORDER BY region_name");

// Get filter values from form submission (default = empty)
$selected_crop = $_GET['crop'] ?? '';
$selected_year = $_GET['year'] ?? '';
$selected_indicator = $_GET['indicator'] ?? '';
$selected_region = $_GET['region'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriBridge Intelligence Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'nav.php'; ?>

    <h1>AgriBridge Intelligence Dashboard</h1>

    <div class="card">
        <form method="get" action="index.php">
            <div class="filter-group">
                <label>Crop:</label>
                <select name="crop">
                    <option value="">All Crops</option>
                    <?php while($row = $crops_res->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>" <?php if($selected_crop == $row['id']) echo "selected"; ?>>
                            <?php echo htmlspecialchars($row['crop_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Year:</label>
                <input type="number" name="year" placeholder="e.g. 2024" value="<?php echo htmlspecialchars($selected_year); ?>">
            </div>

            <div class="filter-group">
                <label>Indicator:</label>
                <select name="indicator">
                    <option value="">All Indicators</option>
                    <option value="Production" <?php if($selected_indicator == "Production") echo "selected"; ?>>Production</option>
                    <option value="Area Harvested" <?php if($selected_indicator == "Area Harvested") echo "selected"; ?>>Area Harvested</option>
                    <option value="Yield" <?php if($selected_indicator == "Yield") echo "selected"; ?>>Yield</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Region:</label>
                <select name="region">
                    <option value="">All Regions</option>
                    <?php 
                    $regions_res->data_seek(0); // reset pointer
                    while($row = $regions_res->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>" <?php if($selected_region == $row['id']) echo "selected"; ?>>
                            <?php echo htmlspecialchars($row['region_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <input type="submit" value="Apply Filters" class="filter-btn">
        </form>
    </div>

    <?php
    // --- 1. National-Level Data Query ---
    $national_sql = "SELECT ad.year, c.crop_name, ad.indicator, ad.value, ad.unit, ad.source 
                     FROM agricultural_data ad
                     JOIN crops c ON ad.crop_id = c.id
                     WHERE ad.region_id IS NULL";

    if($selected_crop) $national_sql .= " AND ad.crop_id = " . (int)$selected_crop;
    if($selected_year) $national_sql .= " AND ad.year = " . (int)$selected_year;
    if($selected_indicator) $national_sql .= " AND ad.indicator = '" . $conn->real_escape_string($selected_indicator) . "'";

    $national_res = $conn->query($national_sql . " ORDER BY ad.year DESC, c.crop_name LIMIT 100");

    // --- 2. Top Regions Production Query ---
    $regions_sql = "SELECT tr.year, c.crop_name, r.region_name, tr.production, tr.unit, tr.rank_position
                    FROM top_regions_production tr
                    JOIN crops c ON tr.crop_id = c.id
                    JOIN regions r ON tr.region_id = r.id
                    WHERE 1";

    if($selected_crop) $regions_sql .= " AND tr.crop_id = " . (int)$selected_crop;
    if($selected_year) $regions_sql .= " AND tr.year = " . (int)$selected_year;
    if($selected_region) $regions_sql .= " AND tr.region_id = " . (int)$selected_region;

    $regions_res = $conn->query($regions_sql . " ORDER BY tr.year DESC, tr.rank_position ASC");

    // --- 3. Farmer Submissions Query ---
    $farmer_sql = "SELECT fs.created_at, c.crop_name, IFNULL(r.region_name,'N/A') as region_name, fs.indicator, fs.value, fs.unit 
                   FROM farmer_submissions fs
                   JOIN crops c ON fs.crop_id = c.id
                   LEFT JOIN regions r ON fs.region_id = r.id
                   WHERE 1";

    if($selected_crop) $farmer_sql .= " AND fs.crop_id = " . (int)$selected_crop;
    if($selected_region) $farmer_sql .= " AND fs.region_id = " . (int)$selected_region;

    $farmer_res = $conn->query($farmer_sql . " ORDER BY fs.created_at DESC");
    ?>

    <div class="card">
        <h2>National Data (MOFA & FAO)</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Year</th><th>Crop</th><th>Indicator</th><th>Value</th><th>Unit</th><th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($national_res && $national_res->num_rows > 0): ?>
                        <?php while($row = $national_res->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['year']; ?></td>
                                <td><?php echo htmlspecialchars($row['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['indicator']); ?></td>
                                <td><?php echo number_format($row['value']); ?></td>
                                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                <td><?php echo htmlspecialchars($row['source']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No national data found matching filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Top Regions Production (MOFA)</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Year</th><th>Crop</th><th>Region</th><th>Production</th><th>Unit</th><th>Rank</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($regions_res && $regions_res->num_rows > 0): ?>
                        <?php while($row = $regions_res->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['year']; ?></td>
                                <td><?php echo htmlspecialchars($row['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['region_name']); ?></td>
                                <td><?php echo number_format($row['production']); ?></td>
                                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                <td><strong>#<?php echo $row['rank_position']; ?></strong></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No regional rankings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Recent Farmer Submissions</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th><th>Crop</th><th>Region</th><th>Indicator</th><th>Value</th><th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($farmer_res && $farmer_res->num_rows > 0): ?>
                        <?php while($row = $farmer_res->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($row['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['region_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['indicator']); ?></td>
                                <td><?php echo number_format($row['value']); ?></td>
                                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No farmer submissions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>