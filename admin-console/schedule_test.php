<?php
require_once '../php/database_connection.php';

try {
    // Create a PDO connection using your settings
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage());
}

// Process the file upload if a CSV file is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $csvFile = $_FILES['csv_file']['tmp_name'];
    $importedData = [];
    $table = '';

    if (($handle = fopen($csvFile, "r")) !== false) {
        // Read the header row and trim whitespace
        $header = fgetcsv($handle, 1000, ",");
        $header = array_map('trim', $header);

        // Define expected headers for the two CSV formats
        $expectedHeadersActiveEvent = ['event_name', 'match_number', 'red_1', 'red_2', 'red_3', 'blue_1', 'blue_2', 'blue_3'];
        $expectedHeadersScouting = ['id', 'ip_address', 'event_name', 'match_no', 'time_sec', 'robot', 'alliance', 'action', 'location', 'result', 'points', 'timestamp'];

        if ($header === $expectedHeadersActiveEvent) {
            // File is for active_event table
            $table = 'active_event';
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $event_name   = trim($data[0]);
                $match_number = trim($data[1]);
                $red1         = trim($data[2]);
                $red2         = trim($data[3]);
                $red3         = trim($data[4]);
                $blue1        = trim($data[5]);
                $blue2        = trim($data[6]);
                $blue3        = trim($data[7]);

                // Create rows for the Red alliance
                foreach ([$red1, $red2, $red3] as $robot) {
                    if (!empty($robot)) {
                        $importedData[] = [
                            "event_name"   => $event_name,
                            "match_number" => $match_number,
                            "alliance"     => "Red",
                            "robot"        => $robot
                        ];
                    }
                }
                // Create rows for the Blue alliance
                foreach ([$blue1, $blue2, $blue3] as $robot) {
                    if (!empty($robot)) {
                        $importedData[] = [
                            "event_name"   => $event_name,
                            "match_number" => $match_number,
                            "alliance"     => "Blue",
                            "robot"        => $robot
                        ];
                    }
                }
            }
        } elseif ($header === $expectedHeadersScouting) {
            // File is for scouting_submissions table
            $table = 'scouting_submissions';
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $id         = trim($data[0]);
                $ip_address = trim($data[1]);
                $event_name = trim($data[2]);
                $match_no   = trim($data[3]);
                $time_sec   = trim($data[4]);
                $robot      = trim($data[5]);
                $alliance   = trim($data[6]);
                $action     = trim($data[7]);
                $location   = trim($data[8]);
                $result     = trim($data[9]);
                $points     = trim($data[10]);
                $timestamp  = trim($data[11]);

                $importedData[] = [
                    "id"         => $id,
                    "ip_address" => $ip_address,
                    "event_name" => $event_name,
                    "match_no"   => $match_no,
                    "time_sec"   => $time_sec,
                    "robot"      => $robot,
                    "alliance"   => $alliance,
                    "action"     => $action,
                    "location"   => $location,
                    "result"     => $result,
                    "points"     => $points,
                    "timestamp"  => $timestamp
                ];
            }
        } else {
            die("CSV format not recognized. Please ensure the CSV header matches one of the expected formats.");
        }
        fclose($handle);
    } else {
        die("Error opening CSV file.");
    }

    // Build a multi-row INSERT statement based on the detected table
    if ($table === 'active_event') {
        $values = [];
        foreach ($importedData as $row) {
            $values[] = "(" .
                $pdo->quote($row["event_name"]) . ", " .
                $pdo->quote($row["match_number"]) . ", " .
                $pdo->quote($row["alliance"]) . ", " .
                $pdo->quote($row["robot"]) .
            ")";
        }
        $sqlInsert = "INSERT INTO active_event (event_name, match_number, alliance, robot) VALUES " . implode(", ", $values);
    } elseif ($table === 'scouting_submissions') {
        $values = [];
        foreach ($importedData as $row) {
            $values[] = "(" .
                $pdo->quote($row["id"]) . ", " .
                $pdo->quote($row["ip_address"]) . ", " .
                $pdo->quote($row["event_name"]) . ", " .
                $pdo->quote($row["match_no"]) . ", " .
                $pdo->quote($row["time_sec"]) . ", " .
                $pdo->quote($row["robot"]) . ", " .
                $pdo->quote($row["alliance"]) . ", " .
                $pdo->quote($row["action"]) . ", " .
                $pdo->quote($row["location"]) . ", " .
                $pdo->quote($row["result"]) . ", " .
                $pdo->quote($row["points"]) . ", " .
                $pdo->quote($row["timestamp"]) .
            ")";
        }
        $sqlInsert = "INSERT IGNORE INTO scouting_submissions 
    (id, ip_address, event_name, match_no, time_sec, robot, alliance, action, location, result, points, timestamp) VALUES " 
    . implode(", ", $values);

    } else {
        die("Unknown table selected.");
    }

    try {
        $pdo->exec($sqlInsert);
        echo "Data imported successfully into " . $table . ".";
    } catch (PDOException $e) {
        die("Error inserting data: " . $e->getMessage());
    }
    exit; // End the script after processing the upload.
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
        
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owl Upload Schedule CSV with Drag and Drop</title>
    <style>
         @font-face {
            font-family: 'Roboto';
            src: url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf'),
            url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            @font-face {
            font-family: 'Griffy';
            src: url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf'),
            url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            @font-face {
            font-family: 'Comfortaa';
            src: url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf'),
            url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            /* Global Styles */
            body, html {
            font-family: 'Comfortaa', sans-serif;
      margin: 0;
      padding: 0;
      background: #222;
      color: #eee;
      line-height: 1.5;
      text-align: center;
    }
        .drop-zone {
            border: 2px dashed #cccccc;
            border-radius: 5px;
            padding: 40px;
            text-align: center;
            font-family: Arial, sans-serif;
            color: #cccccc;
            cursor: pointer;
            margin: 20px auto;
            width: 80%;
            max-width: 500px;
        }
        .drop-zone.dragover {
            background-color: #f0f0f0;
            border-color: #333333;
            color: #333333;
        }
   #containerOuter {
      background-color: #333;
      border-bottom: 1px solid #444;
      width: 100%;
      padding: 1rem;
      box-sizing: border-box;
    }

   #container{
    background-color: #333;
      max-width: 800px;
      margin: auto;
    }
            .logo {
      width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
    }

    </style>
</head>
<body>
  <div id="containerOuter">
    <div id="container">
      <a href=".."><img src="../images/owlupload.png" class="logo" alt="Logo"></a>
    <h2>Upload Schedule CSV</h2>
    <div class="drop-zone" id="drop-zone">
        Drag and drop your CSV file here, or click to select.
        <input type="file" id="file-input" name="csv_file" accept=".csv" style="display:none;">
    </div>
    <div id="message"></div>
</div>
</div>    
    <script>
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const messageDiv = document.getElementById('message');

        // When drop zone is clicked, trigger the hidden file input
        dropZone.addEventListener('click', () => {
            fileInput.click();
        });

        // Handle file selection from file input
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                uploadFile(fileInput.files[0]);
            }
        });

        // Prevent default behavior for drag events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        // Add visual feedback when file is dragged over the drop zone
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('dragover');
            });
        });

        // Handle drop event
        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length) {
                uploadFile(files[0]);
            }
        });

        // Upload file using Fetch API and FormData (AJAX)
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('csv_file', file);
            messageDiv.textContent = 'Uploading...';

            fetch('', { // Current page
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                messageDiv.textContent = result;
            })
            .catch(error => {
                messageDiv.textContent = 'Upload failed.';
                console.error(error);
            });
        }
    </script>
</body>
</html>

