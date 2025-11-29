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
            <div class="version">BUILD 0.1.0-ALPHA // 2077.<?php echo date('m.d'); ?></div>
            <div class="status-bar">
              <span class="status-item">MEM: 64KB FREE</span>
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

        <!-- Version & status moved into .game-title for small-height layout -->
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
    });
    
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
        
        // Navigate based on option
        if (option === 'custom' && playerDeck && aiDeck) {
          // Build compressed deck strings
          var playerJson = {identity: playerDeck.identity, cards: []};
          for (var cardId in playerDeck.cards) {
            for (var i = 0; i < playerDeck.cards[cardId]; i++) {
              playerJson.cards.push(parseInt(cardId));
            }
          }
          var aiJson = {identity: aiDeck.identity, cards: []};
          for (var cardId in aiDeck.cards) {
            for (var i = 0; i < aiDeck.cards[cardId]; i++) {
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
          // Build compressed deck strings
          var playerJson = {identity: playerDeck.identity, cards: []};
          for (var cardId in playerDeck.cards) {
            for (var i = 0; i < playerDeck.cards[cardId]; i++) {
              playerJson.cards.push(parseInt(cardId));
            }
          }
          var aiJson = {identity: aiDeck.identity, cards: []};
          for (var cardId in aiDeck.cards) {
            for (var i = 0; i < aiDeck.cards[cardId]; i++) {
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
                                 '&' + playerSide + '=' + playerCompressed;
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
