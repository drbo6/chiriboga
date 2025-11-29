<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Netrunner: CH1R180G4</title>
  <link href="images/favicon.ico" rel="icon">
  <link rel="manifest" href="manifest.json">
  <link rel="stylesheet" href="style.css" />
  <?php include 'cardrenderer/webfont.php'; ?>
</head>
<body>
  <div class="terminal-frame">
    <div class="screen">
      <div class="glow-overlay"></div>
      <div class="noise"></div>
      
      <div class="screen-content">
        <div class="system-text">
          CH1R180G4 SYSTEMS v2.71 // NEURAL INTERFACE READY<span class="cursor"></span>
        </div>

        <div class="game-title">
          <div class="title-stack">
            <div class="title-line">
              <h1>NETRUNNER</h1>
            </div>
            <div class="subtitle-container">
              <span class="bracket left">[</span><h2>CH1R180G4</h2><span class="bracket right">]</span>
            </div>
            <div class="hex-decoration">
              <div class="hex"></div>
              <div class="hex"></div>
              <div class="hex"></div>
              <div class="hex"></div>
              <div class="hex"></div>
            </div>
          </div>
          <div class="spacer-grow"></div>
          <div class="meta-stack">
            <div class="version">BUILD 0.1.0-ALPHA // 2077.11.29</div>
            <div class="status-bar">
              <span class="status-item">MEM: 64TB FREE</span>
              <span class="status-item">NET: CONNECTED</span>
              <span class="status-item">ICE: NOMINAL</span>
            </div>
          </div>
        </div>

        <div class="menu-items">
          <div class="menu-layout">
            <div class="menu-buttons">
              <div class="menu-item" onclick="handleMenu('quick')">QUICK GAME</div>
              <div class="menu-item" onclick="handleMenu('custom')">CUSTOM GAME</div>
              <div class="menu-item" onclick="handleMenu('tournament')">TOURNAMENT</div>
              <div class="menu-item" onclick="handleMenu('tutorial')">TUTORIAL</div>
              <div class="menu-item" onclick="handleMenu('achievements')">ACHIEVEMENTS <span class="achievement-percent">[0%]</span></div>
              <div class="menu-item" onclick="handleMenu('settings')">SETTINGS</div>
            </div>
            
            <div class="match-preview">
              <div class="preview-title">QUICK GAME<br />INCOMING..</div>
              <div class="portrait-container">
                <img class="portrait" src="https://chiriboga-nsg.cronbach.com/images/30001.jpg" alt="">
                <div class="portrait-glow"></div>
                <div class="portrait-name">Loup</div>
                <div class="portrait-label">YOU</div>
              </div>
              
              <div class="vs-text">VS</div>
              
              <div class="portrait-container">
                <img class="portrait" src="https://chiriboga-nsg.cronbach.com/images/30035.jpg" alt="">
                <div class="portrait-glow"></div>
                <div class="portrait-name">Precision Design</div>
                <div class="portrait-glow"></div>
                <div class="portrait-label">CPU</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Version & status moved into .game-title for small-height layout -->
      </div>
    </div>
  </div>

  <script>
    function handleMenu(option) {
      const item = event.target.closest('.menu-item');
      item.innerHTML = 'LOADING...';
      
      setTimeout(() => {
        const labels = {
          quick: 'QUICK GAME',
          custom: 'CUSTOM GAME',
          tournament: 'TOURNAMENT',
          tutorial: 'TUTORIAL',
          achievements: 'ACHIEVEMENTS <span class="achievement-percent">[0%]</span>',
          settings: 'SETTINGS'
        };
        item.innerHTML = labels[option];
        
        // Navigate to custom game after showing loading
        if (option === 'custom') {
          window.location.href = 'decklauncher.php?sets=systemgateway-systemupdate2021&p=r&r=random';
        }
      }, 500);

      console.log(`> EXECUTING ${option.toUpperCase()}.EXE`);
    }

    // Random glitch for CHIRIBOGA
    const chiriboga = document.querySelector('.game-title h2');
    
    function triggerGlitch() {
      chiriboga.classList.add('glitch');
      
      setTimeout(() => {
        chiriboga.classList.remove('glitch');
      }, 300);
      
      // Schedule next glitch at random interval (1-6 seconds)
      const nextDelay = 1000 + Math.random() * 5000;
      setTimeout(triggerGlitch, nextDelay);
    }
    
    // Start the random glitch cycle after initial delay
    setTimeout(triggerGlitch, 2000);
  </script>
</body>
</html>
