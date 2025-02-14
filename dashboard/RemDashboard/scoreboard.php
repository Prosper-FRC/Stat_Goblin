<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FRC Scoreboard</title>
    <style>
        /* Reset default styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        

        /* Main container */
        .container {
            display: flex;
            flex-direction: column;
            width: 100vw;
  
            height: 100vh;
            margin: auto;
            border: 2px solid rgba(0, 0, 0, 0.3);
        }

        /* Top section with 5 equal divs */
        .top {
            display: flex;
            width: 100%;
            height: 20%;
        }

        .top div {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1rem;
            font-weight: bold;
            color: white;
            border: 2px solid rgba(0, 0, 0, 0.3);
        }

        /* Assigning background colors */
        #blue_score { background: #4A90E2; }
        #blue_team { background: #5B9BD5; flex-direction: column; display: flex; align-items: center; }
        #score_and_timer { background: #F4A261; }
        #red_team { background: #E74C3C; flex-direction: column; display: flex; align-items: center; }
        #red_score { background: #C0392B; }

        /* Robot team div styles */
        .robot {
            width: 90%;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            margin: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
        }

        /* Bottom section */
        .bottom {
            display: flex;
            flex-direction: column;
            flex: 1;
            width: 100%;
        }

        /* Last actions div */
        #last_actions {
            background: #2C3E50;
            height: 25%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2rem;
            color: white;
            border: 2px solid rgba(0, 0, 0, 0.3);
        }

        /* Charts section */
        #charts {
            background: #27AE60;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 2px solid rgba(0, 0, 0, 0.3);
            padding: 10px;
        }

        /* Prediction div */
        #prediction {
            background: #D4AC0D;
            width: 100%;
            height: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2rem;
            color: white;
            border: 2px solid rgba(0, 0, 0, 0.3);
        }

        /* Chart grid */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            width: 100%;
            padding: 10px;
        }

        .chart-grid canvas {
            background: #fff;
            border: 2px solid rgba(0, 0, 0, 0.3);
            width: 100%;
            height: 200px;
        }
        .big {
    font-size: 5rem !important;
                }
    </style>
</head>
<body>

    <div class="container">
        <!-- Top Section -->
        <div class="top">
            <div id="blue_score" class="big">777</div>
            <div id="blue_team">
                <div class="robot">
                    <h3 id="blue_robot_1_name">Blue Robot 1</h3>
                    <span id="blue_robot_1_score">0</span>
                </div>
                <div class="robot">
                    <h3 id="blue_robot_2_name">Blue Robot 2</h3>
                    <span id="blue_robot_2_score">0</span>
                </div>
                <div class="robot">
                    <h3 id="blue_robot_3_name">Blue Robot 3</h3>
                    <span id="blue_robot_3_score">0</span>
                </div>
            </div>
            <div id="score_and_timer" class="big">150</div>
            <div id="red_team">
                <div class="robot">
                    <h3 id="red_robot_1_name">Red Robot 1</h3>
                    <span id="red_robot_1_score">0</span>
                </div>
                <div class="robot">
                    <h3 id="red_robot_2_name">Red Robot 2</h3>
                    <span id="red_robot_2_score">0</span>
                </div>
                <div class="robot">
                    <h3 id="red_robot_3_name">Red Robot 3</h3>
                    <span id="red_robot_3_score">0</span>
                </div>
            </div>
            <div id="red_score" class="big">888</div>
        </div>

        <!-- Bottom Section -->
        <div class="bottom">
            <div id="last_actions">Last Actions</div>
            <div id="charts">
                <div id="prediction">Prediction</div>
                <div class="chart-grid">
                    <canvas id="blue_1"></canvas>
                    <canvas id="blue_2"></canvas>
                    <canvas id="blue_3"></canvas>
                    <canvas id="red_1"></canvas>
                    <canvas id="red_2"></canvas>
                    <canvas id="red_3"></canvas>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
