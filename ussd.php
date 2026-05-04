<?php
// Force the output to be plain text so Africa's Talking can read it perfectly
header('Content-type: text/plain');

try {
    include 'db.php';

    // Get the data from the Africa's Talking simulator
    $phoneNumber = $_POST["phoneNumber"] ?? "";
    $text        = $_POST["text"] ?? "";

    // Clean up the input string
    $text = trim($text);
    $input = explode("*", $text);

    // This helper filters out the navigation keys (98/0) so we can track the "Level" accurately
    $clean_input = array_values(array_filter($input, function($v) { 
        return $v !== "98" && $v !== "0" && $v !== ""; 
    }));

    $level = count($clean_input);
    $response = "";

    // 1. Load the Crops and Regions from your database
    $crops = [];
    $crop_ids_order = [];
    $res = $conn->query("SELECT id, crop_name FROM crops ORDER BY crop_name ASC");
    while($row = $res->fetch_assoc()){
        $crops[$row['id']] = $row['crop_name'];
        $crop_ids_order[] = $row['id'];
    }

    $regions = [];
    $region_ids_order = [];
    $res_reg = $conn->query("SELECT id, region_name FROM regions ORDER BY region_name ASC");
    while($row = $res_reg->fetch_assoc()){
        $regions[$row['id']] = $row['region_name'];
        $region_ids_order[] = $row['id'];
    }

    // 2. Main Menu
    if($text == ""){
        $response  = "CON Welcome to AgriBridge\n";
        $response .= "1. Request Crop Data\n";
        $response .= "2. Submit Farm Data\n";
        $response .= "3. Exit";
    }
    
    // --- OPTION 1: REQUEST DATA (READING FROM DATABASE) ---
    else if($clean_input[0] == "1"){
        if($level == 1){
            $page = substr_count($text, "98"); 
            $start = $page * 5;
            $response = "CON Select Crop (" . ($start+1) . "-" . min($start+5, count($crop_ids_order)) . "):\n";
            for($i = $start; $i < $start + 5; $i++) {
                if(isset($crop_ids_order[$i])) {
                    $response .= (($i % 5) + 1) . ". " . $crops[$crop_ids_order[$i]] . "\n";
                }
            }
            if(count($crop_ids_order) > $start + 5) $response .= "98. Next Page\n";
            $response .= "0. Back";
        }
        else if($level == 2){
            $response = "CON Enter Year (e.g. 2015):";
        }
        else if($level == 3){
            $response = "CON Select Indicator:\n1. Production\n2. Area Harvested\n3. Yield";
        }
        else if($level == 4){
            $page = substr_count($text, "98");
            $actual_index = ($page * 5) + ((int)$clean_input[1] - 1);
            $crop_id = $crop_ids_order[$actual_index] ?? 0;
            $year = (int)$clean_input[2];
            $indicator_map = ["1"=>"Production", "2"=>"Area Harvested", "3"=>"Yield"];
            $indicator = $indicator_map[$clean_input[3]] ?? "Production";

            // TRIM(indicator) handles the trailing spaces found in your SQL file [cite: 1]
            $stmt = $conn->prepare("SELECT value, unit FROM agricultural_data WHERE crop_id=? AND year=? AND TRIM(indicator)=? LIMIT 1");
            $stmt->bind_param("iis", $crop_id, $year, $indicator);
            $stmt->execute();
            $res_data = $stmt->get_result();

            if($row = $res_data->fetch_assoc()){
                $response = "END $indicator for " . $crops[$crop_id] . " ($year): " . number_format($row['value']) . " " . $row['unit'];
            } else {
                $response = "END No records for " . ($crops[$crop_id] ?? 'Crop') . " in $year ($indicator).";
            }
        }
    }

    // --- OPTION 2: SUBMIT FARM DATA (WRITING TO DATABASE) ---
    else if($clean_input[0] == "2"){
        if($level == 1){
            $page = substr_count($text, "98");
            $start = $page * 5;
            $response = "CON Select Crop to Report:\n";
            for($i = $start; $i < $start + 5; $i++) {
                if(isset($crop_ids_order[$i])) {
                    $response .= (($i % 5) + 1) . ". " . $crops[$crop_ids_order[$i]] . "\n";
                }
            }
            if(count($crop_ids_order) > $start + 5) $response .= "98. Next Page";
        }
        else if($level == 2){
            $response = "CON Select Reporting Type:\n1. Production (kg)\n2. Yield (kg/ha)";
        }
        else if($level == 3){
            $page = substr_count($text, "98");
            $crop_id = $crop_ids_order[($page * 5) + ((int)$clean_input[1] - 1)];
            $response = "CON [" . $crops[$crop_id] . "] Select Region:\n";
            foreach($region_ids_order as $index => $id) {
                $response .= ($index + 1) . ". " . $regions[$id] . "\n";
            }
        }
        else if($level == 4){
            $type = ($clean_input[2] == "1") ? "Production" : "Yield";
            $response = "CON Enter $type Amount (kg):";
        }
        else if($level == 5){
            $page = substr_count($text, "98");
            $crop_id = $crop_ids_order[($page * 5) + ((int)$clean_input[1] - 1)];
            $type_choice = $clean_input[2];
            $region_id = $region_ids_order[(int)$clean_input[3]-1];
            $raw_value = (float)$clean_input[4];
            $year = date("Y");

            // Convert farmer units (kg) to official units (tonnes/100g/ha)
            if($type_choice == "1"){
                $indicator = "Production";
                $final_value = $raw_value / 1000; // kg to Tonnes
                $unit = "t";
            } else {
                $indicator = "Yield";
                $final_value = $raw_value * 10; // kg/ha to 100g/ha
                $unit = "100g/ha";
            }

            if(is_numeric($raw_value) && $crop_id && $region_id){
                $stmt = $conn->prepare("INSERT INTO farmer_submissions (farmer_phone, crop_id, region_id, year, indicator, value, unit) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siiisds", $phoneNumber, $crop_id, $region_id, $year, $indicator, $final_value, $unit);
                $stmt->execute();
                
                $response = "END Success! Recorded " . number_format($final_value, 2) . " $unit for " . $crops[$crop_id];
            } else {
                $response = "END Error: Invalid input.";
            }
        }
    }
    else if($clean_input[0] == "3"){
        $response = "END Thank you for using AgriBridge.";
    }

    // Output the final response to the simulator
    echo rtrim($response, "\n");

} catch (Exception $e) {
    echo "END Error: " . $e->getMessage();
}
?>