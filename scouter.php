<?php
// Include the database connection file. This file likely contains the code to connect to your database.
include 'php/database_connection.php';

// Fetch distinct event names from the 'active_event' table in your database.
// This assumes you have a table named 'active_event' with a column named 'event_name'.
$eventQuery = "SELECT DISTINCT event_name FROM active_event"; 

// Prepare the SQL query for execution. This helps prevent SQL injection vulnerabilities.
$eventStmt = $pdo->prepare($eventQuery);

// Execute the prepared query.
$eventStmt->execute();

// Fetch all the results from the query and store them in an array called $events.
// PDO::FETCH_ASSOC tells PDO to return the results as an associative array (key-value pairs).
$events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FRC Scouting</title>
    <style>
    <style>
        /* Responsive design: Adjust form gap for screens narrower than 330px */
        @media (max-width: 800px) {
            form {
                gap: 8px; /* Reduces the gap between form elements for small screens */
            }
        }

        /* Styling for the body element */
       

 @font-face {
            font-family: 'Comfortaa';
            src: url('/../scouting/fonts/Comfortaa/Comfortaa-Regular.ttf') format('ttf'),
            url('/../scouting/fonts/Comfortaa/Comfortaa-Regular.ttf') format('ttf');
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
        /* Styling for the main heading (h1) */
        h1 {
            text-align: left; /* Aligns the text to the left */
            font-size: 1.2rem; /* Sets the font size */
            margin-top: -10px; /* Adjusts the top margin */
            margin-left: 12px; /* Adds left margin for positioning */
        }

        /* Styling for the form element */
        form {
            padding: 10px; /* Adds padding inside the form */
            display: flex; /* Uses flexbox layout */
            flex-direction: column; /* Arranges children in a column */
            gap: 10px; /* Adds space between form elements */
        }

        /* Styling for form labels */
        label {
            font-size: 0.9rem; /* Sets the font size */
            margin-bottom: 5px; /* Adds space below the label */
        }

        /* Styling for select elements and buttons */
        select, button {
            font-size: 0.9rem; /* Sets the font size */
            padding: 10px; /* Adds padding inside the elements */
            border-radius: 5px; /* Rounds the corners */
            width: 100%; /* Sets the width to 100% of the container */
            box-sizing: border-box; /* Includes padding and border in the element's total width and height */
        }

        /* Additional styling for buttons */
        button {
            padding: 1.5rem; /* Increases padding for larger clickable area */
            cursor: pointer; /* Changes cursor to pointer on hover */
        }

        /* Styling for the submit button */
        .submit-button {
            background-color: #FFF; /* Sets background color to white */
            color: #111; /* Sets text color to dark */
            font-size: 1rem; /* Sets font size */
            border: 1px solid #fff; /* Adds a white border */
        }

        /* Hover effect for the submit button */
        .submit-button:hover {
            background-color: #111; /* Changes background color on hover */
            color: #FFF; /* Changes text color on hover */
            border: 1px solid #fff; /* Maintains border on hover */
        }

        /* Styling for the logo image */
        .logo {
      width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
    }

        /* Styling for select elements */
        select {
            font-size: 1.1rem; /* Increases font size for better readability */
            padding: 12px; /* Adds padding for touch-friendly areas */
            border: 1px solid #fff; /* Adds a white border */
            background-color: #222; /* Sets background color to match the theme */
            color: #fff; /* Sets text color to white */
            border-radius: 5px; /* Rounds the corners */
            appearance: none; /* Removes default dropdown arrow */
            -webkit-appearance: none; /* Removes default dropdown arrow in WebKit browsers */
            -moz-appearance: none; /* Removes default dropdown arrow in Mozilla browsers */
            position: relative; /* Positions the element relative for custom styling */
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMCIgaGVpZ2h0PSI2IiB2aWV3Qm94PSIwIDAgMTAgNiI+PHBhdGggZD0iTTAgMGw1IDUgNSA1VjBIMFYwWiIgZmlsbD0iI2ZmZiIvPjwvc3ZnPg=='); /* Adds a custom dropdown arrow */
            background-repeat: no-repeat; /* Prevents the background image from repeating */
            background-position: right 10px center; /* Positions the background image */
            background-size: 10px; /* Sets the size of the background image */
            padding-right: 30px; /* Adds right padding to make space for the arrow */
        }

        /* Class to hide elements */
        .hidden {
            display: none; /* Hides the element */
        }

        /* Focus state for select elements */
        select:focus {
            outline: none; /* Removes default outline */
            border-color: #ccc; /* Changes border color on focus */
        }

            .containerOuter {
      background-color: #333;
      border-bottom: 1px solid #444;
      width: 100%;
      padding: 1rem;
      box-sizing: border-box;
    }
    .container {
      max-width: 800px;
      margin: auto;
    }

    </style>
    </style>
</head>
<body>
      <div class="containerOuter">
    <div class="container">
    <img src="images/statgoblinlogo.webp" class="logo"> 

    <form id="scoutingForm">
        <label for="eventDropdown">Event:</label> 
        <select id="eventDropdown" name="event" required> 
            <option value="">Select Event</option>
            <?php foreach ($events as $event):?> 
                <option value="<?= htmlspecialchars($event['event_name'])?>"><?= htmlspecialchars($event['event_name'])?></option>
            <?php endforeach;?>
        </select>

        <label for="matchNumberDropdown">Match Number:</label>
        <select id="matchNumberDropdown" name="match_number" required>
            <option value="">Select Match Number</option>
            </select>

        <label for="robotDropdown">Robot:</label>
        <select id="robotDropdown" name="robot" required>
            <option value="">Select Robot</option>
            </select>

        <input type="text" id="allianceDisplay" class="hidden" name="alliance" readonly>

        <button type="button" class="submit-button" id="submitForm">Submit</button> 
    </form>

    <script src="js/jquery-3.7.1.min.js"></script> 

    <script>
        $(document).ready(function() {
            // Fetch match numbers based on selected event
            $('#eventDropdown').change(function() { // When the event dropdown value changes
                var eventName = $(this).val(); // Get the selected event name
                if (eventName) { // If an event is selected
                    console.log('Selected Event:', eventName); // Log the selected event to the console
                    $.ajax({ // Make an AJAX request to php/fetch_data.php
                        type: 'POST', // Use the POST method
                        url: 'php/fetch_data.php', // Send the request to this file
                        data: { event: eventName, action: 'fetchMatches' }, // Send the event name and action to the server
                        success: function(response) { // If the request is successful
                            console.log('AJAX Response for fetchMatches:', response); // Log the response to the console
                            $('#matchNumberDropdown').html(response); // Update the match number dropdown with the response
                            $('#robotDropdown').html('<option value="">Select Robot</option>'); // Reset the robot dropdown
                            $('#allianceDisplay').val(''); // Reset the alliance display
                        },
                        error: function(xhr, status, error) { // If the request fails
                            console.error('AJAX Error in fetchMatches:', status, error); // Log the error to the console
                        }
                    });
                } else { // If no event is selected
                    $('#matchNumberDropdown').html('<option value="">Select Match Number</option>'); // Reset the match number dropdown
                    $('#robotDropdown').html('<option value="">Select Robot</option>'); // Reset the robot dropdown
                    $('#allianceDisplay').val(''); // Reset the alliance display
                }
            });

            // Fetch robots based on selected match number
            $('#matchNumberDropdown').change(function() { // When the match number dropdown value changes
                var eventName = $('#eventDropdown').val(); // Get the selected event name
                var matchNumber = $(this).val(); // Get the selected match number
                if (eventName && matchNumber) { // If both event and match number are selected
                    console.log('Selected Event:', eventName); // Log the selected event to the console
                    console.log('Selected Match Number:', matchNumber); // Log the selected match number to the console
                    $.ajax({ // Make an AJAX request to php/fetch_data.php
                        type: 'POST', // Use the POST method
                        url: 'php/fetch_data.php', // Send the request to this file
                        data: { event: eventName, match_number: matchNumber, action: 'fetchRobots' }, // Send the event name, match number, and action to the server
                        success: function(response) { // If the request is successful
                            console.log('AJAX Response for fetchRobots:', response); // Log the response to the console
                            $('#robotDropdown').html(response); // Update the robot dropdown with the response
                            $('#allianceDisplay').val(''); // Reset the alliance display
                        },
                        error: function(xhr, status, error) { // If the request fails
                            console.error('AJAX Error in fetchRobots:', status, error); // Log the error to the console
                        }
                    });
                } else { // If either event or match number is not selected
                    $('#robotDropdown').html('<option value="">Select Robot</option>'); // Reset the robot dropdown
                    $('#allianceDisplay').val(''); // Reset the alliance display
                }
            });

            // Fetch alliance based on selected robot
            $('#robotDropdown').change(function() { // When the robot dropdown value changes
                var eventName = $('#eventDropdown').val(); // Get the selected event name
                var matchNumber = $('#matchNumberDropdown').val(); // Get the selected match number
                var robot = $(this).val(); // Get the selected robot
                if (eventName && matchNumber && robot) { // If event, match number, and robot are selected
                    console.log('Selected Event:', eventName); // Log the selected event to the console
                    console.log('Selected Match Number:', matchNumber); // Log the selected match number to the console
                    console.log('Selected Robot:', robot); // Log the selected robot to the console
                    $.ajax({ // Make an AJAX request to php/fetch_data.php
                        type: 'POST', // Use the POST method
                        url: 'php/fetch_data.php', // Send the request to this file
                        data: { event: eventName, match_number: matchNumber, robot: robot, action: 'fetchAlliance' }, // Send the event name, match number, robot, and action to the server
                        success: function(response) { // If the request is successful
                            console.log('AJAX Response for fetchAlliance:', response); // Log the response to the console
                            $('#allianceDisplay').val(response); // Update the alliance display with the response

                            // Update robot dropdown background color based on alliance
                            if(response=='Red') { 
                                $('#robotDropdown').css('background-color', '#C0392B'); // Set background color to red if alliance is Red
                            } else {
                                $('#robotDropdown').css('background-color', '#2C3E50'); // Set background color to blue if alliance is Blue
                            }
                        },
                        error: function(xhr, status, error) { // If the request fails
                            console.error('AJAX Error in fetchAlliance:', status, error); // Log the error to the console
                        }
                    });
                } else { // If either event, match number, or robot is not selected
                    $('#allianceDisplay').val(''); // Reset the alliance display
                }
            });

            // Handle form submission
            $('#submitForm').click(function() { // When the submit button is clicked
                var event = $('#eventDropdown').val(); // Get the selected event name
                var matchNumber = $('#matchNumberDropdown').val(); // Get the selected match number
                var robot = $('#robotDropdown').val(); // Get the selected robot
                var alliance = $('#allianceDisplay').val(); // Get the alliance
                console.log('Form Data:', { event: event, matchNumber: matchNumber, robot: robot, alliance: alliance }); // Log the form data to the console

                if (event && matchNumber && robot && alliance) { // If all fields are filled
                    // Redirect to scouter/index.php with selected data as URL parameters
                    window.location.href = `scouter/index.php?event=${encodeURIComponent(event)}&match=${encodeURIComponent(matchNumber)}&robot=${encodeURIComponent(robot)}&alliance=${encodeURIComponent(alliance)}`; 
                } else {
                    alert('Please fill all fields.'); // Show an alert if any field is missing
                }
            });
        });
    </script>
</div>
</div>

</body>
</html>