<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Netrunner: Solo Mode</title>
  <link href="images/favicon.ico" rel="icon">
  <link rel="manifest" href="manifest.json">
  <link rel="stylesheet" href="style.css" />
  <?php include 'cardrenderer/webfont.php'; ?>
  <script src="deck/lz-string.min.js"></script>
  <script>
    var cardSet = []; // prepare to receive card definitions
    var setIdentifiers = []; // set identifiers
    var runner = {};
    var corp = {};
    var preconDecks = [];
    function registerPrecon(deck) {
      preconDecks.push(deck);
    }
  </script>
  <?php
  // Load card sets
  echo '<script src="utility.js?' . filemtime('utility.js') . '"></script>';
  $sets = ["systemgateway","systemupdate2021","midnightsun"];
  foreach ($sets as $set) {
    echo '<script src="sets/'.$set.'.js?' . filemtime('sets/'.$set.'.js') . '"></script>';
  }
  
  // Load preconstructed decks
  $preconDir = 'precons';
  if (is_dir($preconDir)) {
    $preconFiles = glob($preconDir . '/*.js');
    foreach ($preconFiles as $preconFile) {
      echo '<script src="' . $preconFile . '?' . filemtime($preconFile) . '"></script>';
    }
  }
  // Determine user IP (respect X-Forwarded-For if present)
  $ip = '';
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($parts[0]);
  } else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  }
  ?>
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
              <span class="bracket left">[</span><h2>$0LØ MOÐ3</h2><span class="bracket right">]</span>
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
          <div class="meta-stack landscape-only">
            <div class="version">BUILD 0.1.0-ALPHA // 2077.<?php echo date('m.d'); ?></div>
            <div class="status-bar">
              <span class="status-item" style="cursor:pointer;" onclick="openCredits()">CREDITS</span>
              <span class="status-item" id="threat-level">THREAT LEVEL: <span id="threat-color">1</span></span>
              <span class="status-item">IP: <?php echo htmlspecialchars($ip); ?></span>
            </div>
          </div>
        </div>

        <div class="menu-items">
          <div class="menu-layout">
            <div class="menu-buttons" id="menu-buttons">
              <div class="menu-item" onclick="handleMenu('quick')">QUICK GAME</div>
              <div class="menu-item" onclick="handleMenu('custom')">CUSTOM GAME</div>
              <div class="menu-item" onclick="handleMenu('tournament')">GAUNTLET</div>
              <div class="menu-item" onclick="handleMenu('tutorial')">TUTORIAL</div>
              <div class="menu-item" onclick="handleMenu('achievements')">ACHIEVEMENTS <span class="achievement-percent">[0%]</span></div>
              <div class="menu-item" onclick="handleMenu('settings')">SETTINGS</div>
            </div>
            <div class="credits-panel" id="credits-panel" style="display:none;">
              <div class="credits-header-row">
                <div class="credits-title">CREDITS</div>
                <button class="credits-back" onclick="closeCredits()">BACK</button>
              </div>
              <div class="credits-scroll">
                <h3>About Chiriboga</h3>
                <p><strong>Chiriboga</strong> is a Netrunner engine originally developed by <a href="https://github.com/bobtheuberfish" target="_blank" rel="noopener">bobtheuberfish</a>. 
                It implements <em>Android: Netrunner</em> gameplay with an AI opponent. The source is available on <a href="https://github.com/bobtheuberfish/chiriboga" target="_blank" rel="noopener">Github</a>.</p>
                <p>Special thanks to testers, including: BadEpsilon, bowlsley, D-Smith, eniteris, Kwaice, Mentlegen, olompumpa, R41B, saff, Saintis, Ysengrin.</p>
                <p class="aside">"...but who ordered him to wear that hat?"</p>
                <h3>Solo Mode</h3>
                <p>The Solo Mode extension is developed by <a href="https://github.com/drbo6" target="_blank" rel="noopener">DrBo6</a>. It adds a more refined interface and game modes. 
                It is available on <a href="https://github.com/NEU-DrBo6/chiriboga" target="_blank" rel="noopener">Github</a> as well.</p>
                <h3>Pre‑constructed Decks</h3>
                <p>Girometics precons designed by <a href="https://netrunnerdb.com/en/decklists/find?faction=&sort=popularity&rotation_id=&author=Girometics&title=&is_legal=&mwl_code=&packs%5B%5D=su21&packs%5B%5D=sg" target="_blank">Girometics</a> and curated by <a href="https://github.com/drbo6" target="_blank" rel="noopener">DrBo6</a>.</p>
                <h3>Legal & Attribution</h3>
                <p><em>Netrunner</em> and <em>Android</em> are trademarks of Fantasy Flight Publishing, Inc. and/or Wizards of the Coast LLC. Not affiliated with FFG, WotC, or NSG.</p>
                <p>Chiriboga includes cards from <a href="https://nullsignal.games" target="_blank">Null Signal's</a> <em>System Gateway</em> and <em>System Update 2021</em>. 
                Its card art & symbols are property of Null Signal Games and used under <a href="https://creativecommons.org/licenses/by-nd/4.0/" target="_blank" rel="noopener">CC BY-ND 4.0</a>. This is a fan implementation and it is not endorsed by NSG.</p>
                <p>All trademarks, card imagery, and faction symbols remain property of their respective owners.</p>                                
                <p>Deck of cards by Daniel Solis from <a href="https://thenounproject.com/browse/icons/term/deck-of-cards/" target="_blank" title="deck of cards Icons">Noun Project</a> (CC BY 3.0)</p>
                <p>Book by Ralf Schmitzer from <a href="https://thenounproject.com/browse/icons/term/book/" target="_blank" title="Book Icons">Noun Project</a> (CC BY 3.0)</p>
                <p class="tiny-note">Open source spirit: Contributions and feedback welcome.</p>
              </div>
            </div>

            <div class="tutorial-panel" id="tutorial-panel" style="display:none;">
              <div class="tutorial-header-row">
                <div class="tutorial-title">TUTORIAL</div>
                <button class="tutorial-back" onclick="closeTutorial()">BACK</button>
              </div>
              <div id="tutorial-buttons">
                <div id="tutorial-grid">
                  <div class="tutorial-item" onclick="startTutorial(0)"><span class="tutorial-number">1</span><span class="tutorial-label">CLICKS & RUNS</span></div>
                  <div class="tutorial-item" onclick="startTutorial(1)"><span class="tutorial-number">2</span><span class="tutorial-label">CREDITS & CARD TYPES</span></div>
                  <div class="tutorial-item" onclick="startTutorial(2)"><span class="tutorial-number">3</span><span class="tutorial-label">ICE & ICEBREAKERS</span></div>
                  <div class="tutorial-item" onclick="startTutorial(3)"><span class="tutorial-number">4</span><span class="tutorial-label">ASSETS & TRASH COSTS</span></div>
                  <div class="tutorial-item" onclick="startTutorial(4)"><span class="tutorial-number">5</span><span class="tutorial-label">ADVANCING & SCORING</span></div>
                  <div class="tutorial-item" onclick="startTutorial(5)"><span class="tutorial-number">6</span><span class="tutorial-label">UPGRADES & ROOT</span></div>
                </div>
              </div>
            </div>
            
            <div class="match-preview" onclick="rerollDecks()">
              <div class="preview-title">QUICK GAME<br />INCOMING..</div>
              <div class="portrait-container" id="player-portrait">
                <img class="portrait" src="" alt="">
                <div class="portrait-glow"></div>
                <div class="portrait-label">YOU</div>
              </div>
              
              <div class="vs-text">VS</div>
              
              <div class="portrait-container" id="ai-portrait">
                <img class="portrait" src="" alt="">
                <div class="portrait-glow"></div>
                <div class="portrait-glow"></div>
                <div class="portrait-label">CPU</div>
              </div>
            </div>

          </div>
        </div>

        <div class="meta-stack portrait-only">
          <div class="version">BUILD 0.1.0-ALPHA // 2077.<?php echo date('m.d'); ?></div>
          <div class="status-bar">
            <span class="status-item" style="cursor:pointer;" onclick="openCredits()">CREDITS</span>
            <span class="status-item" id="threat-level-portrait">THREAT LEVEL: <span id="threat-color-portrait">1</span></span>
            <span class="status-item">IP: <?php echo htmlspecialchars($ip); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Select random Girometics precon decks
    var selectedRunnerDeck = null;
    var selectedCorpDeck = null;
    var playerDeck = null;
    var aiDeck = null;
    var giromRunnerDecks = [];
    var giromCorpDecks = [];
    
    // Function to select random decks
    function selectRandomDecks() {
      // Pick random decks
      if (giromRunnerDecks.length > 0) {
        selectedRunnerDeck = giromRunnerDecks[Math.floor(Math.random() * giromRunnerDecks.length)];
      }
      if (giromCorpDecks.length > 0) {
        selectedCorpDeck = giromCorpDecks[Math.floor(Math.random() * giromCorpDecks.length)];
      }
      
      // Randomly assign player vs AI
      if (Math.random() < 0.5) {
        playerDeck = selectedRunnerDeck;
        aiDeck = selectedCorpDeck;
      } else {
        playerDeck = selectedCorpDeck;
        aiDeck = selectedRunnerDeck;
      }
      
      updatePortraits();
    }
    
    // Function to update portraits
    function updatePortraits() {
      if (playerDeck && aiDeck) {
        var playerIdentity = cardSet[playerDeck.identity];
        var aiIdentity = cardSet[aiDeck.identity];
        
        // Update player portrait
        var playerImg = document.querySelector('#player-portrait .portrait');
        if (playerIdentity) {
          playerImg.src = 'https://chiriboga-nsg.cronbach.com/images/' + playerIdentity.imageFile.replace('.png', '.jpg');
        }
        
        // Update AI portrait
        var aiImg = document.querySelector('#ai-portrait .portrait');
        if (aiIdentity) {
          aiImg.src = 'https://chiriboga-nsg.cronbach.com/images/' + aiIdentity.imageFile.replace('.png', '.jpg');
        }
      }
    }
    
    // Function to reroll decks
    function rerollDecks() {
      selectRandomDecks();
    }
    
    // Wait for all scripts to load before selecting decks
    window.addEventListener('load', function() {
      console.log('Total precon decks loaded:', preconDecks.length);
      console.log('Sample deck:', preconDecks[0]);
      
      // Filter Girometics decks by side (case-insensitive)
      giromRunnerDecks = preconDecks.filter(function(d) {
        var isGirometics = d.deck_set && d.deck_set.toLowerCase() === 'girometics';
        var hasIdentity = cardSet[d.identity];
        var isRunner = hasIdentity && cardSet[d.identity].player === runner;
        return isGirometics && hasIdentity && isRunner;
      });
      giromCorpDecks = preconDecks.filter(function(d) {
        var isGirometics = d.deck_set && d.deck_set.toLowerCase() === 'girometics';
        var hasIdentity = cardSet[d.identity];
        var isCorp = hasIdentity && cardSet[d.identity].player === corp;
        return isGirometics && hasIdentity && isCorp;
      });
      
      console.log('Girometics Runner decks:', giromRunnerDecks.length, giromRunnerDecks.map(function(d) { return d.name; }));
      console.log('Girometics Corp decks:', giromCorpDecks.length, giromCorpDecks.map(function(d) { return d.name; }));
      
      // Select initial random decks
      selectRandomDecks();

      // Lock menu layout dimensions so panel swaps do not shift the UI
      lockMenuLayoutDimensions();
    });
    
    function handleMenu(option) {
      const item = event.target.closest('.menu-item');
      
      // Handle tutorial
      if (option === 'tutorial') {
        openTutorial();
        return;
      }
      
      // Show "COMING SOON" for non-implemented features
      if (option !== 'quick' && option !== 'custom') {
        item.innerHTML = 'COMING SOON';
        setTimeout(() => {
          const labels = {
            quick: 'QUICK GAME',
            custom: 'CUSTOM GAME',
            tournament: 'GAUNTLET',
            tutorial: 'TUTORIAL',
            achievements: 'ACHIEVEMENTS <span class="achievement-percent">[0%]</span>',
            settings: 'SETTINGS'
          };
          item.innerHTML = labels[option];
        }, 1500);
        return;
      }
      
      item.innerHTML = 'LOADING...';
      
      setTimeout(() => {
        const labels = {
          quick: 'QUICK GAME',
          custom: 'CUSTOM GAME',
          tournament: 'GAUNTLET',
          tutorial: 'TUTORIAL',
          achievements: 'ACHIEVEMENTS <span class="achievement-percent">[0%]</span>',
          settings: 'SETTINGS'
        };
        item.innerHTML = labels[option];
        
        // Navigate based on option
        if (option === 'custom' && playerDeck && aiDeck) {
          // Build compressed deck strings from precon format
          var playerJson = {identity: parseInt(playerDeck.identity), cards: []};
          if (playerDeck.Notes) playerJson.notes = playerDeck.Notes;
          if (playerDeck.name) playerJson.name = playerDeck.name;
          if (playerDeck.URL) playerJson.url = playerDeck.URL;
          for (var cardId in playerDeck.cards) {
            var count = playerDeck.cards[cardId];
            for (var i = 0; i < count; i++) {
              playerJson.cards.push(parseInt(cardId));
            }
          }
          var aiJson = {identity: parseInt(aiDeck.identity), cards: []};
          if (aiDeck.Notes) aiJson.notes = aiDeck.Notes;
          if (aiDeck.name) aiJson.name = aiDeck.name;
          if (aiDeck.URL) aiJson.url = aiDeck.URL;
          for (var cardId in aiDeck.cards) {
            var count = aiDeck.cards[cardId];
            for (var i = 0; i < count; i++) {
              aiJson.cards.push(parseInt(cardId));
            }
          }
          
          var playerCompressed = LZString.compressToEncodedURIComponent(JSON.stringify(playerJson));
          var aiCompressed = LZString.compressToEncodedURIComponent(JSON.stringify(aiJson));
          
          // Determine player side (r=runner, c=corp)
          var playerSide = (cardSet[playerDeck.identity].player === runner) ? 'r' : 'c';
          var aiSide = (cardSet[aiDeck.identity].player === runner) ? 'r' : 'c';
          
          window.location.href = 'decklauncher.php?sets=systemgateway-systemupdate2021-midnightsun&p=' + playerSide + 
                                 '&' + aiSide + '=' + aiCompressed + 
                                 '&' + playerSide + '=' + playerCompressed;
        } else if (option === 'quick' && playerDeck && aiDeck) {
          // Build compressed deck strings from precon format
          var playerJson = {identity: parseInt(playerDeck.identity), cards: []};
          if (playerDeck.Notes) playerJson.notes = playerDeck.Notes;
          if (playerDeck.name) playerJson.name = playerDeck.name;
          if (playerDeck.URL) playerJson.url = playerDeck.URL;
          for (var cardId in playerDeck.cards) {
            var count = playerDeck.cards[cardId];
            for (var i = 0; i < count; i++) {
              playerJson.cards.push(parseInt(cardId));
            }
          }
          var aiJson = {identity: parseInt(aiDeck.identity), cards: []};
          if (aiDeck.Notes) aiJson.notes = aiDeck.Notes;
          if (aiDeck.name) aiJson.name = aiDeck.name;
          if (aiDeck.URL) aiJson.url = aiDeck.URL;
          for (var cardId in aiDeck.cards) {
            var count = aiDeck.cards[cardId];
            for (var i = 0; i < count; i++) {
              aiJson.cards.push(parseInt(cardId));
            }
          }
          
          var playerCompressed = LZString.compressToEncodedURIComponent(JSON.stringify(playerJson));
          var aiCompressed = LZString.compressToEncodedURIComponent(JSON.stringify(aiJson));
          
          // Determine player side (r=runner, c=corp)
          var playerSide = (cardSet[playerDeck.identity].player === runner) ? 'r' : 'c';
          var aiSide = (cardSet[aiDeck.identity].player === runner) ? 'r' : 'c';
          
          window.location.href = 'engine.php?sets=systemgateway-systemupdate2021-midnightsun&p=' + playerSide + 
                                 '&' + aiSide + '=' + aiCompressed + 
                                 '&' + playerSide + '=' + playerCompressed +
                                 '&showdeck=1';
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

    // Threat level indicator: start GREEN and hold >= 60s, then cycle every 1-10 min
    var threatLevels = [
      { name: '1', color: '#33ff33' },
      { name: '2', color: '#ffff33' },
      { name: '3', color: '#ff9933' },
      { name: '4', color: '#ff3333' }
    ];
    function setThreat(threat){
      var el1 = document.getElementById('threat-color');
      var el2 = document.getElementById('threat-color-portrait');
      if (el1) { el1.textContent = threat.name; el1.style.color = threat.color; el1.style.textShadow = '0 0 5px ' + threat.color; }
      if (el2) { el2.textContent = threat.name; el2.style.color = threat.color; el2.style.textShadow = '0 0 5px ' + threat.color; }
    }
    function scheduleNextThreatChange(minSec, maxSec){
      var delay = (minSec + Math.random() * (maxSec - minSec)) * 1000;
      setTimeout(function(){
        // pick a random threat (could be same as current; spec allows)
        var idx = Math.floor(Math.random() * threatLevels.length);
        setThreat(threatLevels[idx]);
        // subsequent cycles: 60-600s
        scheduleNextThreatChange(60, 600);
      }, delay);
    }
    // initialize GREEN and hold for at least 60 seconds, then cycle 1-10 minutes
    setThreat(threatLevels[0]);
    scheduleNextThreatChange(60, 600);

    // Keep the menu layout dimensions fixed to avoid layout shifts when toggling panels
    function lockMenuLayoutDimensions(){
      var layout = document.querySelector('.menu-layout');
      if (!layout) return;
      var rect = layout.getBoundingClientRect();
      layout.style.width = rect.width + 'px';
      layout.style.height = rect.height + 'px';
    }

    // Credits toggle
    function openCredits(){
      var menu = document.getElementById('menu-buttons');
      var panel = document.getElementById('credits-panel');
      // If already open, act like back button
      if (menu.style.display === 'none' && panel.style.display === 'flex') {
        closeCredits();
        return;
      }
      // Capture current width of menu buttons before hiding
      var rect = menu.getBoundingClientRect();
      var w = rect.width;
      var h = rect.height; // match visual height
      menu.style.display='none';
      panel.style.width = w + 'px';
      panel.style.maxHeight = h + 'px';
      panel.style.display='flex';
    }
    function closeCredits(){
      document.getElementById('credits-panel').style.display='none';
      document.getElementById('menu-buttons').style.display='flex';
      // Clear explicit width so menu layout can adapt on resize
      var p = document.getElementById('credits-panel');
      p.style.width='';
      p.style.maxHeight='';
    }

    // Tutorial functions
    var screenContentWidth = null;
    var screenContentHeight = null;

    function openTutorial(){
      var menu = document.getElementById('menu-buttons');
      var panel = document.getElementById('tutorial-panel');
      var screenContent = document.querySelector('.screen-content');
      
      // Lock screen-content dimensions
      var rect = screenContent.getBoundingClientRect();
      screenContentWidth = rect.width;
      screenContentHeight = rect.height;
      screenContent.style.width = screenContentWidth + 'px';
      screenContent.style.height = screenContentHeight + 'px';
      
      // If already open, act like back button
      if (menu.style.display === 'none' && panel.style.display === 'flex') {
        closeTutorial();
        return;
      }
      
      // Capture current width of menu buttons before hiding
      var rect = menu.getBoundingClientRect();
      var w = rect.width;
      var h = rect.height;
      menu.style.display='none';
      panel.style.width = w + 'px';
      panel.style.maxHeight = h + 'px';
      panel.style.display='flex';
    }
    
    function closeTutorial(){
      var screenContent = document.querySelector('.screen-content');
      document.getElementById('tutorial-panel').style.display='none';
      document.getElementById('menu-buttons').style.display='flex';
      // Clear explicit width/height so menu layout can adapt on resize
      var p = document.getElementById('tutorial-panel');
      p.style.width='';
      p.style.maxHeight='';
      screenContent.style.width='';
      screenContent.style.height='';
      screenContentWidth = null;
      screenContentHeight = null;
    }
    
    function startTutorial(mentorIndex) {
      var mentorMap = [0, 1, 2, 3, 4, 5];
      window.location.href = 'engine.php?p=r&mentor=' + mentorMap[mentorIndex];
    }
  </script>
</body>
</html>
