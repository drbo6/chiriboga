<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Netrunner: CH1R180G4</title>
  <link href="images/favicon.ico" rel="icon">
  <link rel="manifest" href="manifest.json">
  <?php include 'cardrenderer/webfont.php'; ?>
  <style>
    * {
      font-family: "Lucida Console", Monaco, monospace;
      color: #33ff33;
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background: #0a0a0a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .terminal-frame {
      background: linear-gradient(145deg, #2a2a2a 0%, #1a1a1a 50%, #0d0d0d 100%);
      padding: 30px;
      border-radius: 20px;
      box-shadow: 
        inset 0 2px 10px rgba(255,255,255,0.1),
        0 10px 40px rgba(0,0,0,0.8),
        0 0 0 2px #333;
      position: relative;
    }

    .terminal-frame::before {
      content: "● ● ●";
      position: absolute;
      top: 10px;
      left: 20px;
      font-size: 10px;
      letter-spacing: 4px;
      color: #444;
    }

    .screen {
      background: #0a100a;
      border-radius: 10px;
      padding: 40px 60px;
      position: relative;
      overflow: hidden;
      box-shadow: 
        inset 0 0 100px rgba(0,50,0,0.3),
        inset 0 0 20px rgba(0,0,0,0.8);
    }

    /* CRT curvature effect */
    .screen::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(ellipse at center, transparent 0%, rgba(0,0,0,0.3) 90%, rgba(0,0,0,0.6) 100%);
      pointer-events: none;
      z-index: 10;
    }

    /* Scanlines */
    .screen::after {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: repeating-linear-gradient(
        0deg,
        rgba(0, 0, 0, 0.2),
        rgba(0, 0, 0, 0.2) 1px,
        transparent 1px,
        transparent 3px
      );
      pointer-events: none;
      z-index: 11;
      animation: scanlines 10s linear infinite;
    }

    @keyframes scanlines {
      0% { background-position: 0 0; }
      100% { background-position: 0 100px; }
    }

    /* Screen flicker */
    .screen-content {
      animation: flicker 0.1s infinite;
      position: relative;
      z-index: 5;
    }

    @keyframes flicker {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.98; }
      25%, 75% { opacity: 0.99; }
    }

    /* Phosphor glow */
    .glow-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(ellipse at center, rgba(50,255,50,0.03) 0%, transparent 70%);
      pointer-events: none;
      z-index: 6;
    }

    .game-title {
      margin-bottom: 10px;
      position: relative;
    }

    .title-line {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 15px;
      margin-bottom: 8px;
    }

    .title-line::before,
    .title-line::after {
      content: "════";
      color: #33ff33;
      opacity: 0.5;
      font-size: 14px;
    }

    .game-title h1 {
      font-size: 48px;
      color: #33ff33;
      text-shadow: 
        0 0 5px #33ff33,
        0 0 10px #33ff33,
        0 0 20px #33ff33,
        0 0 40px #00aa00;
      letter-spacing: 10px;
      position: relative;
      display: inline-block;
    }

    .game-title h1::before {
      content: "NETRUNNER";
      position: absolute;
      left: 2px;
      top: 0;
      color: #ffffff;
      opacity: 0.8;
      clip-path: inset(0 0 50% 0);
      animation: glitchTop 4s infinite linear;
    }

    .game-title h1::after {
      content: "NETRUNNER";
      position: absolute;
      left: -2px;
      top: 0;
      color: #33ffff;
      opacity: 0.8;
      clip-path: inset(50% 0 0 0);
      animation: glitchBottom 4s infinite linear;
    }

    @keyframes glitchTop {
      0%, 87%, 100% { transform: translate(0); opacity: 0; }
      88% { transform: translate(-3px, 1px); opacity: 0.8; }
      90% { transform: translate(3px, -1px); opacity: 0.8; }
      92% { transform: translate(-2px, 0); opacity: 0.8; }
      93% { transform: translate(0); opacity: 0; }
    }

    @keyframes glitchBottom {
      0%, 87%, 100% { transform: translate(0); opacity: 0; }
      88% { transform: translate(2px, 1px); opacity: 0.8; }
      89% { transform: translate(-3px, 0); opacity: 0.8; }
      91% { transform: translate(2px, -1px); opacity: 0.8; }
      93% { transform: translate(0); opacity: 0; }
    }

    .subtitle-container {
      position: relative;
      margin: 15px 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .game-title h2 {
      font-size: 32px;
      color: #33ff33;
      letter-spacing: 12px;
      text-shadow: 
        0 0 5px #33ff33,
        0 0 10px #33ff33;
      position: relative;
      display: inline-block;
    }

    .game-title h2::before {
      content: "CH1R180G4";
      position: absolute;
      left: 2px;
      top: 0;
      color: #ffffff;
      opacity: 0;
      clip-path: inset(0 0 50% 0);
    }

    .game-title h2::after {
      content: "CH1R180G4";
      position: absolute;
      left: -2px;
      top: 0;
      color: #33ffff;
      opacity: 0;
      clip-path: inset(50% 0 0 0);
    }

    .game-title h2.glitch::before {
      animation: glitchH2Top 0.3s steps(2) forwards;
    }

    .game-title h2.glitch::after {
      animation: glitchH2Bottom 0.3s steps(2) forwards;
    }

    @keyframes glitchH2Top {
      0% { transform: translate(0); opacity: 0; }
      20% { transform: translate(-4px, 2px); opacity: 0.9; }
      40% { transform: translate(4px, -1px); opacity: 0.8; }
      60% { transform: translate(-2px, 1px); opacity: 0.9; }
      80% { transform: translate(3px, -2px); opacity: 0.7; }
      100% { transform: translate(0); opacity: 0; }
    }

    @keyframes glitchH2Bottom {
      0% { transform: translate(0); opacity: 0; }
      20% { transform: translate(3px, -1px); opacity: 0.8; }
      40% { transform: translate(-3px, 2px); opacity: 0.9; }
      60% { transform: translate(2px, -2px); opacity: 0.7; }
      80% { transform: translate(-4px, 1px); opacity: 0.9; }
      100% { transform: translate(0); opacity: 0; }
    }

    .bracket {
      color: #33ff33;
      font-size: 24px;
      opacity: 0.6;
      vertical-align: middle;
    }

    .bracket.left { margin-right: 10px; }
    .bracket.right { margin-left: 10px; }

    .hex-decoration {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-top: 12px;
    }

    .hex {
      width: 6px;
      height: 6px;
      background: #33ff33;
      transform: rotate(45deg);
      opacity: 0.6;
      box-shadow: 0 0 5px #33ff33;
      animation: hexPulse 2s ease-in-out infinite;
    }

    .hex:nth-child(2) { animation-delay: 0.2s; opacity: 0.8; }
    .hex:nth-child(3) { animation-delay: 0.4s; opacity: 1; }
    .hex:nth-child(4) { animation-delay: 0.2s; opacity: 0.8; }
    .hex:nth-child(5) { animation-delay: 0s; opacity: 0.6; }

    @keyframes hexPulse {
      0%, 100% { transform: rotate(45deg) scale(1); }
      50% { transform: rotate(45deg) scale(1.3); }
    }

    .system-text {
      font-size: 10px;
      color: #33ff33;
      letter-spacing: 3px;
      margin-bottom: 30px;
      opacity: 0.7;
      text-align: center;
    }

    .cursor {
      display: inline-block;
      width: 8px;
      height: 14px;
      background: #33ff33;
      margin-left: 4px;
      animation: blink 1s step-end infinite;
      vertical-align: middle;
      box-shadow: 0 0 5px #33ff33;
    }

    @keyframes blink {
      0%, 50% { opacity: 1; }
      51%, 100% { opacity: 0; }
    }

    .menu-items {
      display: flex;
      flex-direction: column;
      gap: 4px;
      padding: 15px;
      margin-top: 20px;
    }

    /* Quick Game Preview */
    .menu-layout {
      display: flex;
      align-items: stretch;
      gap: 15px;
    }

    .menu-buttons {
      display: flex;
      flex-direction: column;
      gap: 4px;
      flex: 1;
      border: 1px solid #33ff3344;
      padding: 15px;
      justify-content: center;
    }

    .match-preview {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 4px;
      border: 1px solid #33ff3344;
      padding: 15px;
    }

    .preview-title {
      font-size: 10px;
      color: #33ff33;
      letter-spacing: 3px;
      opacity: 0.7;
      margin-bottom: 8px;
    }

    .portrait-label {
      position: absolute;
      bottom: 3px;
      right: 3px;
      font-size: 8px;
      color: #33ff33;
      letter-spacing: 1px;
      background: rgba(0, 0, 0, 0.8);
      padding: 2px 4px;
      z-index: 5;
      border: 1px solid #33ff3366;
    }

    .portrait-name {
      position: absolute;
      top: 3px;
      left: 3px;
      font-size: 7px;
      color: #33ff33;
      letter-spacing: 0.5px;
      background: rgba(0, 0, 0, 0.8);
      padding: 2px 4px;
      z-index: 5;
      border: 1px solid #33ff3366;
      width: min-content;
      line-height: 1.3;
    }

    .portrait-container {
      width: 80px;
      height: 80px;
      position: relative;
      overflow: hidden;
      border: 1px solid #33ff33;
      box-shadow: 
        0 0 8px #33ff3366,
        inset 0 0 15px rgba(0,0,0,0.8);
    }

    .portrait-container::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(
        rgba(0, 50, 0, 0.3),
        rgba(0, 50, 0, 0.3)
      );
      mix-blend-mode: multiply;
      z-index: 2;
      pointer-events: none;
    }

    .portrait-container::after {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: repeating-linear-gradient(
        0deg,
        rgba(0, 0, 0, 0.15),
        rgba(0, 0, 0, 0.15) 1px,
        transparent 1px,
        transparent 3px
      );
      pointer-events: none;
      z-index: 3;
    }

    .portrait {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center 20%;
      filter: sepia(100%) saturate(300%) brightness(0.8) hue-rotate(70deg);
    }

    .portrait-glow {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      box-shadow: inset 0 0 30px rgba(51, 255, 51, 0.3);
      z-index: 4;
      pointer-events: none;
    }

    .vs-text {
      font-size: 20px;
      color: #33ff33;
      text-shadow: 
        0 0 5px #33ff33,
        0 0 10px #33ff33;
      letter-spacing: 1px;
    }

    @keyframes vsPulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.7; transform: scale(1.05); }
    }

    .preview-label {
      font-size: 10px;
      color: #33ff33;
      letter-spacing: 2px;
      opacity: 0.7;
      margin-top: 5px;
      text-align: center;
    }

    .portrait-wrapper {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .menu-item {
      color: #33ff33;
      padding: 12px 30px;
      font-size: 16px;
      letter-spacing: 3px;
      cursor: pointer;
      transition: all 0.1s ease;
      -webkit-user-select: none;
      user-select: none;
      position: relative;
      text-align: left;
    }

    .menu-item::before {
      content: ">";
      position: absolute;
      left: 10px;
      opacity: 0;
      color: #33ff33;
      transition: all 0.1s ease;
    }

    .menu-item:hover {
      background-color: #33ff33;
      color: #0a100a;
      text-shadow: none;
    }

    .menu-item:hover::before {
      opacity: 1;
      color: #0a100a;
    }

    .menu-item:active {
      background-color: #66ff66;
    }

    .achievement-percent {
      color: #ff6633;
      text-shadow: 0 0 5px #ff6633;
    }

    .menu-item:hover .achievement-percent,
    .menu-item:active .achievement-percent {
      color: #0a100a;
      text-shadow: none;
    }

    .version {
      margin-top: 25px;
      font-size: 10px;
      color: #33ff33;
      opacity: 0.5;
      letter-spacing: 2px;
      text-align: center;
    }

    .status-bar {
      display: flex;
      justify-content: space-between;
      margin-top: 20px;
      padding-top: 15px;
      border-top: 1px solid #33ff3333;
      font-size: 9px;
      opacity: 0.6;
    }

    .status-item {
      color: #33ff33;
      letter-spacing: 1px;
    }

    /* Random screen noise */
    .noise {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%' height='100%' filter='url(%23noise)'/%3E%3C/svg%3E");
      opacity: 0.03;
      pointer-events: none;
      z-index: 7;
    }
  </style>
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
              <div class="preview-title">INCOMING</div>
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

        <div class="version">BUILD 0.1.0-ALPHA // 2077.11.29</div>

        <div class="status-bar">
          <span class="status-item">MEM: 64TB FREE</span>
          <span class="status-item">NET: CONNECTED</span>
          <span class="status-item">ICE: NOMINAL</span>
        </div>
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
