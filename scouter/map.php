<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive FRC Field</title>
     <!-- Load jQuery & Chart.js -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <style>
        body {
            text-align: center;
        }
        .field-container {
            position: relative;
            display: inline-block;
        }
        .button-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .button {
            position: absolute;
            width: 30px;
            height: 30px;
            background-color: rgba(0, 0, 255, 0.5);
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .button.clicked {
            background-color: rgba(255, 0, 0, 0.5);
        }
        img {
            width: 800px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="field-container">
        <img src="../images/field.png" alt="FRC Field">
        <div class="button-overlay">
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    const positions = [
                        {top: '14.5%', left: '35.5%', action: 'starting_position_1'},
                        {top: '14.5%', left: '48.0%', action: 'starting_position_2'},
                        {top: '14.5%', left: '60.5%', action: 'starting_position_3'},
                        {top: '27.00%', left: '45%', action: 'scores_coral_level_1'},
                        {top: '30.75%', left: '45.0%', action: 'scores_coral_level_2'},
                        {top: '30.75%', left: '51.0%', action: 'scores_coral_level_3'},
                        {top: '27.0%', left: '51.0%', action: 'scores_coral_level_4'},
                        {top: '48.75%', left: '55.0%', action: 'attempts_shallow_climb'},
                        {top: '48.75%', left: '62.5%', action: 'attempts_deep_climb'},
                        {top: '48.75%', left: '69.5%', action: 'attempts_parked'},
                        {top: '38.00%', left: '19.0%', action: 'scores_algae_processor'},
                        {top: '41.16%', left: '19.0%', action: 'scores_algae_processor'},
                        {top: '38.00%', left: '33.00%', action: 'picks_up_algae'},
                        {top: '38.00%', left: '62.50%', action: 'picks_up_bacteria'},
                        {top: '18.00%', left: '23.0%', action: 'crosses_starting_line'},
                        {top: '18.00%', left: '28.0%', action: 'crosses_starting_line'},
                        {top: '18.00%', left: '33.00%', action: 'crosses_starting_line'},
                        {top: '18.00%', left: '38.00%', action: 'crosses_starting_line'},
                        {top: '18.00%', left: '43.00%', action: 'crosses_starting_line'},
                        {top: '18.00%', left: '48.00%', action: 'crosses_starting_line'},
                        {top: '18.00%', left: '53.00%', action: 'crosses_starting_line'},
                        {top: '18.00%', left: '58.00%', action: 'crosses_starting_line'},
                        {top: '18.00%', left: '63.00%', action: 'crosses_starting_line'},
                        {top: '18.00%', left: '68.00%', action: 'crosses_starting_line'},
                        {top: '18.00%', left: '73.00%', action: 'crosses_starting_line'},
                        {top: '48.75%', left: '25.50%', action: 'scores_algae_net'},
                        {top: '48.75%', left: '33.00%', action: 'scores_algae_net'},
                        {top: '48.75%', left: '40.50%', action: 'scores_algae_net'},
                        {top: '58.00%', left: '47.50%', action: 'plays_defense'},
                        {top: '68.00%', left: '47.50%', action: 'attempts_to_steal'}
                    ];
                    const overlay = document.querySelector(".button-overlay");
                    positions.forEach(pos => {
                        let button = document.createElement("button");
                        button.classList.add("button");
                        button.style.top = pos.top;
                        button.style.left = pos.left;
                        button.setAttribute("data-action", pos.action);
                        button.addEventListener("click", function () {
                            this.classList.toggle("clicked");
                            console.log("Action: " + this.getAttribute("data-action"));
                        });
                        overlay.appendChild(button);
                    });
                });
            </script>
        </div>
    </div>
    <script src="../js/scouter_main.js" type="text/javascript"></script>
</body>
</html>
