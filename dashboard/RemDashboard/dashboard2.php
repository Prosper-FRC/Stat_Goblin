<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart.js Test</title>
</head>
<body>

    <!-- Canvas for Chart.js -->
    <canvas id="myChart" width="400" height="400"></canvas>

    <!-- Correctly load Chart.js from your local bundled file -->
  <!--   <script src="js/dist/chart.bundle.js"></script> -->

  <script  type="module" src="scouting/../../js/Chart.bundle.js"></script>

    <script>
        // Ensure the Chart.js object is loaded properly before using it
document.addEventListener('DOMContentLoaded', function () {
    console.log(window.Chart);  // Check if Chart is available
    if (typeof window.Chart !== "undefined") {
        var ctx = document.getElementById('myChart').getContext('2d');
        var myChart = new window.Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                datasets: [{
                    label: '# of Votes',
                    data: [12, 19, 3, 5, 2, 3],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    } else {
        console.error("Chart.js is not loaded correctly.");
    }
});
    </script>

</body>
</html>
