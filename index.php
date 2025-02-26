<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Console</title>
    <style>
@font-face {
            font-family: 'Roboto';
            src: url('/../Stat_Goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf'),
            url('/../Stat_Goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            @font-face {
            font-family: 'Griffy';
            src: url('/../Stat_Goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf'),
            url('/../Stat_Goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            @font-face {
            font-family: 'Comfortaa';
            src: url('/../Stat_Goblin/fonts/Comfortaa/Comfortaa-Regular.ttf') format('ttf'),
            url('/../Stat_Goblin/fonts/Comfortaa/Comfortaa-Regular.ttf') format('ttf');
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


    /* Grid layout for dropdowns: 2 per row */
    .grid-container {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      grid-gap: 1rem;
      margin-bottom: 1rem;
    }
    .grid-item {
      display: flex;
      flex-direction: column;
    }
    .grid-item label {
      margin-bottom: 0.3rem;
      font-weight: bold;
      font-size: 1rem;
    }



        .logo {
      width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
    }
    .icon{width:80px}
  </style>

  </head>

<body>
  <div class="containerOuter">
    <div class="container">
      <img src="images/theStatOwl.png" class="logo" alt="Logo">
      
      <!-- 2x2 Grid for Dropdowns -->
      <div class="grid-container">
        <div class="grid-item">
          <label for="eventDropdown"><strong>The Scout Owl:</strong></label>
         <a href ="scouter.php"><img class="icon" src="icons/scouter.png" alt="scouter app"></a>
        </div>


        <div class="grid-item">
          <label for="eventDropdown"><strong>Owl Admin Console:</strong></label>
          

 <a href ="admin-console/"><img class="icon" src="icons/admin.png" alt="Admin Console"></a>


        </div>

                <div class="grid-item">
          <label for="eventDropdown"><strong>Owl Analytics:</strong></label>
         

          <a href ="dashboard/matchstats.php"><img class="icon" src="icons/analytics.png" alt="scouter app"></a>

        </div>

                <div class="grid-item">
          <label for="eventDropdown"><strong>Upload Schedule:</strong></label>
          
 <a href ="admin-console/schedule.php"><img class="icon" src="icons/upload.png" alt="scouter app"></a>



        </div> 


      </div>
    </div>
  </div>
</body>
</html>
