<?php
$version = "0.6.10-BETA";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Netrunner: Solo Mode</title>
  <link href="images/favicon.ico" rel="icon">
  <link rel="manifest" href="manifest.json">
  <link rel="stylesheet" href="style.css?<?php echo filemtime('style.css'); ?>" />
  <style>
    /* Achievements Panel Styles */
    .achievements-panel {
      display: none;
      flex-direction: column;
      gap: 8px;
      overflow: hidden;
    }
    .achievements-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 8px;
      border-bottom: 1px solid var(--crt-green-dim);
    }
    .achievements-title {
      font-size: 1.2rem;
      color: var(--crt-green);
      text-shadow: 0 0 10px var(--crt-green);
    }
    .achievements-back {
      background: transparent;
      border: 1px solid var(--crt-green-dim);
      color: var(--crt-green-muted);
      padding: 4px 12px;
      cursor: pointer;
      font-family: inherit;
      font-size: 0.85rem;
      transition: all 0.2s;
    }
    .achievements-back:hover {
      color: var(--crt-green);
      border-color: var(--crt-green);
      text-shadow: 0 0 5px var(--crt-green);
    }
    .achievements-content {
      overflow-y: auto;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .achievements-section-header {
      font-size: 0.85rem;
      color: var(--crt-green-muted);
      padding: 8px 0 4px;
      border-bottom: 1px dashed var(--crt-green-dim);
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    /* Achievements List */
    .achievements-list {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .achievement-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px;
      background: rgba(51, 255, 51, 0.05);
      border: 1px solid var(--crt-green-dim);
      cursor: help;
      transition: all 0.2s;
    }
    .achievement-row:hover {
      background: rgba(51, 255, 51, 0.1);
      border-color: var(--crt-green);
    }
    .achievement-incomplete {
      opacity: 0.4;
      filter: grayscale(0.5);
    }
    .achievement-incomplete:hover {
      opacity: 0.6;
    }
    .achievement-name {
      font-size: 0.9rem;
      color: var(--crt-green);
    }
    .achievement-date {
      font-size: 0.85rem;
      color: var(--crt-green-muted);
      font-family: monospace;
    }
  </style>
  <?php include 'cardrenderer/webfont.php'; ?>
  <script src="deck/lz-string.min.js?<?php echo filemtime('deck/lz-string.min.js'); ?>"></script>
  <script src="deck/seedrandom.min.js?<?php echo filemtime('deck/seedrandom.min.js'); ?>"></script>
  <script>
    var cardSet = []; // prepare to receive card definitions
    var setIdentifiers = []; // set identifiers
    var runner = {};
    var corp = {};
    var preconDecks = [];
    function registerPrecon(deck) {
      preconDecks.push(deck);
    }

    // Early shim so menu clicks before scripts load won't throw ReferenceError
    (function(){
      if (typeof window.handleMenu === 'undefined') {
        window._queuedMenuClicks = [];
        window.handleMenu = function(option, evt) {
          // queue option; evt may not be available later
          window._queuedMenuClicks.push(option);
        };
        window._flushQueuedMenuClicks = function() {
          if (!window._queuedMenuClicks || !window.handleMenuImpl) return;
          var q = window._queuedMenuClicks.slice(); window._queuedMenuClicks = [];
          q.forEach(function(opt){ try { window.handleMenuImpl(opt); } catch(e){} });
        };
      }
    })();
  </script>
  <?php
  echo '<script src="utility.js?' . filemtime('utility.js') . '"></script>';
  echo '<script src="config.js?' . filemtime('config.js') . '"></script>';

  // Load card sets
  $sets = ["coreset","systemgateway","systemupdate2021","midnightsun","elevation"];
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
    // Validate IP format to prevent header injection
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
      $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
  } else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
  }
  ?>
</head>
<body class="no-scroll">
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
            <div class="version">BUILD <?php echo $version; ?> // 2077.<?php echo date('m.d'); ?></div>
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
              <div class="menu-item" onclick="handleMenu('quick', event)">QUICK GAME</div>
              <div class="menu-item" onclick="handleMenu('custom', event)">CUSTOM GAME</div>
              <div class="menu-item-container" id="gauntlet-container">
                <div class="menu-item" id="gauntlet-main" onclick="handleMenu('tournament', event)">GAUNTLET</div>
                <div class="gauntlet-submenu" id="gauntlet-submenu" style="display:none;">
                  <div class="menu-item-sub" onclick="handleGauntletNew(event)">NEW</div>
                  <div class="menu-item-sub disabled" id="gauntlet-continue-btn" onclick="handleGauntletContinue(event)">CONTINUE</div>
                </div>
              </div>
              <div class="menu-item" onclick="handleMenu('tutorial', event)">TUTORIAL</div>
              <div class="menu-item" onclick="handleMenu('achievements', event)">ACHIEVEMENTS <span class="achievement-percent">[0%]</span></div>
              <div class="menu-item" onclick="handleMenu('settings', event)">SETTINGS</div>
            </div>
            <div class="credits-panel" id="credits-panel" style="display:none;">
              <div class="credits-header-row">
                <div class="credits-title">CREDITS</div>
                <button class="credits-back" onclick="closeCredits()">BACK</button>
              </div>
              <div class="credits-scroll">
                <h3>About Netrunner Solo Mode</h3>
                <p>This Netrunner Solo Mode extension for the Chiriboga engine is developed by <a href="https://github.com/drbo6" target="_blank" rel="noopener">DrBo6</a>. It adds a more refined interface and game modes to the game. 
                It is available on <a href="https://github.com/NEU-DrBo6/chiriboga" target="_blank" rel="noopener">Github</a> for bug reports and contributions.</p>                
                <h3>About Chiriboga</h3>
                <p><strong>Chiriboga</strong> is a Netrunner engine developed by <a href="https://github.com/bobtheuberfish" target="_blank" rel="noopener">bobtheuberfish</a>. 
                It implements <em>Android: Netrunner</em> gameplay with an AI opponent. The source is available on <a href="https://github.com/bobtheuberfish/chiriboga" target="_blank" rel="noopener">Github</a>.</p>
                <p>Special thanks to testers, including: BadEpsilon, bowlsley, D-Smith, eniteris, Kwaice, Mentlegen, olompumpa, R41B, saff, Saintis, Ysengrin.</p>
                <p class="aside">"...but who ordered him to wear that hat?"</p>
                <h3>Pre‑constructed Decks</h3>
                <p>Girometics SG+SU21 and NSG Core precons designed by <a href="https://netrunnerdb.com/en/decklists/find?faction=&sort=popularity&rotation_id=&author=Girometics&title=&is_legal=&mwl_code=&packs%5B%5D=su21&packs%5B%5D=sg" target="_blank">Girometics</a>. All other precons curated by <a href="https://github.com/drbo6" target="_blank" rel="noopener">DrBo6</a>. Click on them to see their creators on NetrunnerDB.com.</p>
                <h3>Legal & Attribution</h3>
                <p><em>Netrunner</em> and <em>Android</em> are trademarks of Fantasy Flight Publishing, Inc. and/or Wizards of the Coast LLC. Not affiliated with FFG, WotC, or NSG.</p>
                <p>Chiriboga includes cards from <a href="https://nullsignal.games" target="_blank">Null Signal's</a> <em>System Gateway</em> and <em>System Update 2021</em>. 
                Its card art & symbols are property of Null Signal Games and used under <a href="https://creativecommons.org/licenses/by-nd/4.0/" target="_blank" rel="noopener">CC BY-ND 4.0</a>. This is a fan implementation and it is not endorsed by NSG.</p>
                <p>All trademarks, card imagery, and faction symbols remain property of their respective owners.</p>                                
                <p>Deck of cards by Daniel Solis from <a href="https://thenounproject.com/icon/deck-of-cards-219525/" target="_blank" title="deck of cards Icons">Noun Project</a> (<a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>)</p>
                <p>Book by Ralf Schmitzer from <a href="https://thenounproject.com/icon/book-548893/" target="_blank" title="Book Icons">Noun Project</a> (<a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>)</p>
                <p>Debug by Studio GLD from <a href="https://thenounproject.com/icon/debug-3594500/" target="_blank" title="Debug Icons">Noun Project</a> (<a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>)</p>
                <p class="tiny-note">Open source spirit: Contributions and feedback welcome.</p>
              </div>
            </div>

            <div class="settings-panel" id="settings-panel" style="display:none;">
              <div class="settings-header-row">
                <div class="settings-title">SETTINGS</div>
                <button class="settings-back" onclick="closeSettings()">BACK</button>
              </div>
              <div class="settings-content">
                <div class="settings-section-header">GLOBAL SETTINGS</div>
                <div class="settings-group" title="AI execution speed in milliseconds">
                  <label class="settings-label">GAME SPEED</label>
                  <div style="display:flex;align-items:center;gap:12px;justify-content:flex-start;">
                    <label style="display:flex;align-items:center;gap:4px;margin:0;">
                      <input type="checkbox" id="speed-settings-1" onchange="setGameSpeed(1000)">
                      <span style="width:12px;text-align:center;color:var(--crt-green-muted);">1</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:4px;margin:0;">
                      <input type="checkbox" id="speed-settings-2" checked onchange="setGameSpeed(350)">
                      <span style="width:12px;text-align:center;color:var(--crt-green-muted);">2</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:4px;margin:0;">
                      <input type="checkbox" id="speed-settings-3" onchange="setGameSpeed(100)">
                      <span style="width:12px;text-align:center;color:var(--crt-green-muted);">3</span>
                    </label>
                  </div>
                </div>
                <div class="settings-group" title="Enable debug menu in game">
                  <label class="settings-label">DEBUG MENU</label>
                  <div class="settings-switch">
                    <input type="checkbox" id="debug-menu-settings-toggle" onchange="toggleDebugMenu()">
                    <label for="debug-menu-settings-toggle" class="switch-label"></label>
                  </div>
                </div>

                <div class="settings-group" title="Use high-resolution card art when available">
                  <label class="settings-label">HI-RES CARD ART</label>
                  <div class="settings-switch">
                    <input type="checkbox" id="enable-hires-toggle" onchange="toggleEnableHiRes()">
                    <label for="enable-hires-toggle" class="switch-label"></label>
                  </div>
                </div>
                <div class="settings-section-header">GAUNTLET SETTINGS</div>
                <div class="settings-group" title="Number of opponents you must defeat to complete the Gauntlet">
                  <label class="settings-label">GAUNTLET LENGTH</label>
                  <div class="settings-stepper">
                    <button class="stepper-btn" id="gauntlet-length-minus" onclick="adjustGauntletLength(-4)">−</button>
                    <span class="stepper-value" id="gauntlet-length-value">4</span>
                    <button class="stepper-btn" id="gauntlet-length-plus" onclick="adjustGauntletLength(4)">+</button>
                  </div>
                </div>
                <div class="settings-group" title="Cycle through all four corp factions before repeating any faction">
                  <label class="settings-label">ALTERNATE FACTIONS</label>
                  <div class="settings-switch">
                    <input type="checkbox" id="alternate-factions-toggle" onchange="toggleAlternateFactions()">
                    <label for="alternate-factions-toggle" class="switch-label"></label>
                  </div>
                </div>
                <div class="settings-group" title="Distribute runner cards evenly across all factions in your Gauntlet starting card pool">
                  <label class="settings-label">BALANCED FACTIONS</label>
                  <div class="settings-switch">
                    <input type="checkbox" id="balanced-factions-toggle" onchange="toggleBalancedFactions()">
                    <label for="balanced-factions-toggle" class="switch-label"></label>
                  </div>
                </div>
                <div class="settings-group" title="Card sets available for building your runner deck in Gauntlet mode">
                  <label class="settings-label" id="allowed-sets-label" onclick="handleAllowedSetsClick();">ALLOWED<br />PLAYER SETS</label>
                  <div class="settings-checkboxes">
                    <label class="checkbox-label checkbox-disabled" title="Core set, always included">
                      <input type="checkbox" id="set-sg" checked disabled>
                      <span class="checkbox-text">System Gateway</span>
                    </label>
                    <label class="checkbox-label" title="Additional cards from System Update 2021">
                      <input type="checkbox" id="set-su21" onchange="toggleAllowedSet('su21')">
                      <span class="checkbox-text">System Update 2021</span>
                    </label>
                    <label class="checkbox-label" title="Elevation expansion cards (probably bugs here, but hopefully not game-breaking)">
                      <input type="checkbox" id="set-elev" onchange="toggleAllowedSet('elev')">
                      <span class="checkbox-text">Elevation (Untested)</span>
                    </label>
                    <label class="checkbox-label hidden-set-option" id="core-option" title="Original Core Set cards" style="display: none;">
                      <input type="checkbox" id="set-core" onchange="toggleAllowedSet('core')">
                      <span class="checkbox-text">Core Set (Untested)</span>
                    </label>
                    <label class="checkbox-label hidden-set-option" id="ms-option" title="Midnight Sun expansion cards" style="display: none;">
                      <input type="checkbox" id="set-ms" onchange="toggleAllowedSet('ms')">
                      <span class="checkbox-text">Midnight Sun (Untested)</span>
                    </label>
                  </div>
                </div>
                <div class="settings-section-header" title="Select which corp decks can appear as opponents in Gauntlet mode">GAUNTLET OPPONENTS</div>
                <div class="precon-list" id="precon-list">
                  <!-- Populated by JavaScript -->
                </div>
                <div class="settings-section-header">DATA MANAGEMENT</div>
                <div class="settings-group" title="Export all local data including settings and gauntlet saves">
                  <label class="settings-label">BACKUP DATA</label>
                  <button class="settings-btn" onclick="exportLocalData()">EXPORT</button>
                </div>
                <div class="settings-group" title="Import previously exported data backup">
                  <label class="settings-label">RESTORE DATA</label>
                  <button class="settings-btn" onclick="document.getElementById('import-data-input').click()">IMPORT</button>
                  <input type="file" id="import-data-input" accept=".json" style="display:none;" onchange="importLocalData(event)">
                </div>
              </div>
            </div>

            <div class="achievements-panel" id="achievements-panel" style="display:none;">
              <div class="achievements-header-row">
                <div class="achievements-title">ACHIEVEMENTS</div>
                <button class="achievements-back" onclick="closeAchievements()">BACK</button>
              </div>
              <div class="achievements-content">
                <div class="achievements-section-header">HIGH SCORES</div>
                <div class="high-scores-list" id="high-scores-list">
                  <!-- Populated by JavaScript -->
                </div>
                <div class="achievements-section-header">ACHIEVEMENTS</div>
                <div class="achievements-list" id="achievements-list">
                  <!-- Populated by JavaScript -->
                </div>
              </div>
            </div>

            <!-- Data management modal -->
            <div class="data-modal" id="data-modal" style="display:none;">
              <div class="data-modal-content">
                <div class="data-modal-title" id="data-modal-title">TITLE</div>
                <div class="data-modal-message" id="data-modal-message">Message goes here</div>
                <div class="data-modal-buttons" id="data-modal-buttons">
                  <button class="data-modal-btn" onclick="closeDataModal()">OK</button>
                </div>
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
                  <div class="tutorial-item" onclick="window.location.href='engine.php?ap=6&p=r&r=N4IglgJgpgdgLmOBPEAuAzABkwdgGwA0IAxgIYBOEAzmgNpbaEOZPYCMATAQ59++n0xsALILYBWMZJ4AOQR0zzFDDm3lqVrTBy0cc8-SrlH5x7BwCc8qwyyC7t5dnRdbr5wNufnwgLoBfIA&c=N4IglgJgpgdgLmOBPEAuAzABkwdhwGhAGMBDAJwgGc0BtLTdA+x-ZgTle3Q-oBZNOmfoN4AmEQFZJIgGyyRTbL0WYZvQWo0qZ27T2wz9uAfRwnsOAIyCrN8afsXHudDden1Hm1NM+LEgF0AXyA&t=1'"><span class="tutorial-number">7</span><span class="tutorial-label">VS CORP STARTER DECK</span></div>
                  <div class="tutorial-item" onclick="window.location.href='engine.php?ap=6&p=c&c=N4IglgJgpgdgLmOBPEAuAzABkwdhwGhAGMBDAJwgGc0BtLTdA+x-ZgTle3Q-oBZNOmfoN4AmEQFZJIgGyyRTbL0WYZvQWo0qZ27T2wz9uAfRwnsOAIyCrN8afsXHudDden1Hm1NM+LEgF0AXyA&r=N4IglgJgpgdgLmOBPEAuAzABkwdgGwA0IAxgIYBOEAzmgNpbaEOZPYCMATAQ59++n0xsALILYBWMZJ4AOQR0zzFDDm3lqVrTBy0cc8-SrlH5x7BwCc8qwyyC7t5dnRdbr5wNufnwgLoBfIA&t=1'"><span class="tutorial-number">8</span><span class="tutorial-label">VS RUNNER STARTER DECK</span></div>
                </div>
              </div>
            </div>
            
            <div class="match-preview" onclick="selectRandomDecks()">
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
          <div class="version">BUILD <?php echo $version; ?> // 2077.<?php echo date('m.d'); ?></div>
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
    
    // Settings overrides (initialized from gauntletConfig on page load)
    var settingsOverrides = {
      gauntletLength: null,
      alternateFactions: null,
      balancedFactions: null,
      allowedSets: null,
      preconOverrides: {}  // Maps precon name to boolean override for useForGauntlet
    };
    
    // ========================================
    // ACHIEVEMENTS SYSTEM
    // ========================================
    var ACHIEVEMENTS_STORAGE_KEY = 'chiriboga-achievements';
    
    // Default achievements definition
    var defaultAchievements = [
      {
        id: 'getHighScore',
        name: 'High Scorer',
        description: 'Record a High Score.',
        achieved: false,
        achievedAt: null,
        hidden: false
      },
      {
        id: 'beat4gauntlet',
        name: 'Complete a short Gauntlet',
        description: 'Survive a Gauntlet of 4 opponents.',
        achieved: false,
        achievedAt: null,
        hidden: false
      },
      {
        id: 'beat8gauntlet',
        name: 'Complete a regular Gauntlet',
        description: 'Survive a Gauntlet of 8 opponents.',
        achieved: false,
        achievedAt: null,
        hidden: false
      },
      {
        id: 'beat12gauntlet',
        name: 'Complete a long Gauntlet',
        description: 'Survive a Gauntlet of 12 opponents.',
        achieved: false,
        achievedAt: null,
        hidden: false
      }
    ];
    
    // Default high scores structure (top 3)
    var defaultHighScores = [
      { score: 0, timestamp: null, identity: null },
      { score: 0, timestamp: null, identity: null },
      { score: 0, timestamp: null, identity: null }
    ];
    
    // Initialize achievements in localStorage if not present
    function initializeAchievements() {
      try {
        var existing = localStorage.getItem(ACHIEVEMENTS_STORAGE_KEY);
        if (!existing) {
          // Create default achievements structure
          var achievementsData = {
            highScores: JSON.parse(JSON.stringify(defaultHighScores)),
            achievements: JSON.parse(JSON.stringify(defaultAchievements))
          };
          localStorage.setItem(ACHIEVEMENTS_STORAGE_KEY, JSON.stringify(achievementsData));
          return achievementsData;
        } else {
          // Parse existing data and merge any new achievements
          var data = JSON.parse(existing);
          
          if (!Array.isArray(data.highScores)) {
            data.highScores = JSON.parse(JSON.stringify(defaultHighScores));
          } else if (data.highScores.length > 3) {
            // Trim to top 3 if somehow more were added
            data.highScores.sort(function(a, b) {
              if (b.score !== a.score) return b.score - a.score;
              return new Date(b.timestamp) - new Date(a.timestamp);
            });
            data.highScores = data.highScores.slice(0, 3);
          }
          
          if (!Array.isArray(data.achievements)) {
            data.achievements = [];
          }
          
          // Merge in any new achievements that don't exist yet
          var existingIds = {};
          for (var i = 0; i < data.achievements.length; i++) {
            existingIds[data.achievements[i].id] = true;
          }
          for (var j = 0; j < defaultAchievements.length; j++) {
            if (!existingIds[defaultAchievements[j].id]) {
              data.achievements.push(JSON.parse(JSON.stringify(defaultAchievements[j])));
            }
          }
          
          // Save back with any new achievements added
          localStorage.setItem(ACHIEVEMENTS_STORAGE_KEY, JSON.stringify(data));
          return data;
        }
      } catch (e) {
        console.error('Error initializing achievements:', e);
        return { highScores: JSON.parse(JSON.stringify(defaultHighScores)), achievements: JSON.parse(JSON.stringify(defaultAchievements)) };
      }
    }
    
    // Get achievements data
    function getAchievements() {
      try {
        var data = localStorage.getItem(ACHIEVEMENTS_STORAGE_KEY);
        if (data) {
          return JSON.parse(data);
        }
      } catch (e) {
        console.error('Error reading achievements:', e);
      }
      return initializeAchievements();
    }
    
    // Update high scores if new score qualifies for top 3
    function updateHighScore(score, identity) {
      try {
        var data = getAchievements();
        var dominated = data.highScores[2].score;
        
        if (score > dominated) {
          // Add new score and sort
          data.highScores.push({
            score: score,
            timestamp: new Date().toISOString(),
            identity: identity
          });
          data.highScores.sort(function(a, b) { return b.score - a.score; });
          data.highScores = data.highScores.slice(0, 3);
          localStorage.setItem(ACHIEVEMENTS_STORAGE_KEY, JSON.stringify(data));
          return true;
        }
        return false;
      } catch (e) {
        console.error('Error updating high score:', e);
        return false;
      }
    }
    
    // Unlock an achievement by id
    function unlockAchievement(achievementId) {
      try {
        var data = getAchievements();
        for (var i = 0; i < data.achievements.length; i++) {
          if (data.achievements[i].id === achievementId && !data.achievements[i].achieved) {
            data.achievements[i].achieved = true;
            data.achievements[i].achievedAt = new Date().toISOString();
            localStorage.setItem(ACHIEVEMENTS_STORAGE_KEY, JSON.stringify(data));
            return data.achievements[i];
          }
        }
        return null;
      } catch (e) {
        console.error('Error unlocking achievement:', e);
        return null;
      }
    }
    
    // Calculate achievement completion percentage
    function getAchievementPercentage() {
      var data = getAchievements();
      if (!data.achievements || data.achievements.length === 0) return 0;
      
      var achieved = 0;
      for (var i = 0; i < data.achievements.length; i++) {
        if (data.achievements[i].achieved) {
          achieved++;
        }
      }
      return Math.round((achieved / data.achievements.length) * 100);
    }
    
    // Update the achievement percentage display in the menu
    function updateAchievementDisplay() {
      var percent = getAchievementPercentage();
      var displays = document.querySelectorAll('.achievement-percent');
      for (var i = 0; i < displays.length; i++) {
        displays[i].textContent = '[' + percent + '%]';
      }
    }
    
    // ========================================
    // END ACHIEVEMENTS SYSTEM
    // ========================================
    
    // Hidden sets reveal tracking
    var hiddenSetsRevealed = false;
    var allowedSetsClickCount = 0;
    var allowedSetsClickTimeout = null;
    
    // Handle clicks on "Allowed Player Sets" label to reveal hidden options
    function handleAllowedSetsClick() {
      if (hiddenSetsRevealed) return; // Already revealed
      
      allowedSetsClickCount++;
      
      // Reset click count after 2 seconds of no clicks
      if (allowedSetsClickTimeout) {
        clearTimeout(allowedSetsClickTimeout);
      }
      allowedSetsClickTimeout = setTimeout(function() {
        allowedSetsClickCount = 0;
      }, 2000);
      
      // Reveal hidden options after 6 clicks
      if (allowedSetsClickCount >= 6) {
        hiddenSetsRevealed = true;
        document.getElementById('core-option').style.display = '';
        document.getElementById('ms-option').style.display = '';
      }
    }
    
    // Load settings from localStorage, falling back to config defaults
    function initializeSettings() {
      var saved = null;
      try {
        var savedJson = localStorage.getItem('chiriboga-settings');
        if (savedJson) {
          saved = JSON.parse(savedJson);
        }
      } catch (e) {
        console.warn('Could not load settings from localStorage:', e);
      }
      
      // Use saved values if available, otherwise fall back to config
      // Valid gauntlet lengths are 4, 8, or 12 - snap to nearest valid value
      var rawGauntletLength = (saved && typeof saved.gauntletLength === 'number') 
        ? saved.gauntletLength
        : (gauntletConfig.gauntletLength || 4);
      // Snap to nearest valid value (4, 8, or 12)
      if (rawGauntletLength <= 6) {
        settingsOverrides.gauntletLength = 4;
      } else if (rawGauntletLength <= 10) {
        settingsOverrides.gauntletLength = 8;
      } else {
        settingsOverrides.gauntletLength = 12;
      }
      settingsOverrides.alternateFactions = (saved && typeof saved.alternateFactions === 'boolean') 
        ? saved.alternateFactions 
        : (gauntletConfig.alternateFactions !== false);
      settingsOverrides.balancedFactions = (saved && typeof saved.balancedFactions === 'boolean') 
        ? saved.balancedFactions 
        : (gauntletConfig.balancedFactions || false);
      settingsOverrides.allowedSets = (saved && Array.isArray(saved.allowedSets)) 
        ? saved.allowedSets.slice() 
        : (gauntletConfig.allowedSets ? gauntletConfig.allowedSets.slice() : []);
      
      // Ensure System Gateway is always included
      if (settingsOverrides.allowedSets.indexOf('sg') === -1) {
        settingsOverrides.allowedSets.push('sg');
      }
      
      // Load precon overrides
      settingsOverrides.preconOverrides = (saved && typeof saved.preconOverrides === 'object' && saved.preconOverrides !== null)
        ? saved.preconOverrides
        : {};
      
      // Load game speed (default 350ms)
      settingsOverrides.gameSpeed = (saved && typeof saved.gameSpeed === 'number')
        ? saved.gameSpeed
        : 350;
      
      // Load debug menu preference (default false)
      settingsOverrides.debugMenuEnabled = (saved && typeof saved.debugMenuEnabled === 'boolean')
        ? saved.debugMenuEnabled
        : false;

      // Load hi-res preference (default from config or false)
      settingsOverrides.enableHiRes = (saved && typeof saved.enableHiRes === 'boolean')
        ? saved.enableHiRes
        : (typeof gauntletConfig !== 'undefined' && typeof gauntletConfig.enableHiRes === 'boolean' ? gauntletConfig.enableHiRes : false);
      
      // Update UI to match
      document.getElementById('gauntlet-length-value').textContent = settingsOverrides.gauntletLength;
      document.getElementById('alternate-factions-toggle').checked = settingsOverrides.alternateFactions;
      document.getElementById('balanced-factions-toggle').checked = settingsOverrides.balancedFactions;
      document.getElementById('set-su21').checked = settingsOverrides.allowedSets.indexOf('su21') !== -1;
      document.getElementById('set-elev').checked = settingsOverrides.allowedSets.indexOf('elev') !== -1;
      document.getElementById('set-core').checked = settingsOverrides.allowedSets.indexOf('core') !== -1;
      document.getElementById('set-ms').checked = settingsOverrides.allowedSets.indexOf('ms') !== -1;
      
      // Set game speed checkboxes
      document.getElementById('speed-settings-1').checked = (settingsOverrides.gameSpeed === 1000);
      document.getElementById('speed-settings-2').checked = (settingsOverrides.gameSpeed === 350);
      document.getElementById('speed-settings-3').checked = (settingsOverrides.gameSpeed === 100);
      
      // Set debug menu toggle
      document.getElementById('debug-menu-settings-toggle').checked = settingsOverrides.debugMenuEnabled;
      // Set hi-res toggle
      document.getElementById('enable-hires-toggle').checked = settingsOverrides.enableHiRes;
      
      // Update stepper button states
      updateStepperButtons();
      
      // Populate precon list
      populatePreconList();
    }
    
    // Save settings to localStorage
    function saveSettings() {
      try {
        var toSave = {
          gauntletLength: settingsOverrides.gauntletLength,
          alternateFactions: settingsOverrides.alternateFactions,
          balancedFactions: settingsOverrides.balancedFactions,
          allowedSets: settingsOverrides.allowedSets,
          preconOverrides: settingsOverrides.preconOverrides,
          gameSpeed: settingsOverrides.gameSpeed,
          debugMenuEnabled: settingsOverrides.debugMenuEnabled,
          enableHiRes: settingsOverrides.enableHiRes
        };
        localStorage.setItem('chiriboga-settings', JSON.stringify(toSave));
      } catch (e) {
        console.warn('Could not save settings to localStorage:', e);
      }
    }
    
    // Update stepper button disabled states based on current value
    function updateStepperButtons() {
      var minusBtn = document.getElementById('gauntlet-length-minus');
      var plusBtn = document.getElementById('gauntlet-length-plus');
      minusBtn.disabled = settingsOverrides.gauntletLength <= 4;
      plusBtn.disabled = settingsOverrides.gauntletLength >= 12;
    }
    
    // Settings control functions
    function adjustGauntletLength(delta) {
      var newVal = settingsOverrides.gauntletLength + delta;
      // Valid values are 4, 8, or 12
      if (newVal >= 4 && newVal <= 12 && newVal % 4 === 0) {
        settingsOverrides.gauntletLength = newVal;
        document.getElementById('gauntlet-length-value').textContent = newVal;
        updateStepperButtons();
        saveSettings();
      }
    }
    
    function toggleAlternateFactions() {
      settingsOverrides.alternateFactions = document.getElementById('alternate-factions-toggle').checked;
      saveSettings();
    }
    
    function toggleBalancedFactions() {
      settingsOverrides.balancedFactions = document.getElementById('balanced-factions-toggle').checked;
      saveSettings();
    }
    
    function setGameSpeed(speed) {
      settingsOverrides.gameSpeed = speed;
      // Update checkbox states to make them behave like radio buttons
      document.getElementById('speed-settings-1').checked = (speed === 1000);
      document.getElementById('speed-settings-2').checked = (speed === 350);
      document.getElementById('speed-settings-3').checked = (speed === 100);
      saveSettings();
    }
    
    function toggleDebugMenu() {
      settingsOverrides.debugMenuEnabled = document.getElementById('debug-menu-settings-toggle').checked;
      saveSettings();
    }

    function toggleEnableHiRes() {
      settingsOverrides.enableHiRes = document.getElementById('enable-hires-toggle').checked;
      saveSettings();
    }
    
    function toggleAllowedSet(setCode) {
      var checkbox = document.getElementById('set-' + setCode);
      var idx = settingsOverrides.allowedSets.indexOf(setCode);
      if (checkbox.checked && idx === -1) {
        settingsOverrides.allowedSets.push(setCode);
      } else if (!checkbox.checked && idx !== -1) {
        settingsOverrides.allowedSets.splice(idx, 1);
      }
      saveSettings();
    }
    
    // Populate precon list in settings
    function populatePreconList() {
      var listContainer = document.getElementById('precon-list');
      if (!listContainer) return;
      
      listContainer.innerHTML = '';
      
      // Filter to corp precons only (gauntlet opponents)
      var corpPrecons = preconDecks.filter(function(d) {
        if (!cardSet[d.identity]) return false;
        return cardSet[d.identity].player === corp;
      });
      
      // Separate neutral decks from factioned decks
      var neutralPrecons = [];
      var factionedPrecons = [];
      
      for (var i = 0; i < corpPrecons.length; i++) {
        var precon = corpPrecons[i];
        var identity = cardSet[precon.identity];
        var faction = identity.faction || 'Unknown';
        
        if (faction === 'Neutral') {
          neutralPrecons.push(precon);
        } else {
          factionedPrecons.push(precon);
        }
      }
      
      // Sort each group by faction then name
      factionedPrecons.sort(function(a, b) {
        var factionA = cardSet[a.identity].faction || '';
        var factionB = cardSet[b.identity].faction || '';
        if (factionA !== factionB) return factionA.localeCompare(factionB);
        return (a.name || '').localeCompare(b.name || '');
      });
      
      neutralPrecons.sort(function(a, b) {
        return (a.name || '').localeCompare(b.name || '');
      });
      
      // Helper function to create a precon item
      function createPreconItem(precon) {
        var identity = cardSet[precon.identity];
        var faction = identity.faction || 'Unknown';
        
        // Determine if enabled: override takes precedence, otherwise use precon default
        var isEnabled = settingsOverrides.preconOverrides.hasOwnProperty(precon.name)
          ? settingsOverrides.preconOverrides[precon.name]
          : (precon.useForGauntlet === true);
        
        var item = document.createElement('div');
        item.className = 'precon-item';
        
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'precon-checkbox';
        checkbox.checked = isEnabled;
        checkbox.dataset.preconName = precon.name;
        checkbox.onchange = function() {
          togglePreconOverride(this.dataset.preconName, this.checked);
        };
        
        var nameLink = document.createElement('a');
        nameLink.className = 'precon-name';
        nameLink.textContent = precon.name || 'Unknown Deck';
        if (precon.URL) {
          nameLink.href = precon.URL;
          nameLink.target = '_blank';
          nameLink.rel = 'noopener';
        }
        
        var factionLabel = document.createElement('span');
        factionLabel.className = 'precon-faction';
        // Abbreviate faction names
        var factionAbbrev = {
          'Jinteki': 'JIN',
          'Haas-Bioroid': 'HB',
          'NBN': 'NBN',
          'Weyland Consortium': 'WEY',
          'Neutral': 'NEU'
        };
        factionLabel.textContent = factionAbbrev[faction] || faction.substring(0, 3).toUpperCase();
        
        item.appendChild(checkbox);
        item.appendChild(nameLink);
        item.appendChild(factionLabel);
        return item;
      }
      
      // Add factioned precons first
      for (var i = 0; i < factionedPrecons.length; i++) {
        listContainer.appendChild(createPreconItem(factionedPrecons[i]));
      }
      
      // Add neutral section if there are neutral decks
      if (neutralPrecons.length > 0) {
        var neutralHeader = document.createElement('div');
        neutralHeader.className = 'settings-section-header';
        neutralHeader.title = 'Neutral corp decks that can replace the final gauntlet opponent when Alternate Factions is ON';
        neutralHeader.textContent = 'NEUTRAL GAUNTLET OPPONENTS';
        listContainer.appendChild(neutralHeader);
        
        var neutralDesc = document.createElement('div');
        neutralDesc.className = 'precon-section-desc';
        neutralDesc.style.cssText = 'padding: 4px 8px 8px 8px; font-size: 10px; color: #00ff00aa; line-height: 1.4;';
        neutralDesc.textContent = 'When Alternate Factions is ON, enabled neutral decks can replace the final gauntlet opponent.';
        listContainer.appendChild(neutralDesc);
        
        for (var i = 0; i < neutralPrecons.length; i++) {
          listContainer.appendChild(createPreconItem(neutralPrecons[i]));
        }
      }
    }
    
    // Toggle precon override
    function togglePreconOverride(preconName, enabled) {
      settingsOverrides.preconOverrides[preconName] = enabled;
      saveSettings();
    }
    
    // Helper function to check if a precon is enabled for gauntlet
    function isPreconEnabledForGauntlet(precon) {
      if (settingsOverrides.preconOverrides.hasOwnProperty(precon.name)) {
        return settingsOverrides.preconOverrides[precon.name];
      }
      return precon.useForGauntlet === true;
    }
    
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
          playerImg.src = 'images/' + playerIdentity.imageFile.replace('.png', '.jpg');
        }
        
        // Update AI portrait
        var aiImg = document.querySelector('#ai-portrait .portrait');
        if (aiIdentity) {
          aiImg.src = 'images/' + aiIdentity.imageFile.replace('.png', '.jpg');
        }
      }
    }
    
    // Function to launch gauntlet mode
    function LaunchGauntlet() {
      // Generate gauntlet card subset
      var gauntletCardIds = [];
      var gauntletCardCounts = {};
      
      // Helper to check if card matches subtype requirements
      function CardMatchesRequirement(cardId, matchSubtypes, excludeSubtypes) {
        if (!cardSet[cardId] || !cardSet[cardId].subTypes) return false;
        var cardSubtypes = cardSet[cardId].subTypes || [];
        
        if (excludeSubtypes && excludeSubtypes.length > 0) {
          for (var i = 0; i < excludeSubtypes.length; i++) {
            if (cardSubtypes.indexOf(excludeSubtypes[i]) !== -1) return false;
          }
        }
        
        if (!matchSubtypes || matchSubtypes.length === 0) return true;
        
        for (var i = 0; i < matchSubtypes.length; i++) {
          if (cardSubtypes.indexOf(matchSubtypes[i]) === -1) return false;
        }
        return true;
      }
      
      // Helper to check if card is from an allowed set
      function CardFromAllowedSet(cardId) {
        if (!settingsOverrides.allowedSets || settingsOverrides.allowedSets.length === 0) return true;
        
        var allowedSets = settingsOverrides.allowedSets;
        var cardIdStr = String(cardId);
        
        // Map card ID ranges to set codes
        var cardSetMap = {
          '30': 'sg',      // System Gateway (30000-30999)
          '31': 'su21',    // System Update 2021 (31000-31999)
          '33': 'ms',      // Midnight Sun (33000-33999)
          '35': 'elev'     // Elevation (35000-35999)
        };
        
        // Get the set code for this card
        var cardIdInt = parseInt(cardId);
        var cardSetCode = null;
        
        // Core Set uses 1xxx range (1000-1999)
        if (cardIdInt >= 1000 && cardIdInt <= 1999) {
          cardSetCode = 'core';
        } else {
          var cardSetPrefix = cardIdStr.substring(0, 2);
          cardSetCode = cardSetMap[cardSetPrefix];
        }
        
        if (!cardSetCode) return false;
        return allowedSets.indexOf(cardSetCode) !== -1;
      }
      
      // Build exclusion list for locked fixed cards
      var excludedCardIds = {};
      if (gauntletConfig && gauntletConfig.lockedFixedCards && gauntletConfig.fixedCards) {
        for (var i = 0; i < gauntletConfig.fixedCards.length; i++) {
          excludedCardIds[gauntletConfig.fixedCards[i].id] = true;
        }
      }
      
      // Add fixed cards
      if (gauntletConfig && gauntletConfig.fixedCards) {
        for (var i = 0; i < gauntletConfig.fixedCards.length; i++) {
          var fixedCard = gauntletConfig.fixedCards[i];
          var cardId = fixedCard.id;
          var quantity = fixedCard.quantity || 1;
          gauntletCardCounts[cardId] = (gauntletCardCounts[cardId] || 0) + quantity;
          for (var j = 0; j < quantity; j++) {
            gauntletCardIds.push(cardId);
          }
        }
      }
      
      // Add random cards
      if (gauntletConfig && gauntletConfig.randomCardRequirements) {
        var useBalancedFactions = settingsOverrides.balancedFactions || false;
        var runnerFactions = ['Anarch', 'Criminal', 'Shaper', 'Neutral'];
        
        for (var req = 0; req < gauntletConfig.randomCardRequirements.length; req++) {
          var requirement = gauntletConfig.randomCardRequirements[req];
          var quantity = requirement.quantity || 0;
          var cardType = requirement.cardType;
          var matchSubtypes = requirement.matchSubtypes || [];
          var excludeSubtypes = requirement.excludeSubtypes || [];
          
          if (useBalancedFactions) {
            // Faction-balanced selection: distribute cards evenly across factions
            var cardsByFaction = {};
            for (var f = 0; f < runnerFactions.length; f++) {
              cardsByFaction[runnerFactions[f]] = [];
            }
            
            for (var cardId in cardSet) {
              if (!cardSet[cardId]) continue;
              if (cardSet[cardId].player !== runner) continue;
              if (cardSet[cardId].cardType !== cardType) continue;
              if (cardSet[cardId].cardType === 'identity') continue;
              if (!CardFromAllowedSet(cardId)) continue;
              if (excludedCardIds[cardId]) continue;
              if (CardMatchesRequirement(cardId, matchSubtypes, excludeSubtypes)) {
                var cardFaction = cardSet[cardId].faction || 'Neutral';
                if (cardsByFaction[cardFaction]) {
                  cardsByFaction[cardFaction].push(parseInt(cardId));
                }
              }
            }
            
            // Get factions that have at least one matching card
            var factionsWithCards = [];
            for (var f = 0; f < runnerFactions.length; f++) {
              if (cardsByFaction[runnerFactions[f]].length > 0) {
                factionsWithCards.push(runnerFactions[f]);
              }
            }
            
            if (factionsWithCards.length === 0) continue;  // Skip if no cards match
            
            // Sort factions by pool size (smallest first) to prioritize smaller pools
            factionsWithCards.sort(function(a, b) {
              return cardsByFaction[a].length - cardsByFaction[b].length;
            });
            
            // Calculate even distribution with remainder
            var basePerFaction = Math.floor(quantity / factionsWithCards.length);
            var remainder = quantity % factionsWithCards.length;
            
            // Select cards from each faction according to quota
            for (var f = 0; f < factionsWithCards.length; f++) {
              var faction = factionsWithCards[f];
              var pool = cardsByFaction[faction].slice(); // Make a copy to sample without replacement
              var target = basePerFaction + (f < remainder ? 1 : 0);  // First factions get +1 for remainder
              
              // If target exceeds pool size, we need to cycle through the pool multiple times
              for (var s = 0; s < target; s++) {
                if (pool.length === 0) {
                  // Refill the pool if exhausted (rare case where target > pool size)
                  pool = cardsByFaction[faction].slice();
                }
                var randomIdx = Math.floor(Math.random() * pool.length);
                var selectedCard = pool[randomIdx];
                gauntletCardIds.push(selectedCard);
                gauntletCardCounts[selectedCard] = (gauntletCardCounts[selectedCard] || 0) + 1;
                // Remove selected card from pool to prevent immediate duplicates
                pool.splice(randomIdx, 1);
              }
            }
          } else {
            // Original unbalanced selection: pick randomly from entire pool
            var matchingCards = [];
            for (var cardId in cardSet) {
              if (!cardSet[cardId]) continue;
              if (cardSet[cardId].player !== runner) continue;
              if (cardSet[cardId].cardType !== cardType) continue;
              if (cardSet[cardId].cardType === 'identity') continue;
              if (!CardFromAllowedSet(cardId)) continue;
              if (excludedCardIds[cardId]) continue;
              if (CardMatchesRequirement(cardId, matchSubtypes, excludeSubtypes)) {
                matchingCards.push(parseInt(cardId));
              }
            }
            
            var selected = 0;
            while (selected < quantity && matchingCards.length > 0) {
              var randomIdx = Math.floor(Math.random() * matchingCards.length);
              var selectedCard = matchingCards[randomIdx];
              gauntletCardIds.push(selectedCard);
              gauntletCardCounts[selectedCard] = (gauntletCardCounts[selectedCard] || 0) + 1;
              selected++;
            }
          }
        }
      }
      
      // Select opponents based on gauntlet configuration
      var gauntletLength = settingsOverrides.gauntletLength || 4;
      var alternateFactions = settingsOverrides.alternateFactions;
      
      // Initialize perk pools for opponent starting perks
      var regularPerks = [1,1,1,2,2,2,3,3,3];
      var bossPerks = [4,5,6];
      
      // Shuffle the perk pools
      for (var i = regularPerks.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var temp = regularPerks[i];
        regularPerks[i] = regularPerks[j];
        regularPerks[j] = temp;
      }
      for (var i = bossPerks.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var temp = bossPerks[i];
        bossPerks[i] = bossPerks[j];
        bossPerks[j] = temp;
      }
      
      // Helper function to get starting perk for opponent number (1-indexed)
      function getStartingPerk(opponentNum) {
        // Opponents 1-3 => Draw from regularPerks
        if (opponentNum >= 1 && opponentNum <= 3) {
          return regularPerks.length > 0 ? regularPerks.shift() : 0;
        }
        // Opponent 4 => Draw from bossPerks
        if (opponentNum === 4) {
          return bossPerks.length > 0 ? bossPerks.shift() : 0;
        }
        // Opponents 5-7 => Draw from regularPerks
        if (opponentNum >= 5 && opponentNum <= 7) {
          return regularPerks.length > 0 ? regularPerks.shift() : 0;
        }
        // Opponent 8 => Draw from bossPerks
        if (opponentNum === 8) {
          return bossPerks.length > 0 ? bossPerks.shift() : 0;
        }
        // Opponents 9-11 => Draw from regularPerks
        if (opponentNum >= 9 && opponentNum <= 11) {
          return regularPerks.length > 0 ? regularPerks.shift() : 0;
        }
        // Opponent 12 => Draw from bossPerks
        if (opponentNum === 12) {
          return bossPerks.length > 0 ? bossPerks.shift() : 0;
        }
        // Opponent 13 and up => Always 0
        return 0;
      }
      
      var selectedOpponents = [];
      
      if (alternateFactions) {
        // Select opponents cycling through factions, with each faction getting equal representation
        // Re-shuffle faction order every 4 opponents so no repeats within a cluster
        var allCorpFactions = ['Jinteki', 'Haas-Bioroid', 'NBN', 'Weyland Consortium'];
        
        // Build a list of factions with reshuffling every 4
        var corpFactions = [];
        for (var cluster = 0; cluster < Math.ceil(gauntletLength / 4); cluster++) {
          // Shuffle factions for this cluster
          var clusterFactions = allCorpFactions.slice(); // copy array
          for (var i = clusterFactions.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var temp = clusterFactions[i];
            clusterFactions[i] = clusterFactions[j];
            clusterFactions[j] = temp;
          }
          // Add this cluster's factions to the list
          for (var i = 0; i < clusterFactions.length; i++) {
            corpFactions.push(clusterFactions[i]);
          }
        }
        
        // Trim to gauntletLength
        corpFactions = corpFactions.slice(0, gauntletLength);
        
        for (var f = 0; f < corpFactions.length; f++) {
          var faction = corpFactions[f];
          var candidateDecks = preconDecks.filter(function(d) {
            if (!isPreconEnabledForGauntlet(d)) return false;
            if (!cardSet[d.identity]) return false;
            var identity = cardSet[d.identity];
            return identity.player === corp && identity.faction === faction;
          });
          
          if (candidateDecks.length > 0) {
            var chosen = candidateDecks[Math.floor(Math.random() * candidateDecks.length)];
            var chosenIdentity = cardSet[chosen.identity];
            var opponent = {identity: parseInt(chosen.identity), cards: [], name: chosen.name || 'Unknown Deck', faction: chosenIdentity.faction || 'Unknown', URL: chosen.URL || '', hasbeendefeated: false};
            selectedOpponents.push(opponent);
            // Populate cards from precon
            for (var cc in chosen.cards) {
              if (!chosen.cards.hasOwnProperty(cc)) continue;
              var qty = chosen.cards[cc];
              for (var q = 0; q < qty; q++) {
                opponent.cards.push(parseInt(cc));
              }
            }
          }
        }
        
        // Check if we should replace the final opponent with a neutral deck
        if (gauntletLength > 0 && selectedOpponents.length === gauntletLength) {
          var neutralBossChance = (typeof gauntletConfig.neutralBossChance === 'number') 
            ? gauntletConfig.neutralBossChance 
            : 0.25;
          
          if (Math.random() < neutralBossChance) {
            // Find available neutral corp decks
            var neutralDecks = preconDecks.filter(function(d) {
              if (!isPreconEnabledForGauntlet(d)) return false;
              if (!cardSet[d.identity]) return false;
              var identity = cardSet[d.identity];
              return identity.player === corp && identity.faction === 'Neutral';
            });
            
            if (neutralDecks.length > 0) {
              // Replace the last opponent with a random neutral deck
              var chosen = neutralDecks[Math.floor(Math.random() * neutralDecks.length)];
              var chosenIdentity = cardSet[chosen.identity];
              var opponent = {identity: parseInt(chosen.identity), cards: [], name: chosen.name || 'Unknown Deck', faction: chosenIdentity.faction || 'Unknown', URL: chosen.URL || '', hasbeendefeated: false};
              
              // Populate cards from precon
              for (var cc in chosen.cards) {
                if (!chosen.cards.hasOwnProperty(cc)) continue;
                var qty = chosen.cards[cc];
                for (var q = 0; q < qty; q++) {
                  opponent.cards.push(parseInt(cc));
                }
              }
              
              // Replace the last opponent
              selectedOpponents[selectedOpponents.length - 1] = opponent;
            }
          }
        }
      } else {
        // Randomly select opponents from all factions without faction constraint
        // Allows duplicates only after cycling through all available decks
        var candidateDecks = preconDecks.filter(function(d) {
          if (!isPreconEnabledForGauntlet(d)) return false;
          if (!cardSet[d.identity]) return false;
          var identity = cardSet[d.identity];
          return identity.player === corp;
        });
        
        // Track which decks have been used to avoid repeating until all are cycled
        var availableIndices = [];
        for (var idx = 0; idx < candidateDecks.length; idx++) {
          availableIndices.push(idx);
        }
        
        for (var o = 0; o < gauntletLength && candidateDecks.length > 0; o++) {
          // If we've used all available decks, reset the pool
          if (availableIndices.length === 0) {
            for (var idx = 0; idx < candidateDecks.length; idx++) {
              availableIndices.push(idx);
            }
          }
          
          // Pick a random index from available indices
          var randomAvailablePos = Math.floor(Math.random() * availableIndices.length);
          var deckIndex = availableIndices[randomAvailablePos];
          
          // Remove this index from available pool
          availableIndices.splice(randomAvailablePos, 1);
          
          var chosen = candidateDecks[deckIndex];
          var chosenIdentity = cardSet[chosen.identity];
          var opponent = {identity: parseInt(chosen.identity), cards: [], name: chosen.name || 'Unknown Deck', faction: chosenIdentity.faction || 'Unknown', URL: chosen.URL || '', hasbeendefeated: false};
          selectedOpponents.push(opponent);
          // Populate cards from precon
          for (var cc in chosen.cards) {
            if (!chosen.cards.hasOwnProperty(cc)) continue;
            var qty = chosen.cards[cc];
            for (var q = 0; q < qty; q++) {
              opponent.cards.push(parseInt(cc));
            }
          }
        }
      }
      
      // Assign starting perks to all selected opponents
      for (var opIdx = 0; opIdx < selectedOpponents.length; opIdx++) {
        selectedOpponents[opIdx].startingPerk = getStartingPerk(opIdx + 1); // opIdx+1 for 1-indexed opponent number
      }
      
      // Generate unique 1337-speak corp names for each opponent (max 8 chars each)
      var leetCorpNames = [
        'xX_C0rP', 'D4t4.VuL', 'n3X7~g3N', 'SyS7_c0r',
        'm3G4::Cp', 'Zer0_D4w', 'c0D3.r3D', 'H4cK~Pr0',
        'F1r3|W4L', 'd4Rk_N0d', 'Gh0s7.N3', 'V1rU5~Fr',
        'cR4sH_73', 'r007|4cC', '5yS.0v3R', 'gL17cH_F',
        '4p3X~C0r', 'N0v4_53c', 'Ph4n70M.', 'cYb3R|L0',
        '7r4C3_Nu', 'pR070~M4', 'Qu4n7uM.', '5734L7H_',
        'bL4cK|1c', 'R3d_Qu33', '5p1D3r.A', 'v3C70R~9',
        'Null.p7R', 'w4RM_h0L', '1c3~Br34', 'Pr0Xy|99',
        'd33P_w3B', 'n0D3.x3C', 'Z3r0|d4Y', 'C0r3~DmP'
      ];
      
      // Shuffle the names array
      for (var i = leetCorpNames.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var temp = leetCorpNames[i];
        leetCorpNames[i] = leetCorpNames[j];
        leetCorpNames[j] = temp;
      }
      
      // Assign unique names to each opponent
      for (var opIdx = 0; opIdx < selectedOpponents.length; opIdx++) {
        selectedOpponents[opIdx].gauntletCorpName = leetCorpNames[opIdx % leetCorpNames.length];
      }
      
      // Create gauntlet state object
      var seed = Math.random().toString(36).substring(2, 15);
      var gauntletState = {
        subset: gauntletCardCounts,
        opponents: selectedOpponents,
        defeated: 0,
        defeatOrder: [], // Track the order in which opponents were defeated (array of opponent indices)
        agendaScored: 0,
        agendaStolen: 0,
        credits: gauntletConfig && gauntletConfig.startingCredits ? gauntletConfig.startingCredits : 0,
        creditsWon: 0,
        creditsWonText: "",
        gauntletLength: gauntletLength,
        allowedSets: settingsOverrides.allowedSets || [],
        seed: seed,
        shopPurchaseCount: 0
      };
      
      // Encode gauntlet state
      var encodedG = LZString.compressToEncodedURIComponent(JSON.stringify(gauntletState));
      
      // Select random runner identity
      var runnerIdentities = [];
      for (var i = 0; i < cardSet.length; i++) {
        if (cardSet[i] && cardSet[i].cardType === 'identity' && cardSet[i].player === runner) {
          // Check if this identity is allowed in gauntlet config
          if (gauntletConfig.allowedIdentities && gauntletConfig.allowedIdentities.runnerIds && gauntletConfig.allowedIdentities.runnerIds.length > 0) {
            // Only include if in the allowed list
            if (gauntletConfig.allowedIdentities.runnerIds.indexOf(i) !== -1) {
              runnerIdentities.push(i);
            }
          } else {
            // No restrictions, include all runners
            runnerIdentities.push(i);
          }
        }
      }
      
      var randomRunner = runnerIdentities[Math.floor(Math.random() * runnerIdentities.length)];
      
      // Create empty runner deck with random identity
      var runnerDeck = {identity: randomRunner, cards: []};
      var encodedR = LZString.compressToEncodedURIComponent(JSON.stringify(runnerDeck));
      
      // Use first corp opponent as starting opponent
      var corpOpponentDeck = selectedOpponents[0];
      var encodedC = LZString.compressToEncodedURIComponent(JSON.stringify(corpOpponentDeck));
      
      // Navigate to gauntlet
      window.location.href = 'gauntlet.php?r=' + encodedR + '&c=' + encodedC + '&g=' + encodedG;
    }
    

    // Wait for all scripts to load before selecting decks
    window.addEventListener('load', function() {
      // Filter Quick Game decks by side
      giromRunnerDecks = preconDecks.filter(function(d) {
        var isQuickGameDeck = d.useForQuickGame === true;
        var hasIdentity = cardSet[d.identity];
        var isRunner = hasIdentity && cardSet[d.identity].player === runner;
        return isQuickGameDeck && hasIdentity && isRunner;
      });
      giromCorpDecks = preconDecks.filter(function(d) {
        var isQuickGameDeck = d.useForQuickGame === true;
        var hasIdentity = cardSet[d.identity];
        var isCorp = hasIdentity && cardSet[d.identity].player === corp;
        return isQuickGameDeck && hasIdentity && isCorp;
      });
      
      // Select initial random decks
      selectRandomDecks();

      // Initialize settings from config
      initializeSettings();
      
      // Initialize achievements and update display
      initializeAchievements();
      updateAchievementDisplay();

      // Lock menu layout dimensions so panel swaps do not shift the UI
      lockMenuLayoutDimensions();

      // Track resolution bucket changes and auto-refresh if we cross breakpoints
      initResolutionAutoRefresh();
    });
    
    function handleMenu(option, evt) {
      // Initialize achievements if not already present
      initializeAchievements();
      
      // Register implementation so early queued clicks can be flushed
      try { window.handleMenuImpl = handleMenu; } catch(e) {}
      try { if (window._flushQueuedMenuClicks) window._flushQueuedMenuClicks(); } catch(e) {}

      var item = null;
      if (evt && evt.target) {
        item = evt.target.closest('.menu-item');
      } else {
        // Try to locate the menu item using the onclick attribute matching
        try {
          item = document.querySelector('.menu-item[onclick*="handleMenu(\'' + option + "'" + ')"]');
        } catch(e) { item = null; }
        if (!item) {
          // Fallback: find by text
          var nodes = document.querySelectorAll('.menu-item');
          for (var i=0;i<nodes.length;i++) {
            if (nodes[i].textContent.trim().toLowerCase().indexOf(option) !== -1) { item = nodes[i]; break; }
          }
        }
      }
      
      // Handle tutorial
      if (option === 'tutorial') {
        openTutorial();
        return;
      }
      
      // Handle gauntlet/tournament mode - show submenu
      if (option === 'tournament') {
        showGauntletSubmenu();
        return;
      }
      
      // Handle settings
      if (option === 'settings') {
        openSettings();
        return;
      }
      
      // Handle achievements
      if (option === 'achievements') {
        openAchievements();
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
          if (playerDeck.notes) playerJson.notes = playerDeck.notes;
          if (playerDeck.name) playerJson.name = playerDeck.name;
          if (playerDeck.URL) playerJson.url = playerDeck.URL;
          for (var cardId in playerDeck.cards) {
            var count = playerDeck.cards[cardId];
            for (var i = 0; i < count; i++) {
              playerJson.cards.push(parseInt(cardId));
            }
          }
          var aiJson = {identity: parseInt(aiDeck.identity), cards: []};
          if (aiDeck.notes) aiJson.notes = aiDeck.notes;
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
          
          window.location.href = 'decklauncher.php?p=' + playerSide + 
                                 '&' + aiSide + '=' + aiCompressed + 
                                 '&' + playerSide + '=' + playerCompressed;
        } else if (option === 'quick' && playerDeck && aiDeck) {
          // Build compressed deck strings from precon format
          var playerJson = {identity: parseInt(playerDeck.identity), cards: []};
          if (playerDeck.notes) playerJson.notes = playerDeck.notes;
          if (playerDeck.name) playerJson.name = playerDeck.name;
          if (playerDeck.URL) playerJson.url = playerDeck.URL;
          for (var cardId in playerDeck.cards) {
            var count = playerDeck.cards[cardId];
            for (var i = 0; i < count; i++) {
              playerJson.cards.push(parseInt(cardId));
            }
          }
          var aiJson = {identity: parseInt(aiDeck.identity), cards: []};
          if (aiDeck.notes) aiJson.notes = aiDeck.notes;
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
          
          window.location.href = 'engine.php?p=' + playerSide + 
                                 '&' + aiSide + '=' + aiCompressed + 
                                 '&' + playerSide + '=' + playerCompressed +
                                 '&showdeck=1';
        }
      }, 500);
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

    // Auto-refresh when switching between major responsive breakpoints
    var resolutionBucket = null;
    var reloadScheduled = false;
    function getResolutionBucket(){
      var w = window.innerWidth;
      var h = window.innerHeight;
      if (h <= 768) return 'short-height';
      if (h >= 769 && w <= 678) return 'narrow-width';
      return 'standard';
    }
    function initResolutionAutoRefresh(){
      resolutionBucket = getResolutionBucket();
      window.addEventListener('resize', function(){
        var next = getResolutionBucket();
        if (next !== resolutionBucket && !reloadScheduled){
          reloadScheduled = true;
          setTimeout(function(){ location.reload(); }, 200);
        }
      });
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

    // Settings toggle
    function openSettings(){
      var menu = document.getElementById('menu-buttons');
      var panel = document.getElementById('settings-panel');
      // If already open, act like back button
      if (menu.style.display === 'none' && panel.style.display === 'flex') {
        closeSettings();
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
    function closeSettings(){
      document.getElementById('settings-panel').style.display='none';
      document.getElementById('menu-buttons').style.display='flex';
      // Clear explicit width so menu layout can adapt on resize
      var p = document.getElementById('settings-panel');
      p.style.width='';
      p.style.maxHeight='';
    }

    // Achievements panel toggle
    function openAchievements(){
      var menu = document.getElementById('menu-buttons');
      var panel = document.getElementById('achievements-panel');
      // If already open, act like back button
      if (menu.style.display === 'none' && panel.style.display === 'flex') {
        closeAchievements();
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
      // Populate the achievements panel
      populateAchievementsPanel();
    }
    
    function closeAchievements(){
      document.getElementById('achievements-panel').style.display='none';
      document.getElementById('menu-buttons').style.display='flex';
      // Clear explicit width so menu layout can adapt on resize
      var p = document.getElementById('achievements-panel');
      p.style.width='';
      p.style.maxHeight='';
    }
    
    // Format timestamp to yy|mm|dd format
    function formatAchievementDate(timestamp) {
      if (!timestamp) return '';
      var date = new Date(timestamp);
      var yy = String(date.getFullYear()).slice(-2);
      var mm = String(date.getMonth() + 1).padStart(2, '0');
      var dd = String(date.getDate()).padStart(2, '0');
      return yy + '|' + mm + '|' + dd;
    }
    
    // Get card image path from identity ID
    function getIdentityImagePath(identityId) {
      if (!identityId || !cardSet[identityId]) return '';
      var identity = cardSet[identityId];
      if (identity && identity.imageFile) {
        return 'images/' + identity.imageFile.replace('.png', '.jpg');
      }
      return '';
    }
    
    // Populate the achievements panel with high scores and achievements
    function populateAchievementsPanel() {
      var data = getAchievements();
      
      // Populate high scores (sorted descending by score)
      var highScoresList = document.getElementById('high-scores-list');
      highScoresList.innerHTML = '';
      
      // Sort high scores by score descending, then by timestamp descending for ties (latest first)
      var sortedScores = data.highScores.slice().sort(function(a, b) {
        if (b.score !== a.score) return b.score - a.score;
        // For ties, latest timestamp first (descending)
        return new Date(b.timestamp) - new Date(a.timestamp);
      });
      
      // Check if there are any valid scores
      var hasValidScores = sortedScores.some(function(hs) { return hs.score > 0; });
      
      if (!hasValidScores) {
        var emptyRow = document.createElement('div');
        emptyRow.className = 'high-score-row high-score-empty';
        emptyRow.textContent = 'No high scores yet. Complete a Gauntlet!';
        highScoresList.appendChild(emptyRow);
      } else {
        var displayCount = 0;
        for (var i = 0; i < sortedScores.length && displayCount < 3; i++) {
          var hs = sortedScores[i];
          if (hs.score <= 0) continue; // Skip empty scores
          displayCount++;
          
          var row = document.createElement('div');
          row.className = 'high-score-row';
          
          // Identity thumbnail
          var thumb = document.createElement('div');
          thumb.className = 'high-score-thumb';
          if (hs.identity) {
            var imgPath = getIdentityImagePath(hs.identity);
            if (imgPath) {
              var img = document.createElement('img');
              img.src = imgPath;
              img.alt = '';
              thumb.appendChild(img);
            }
          }
          row.appendChild(thumb);
          
          // Score
          var scoreEl = document.createElement('div');
          scoreEl.className = 'high-score-score';
          scoreEl.textContent = hs.score;
          row.appendChild(scoreEl);
          
          // Date
          var dateEl = document.createElement('div');
          dateEl.className = 'high-score-date';
          dateEl.textContent = formatAchievementDate(hs.timestamp);
          row.appendChild(dateEl);
          
          highScoresList.appendChild(row);
        }
      }
      
      // Populate achievements
      var achievementsList = document.getElementById('achievements-list');
      achievementsList.innerHTML = '';
      
      for (var i = 0; i < data.achievements.length; i++) {
        var achievement = data.achievements[i];
        
        var row = document.createElement('div');
        row.className = 'achievement-row' + (achievement.achieved ? '' : ' achievement-incomplete');
        row.title = achievement.description;
        
        // Achievement name
        var nameEl = document.createElement('div');
        nameEl.className = 'achievement-name';
        nameEl.textContent = achievement.name;
        row.appendChild(nameEl);
        
        // Achievement date (only if achieved)
        var dateEl = document.createElement('div');
        dateEl.className = 'achievement-date';
        if (achievement.achieved && achievement.achievedAt) {
          dateEl.textContent = formatAchievementDate(achievement.achievedAt);
        }
        row.appendChild(dateEl);
        
        achievementsList.appendChild(row);
      }
    }

    // Data modal helper functions
    var dataModalCallback = null;
    
    function showDataModal(title, message, buttons) {
      var modal = document.getElementById('data-modal');
      var titleEl = document.getElementById('data-modal-title');
      var messageEl = document.getElementById('data-modal-message');
      var buttonsEl = document.getElementById('data-modal-buttons');
      
      titleEl.textContent = title;
      messageEl.innerHTML = message;
      
      // Build buttons
      buttonsEl.innerHTML = '';
      buttons.forEach(function(btn) {
        var button = document.createElement('button');
        button.className = 'data-modal-btn' + (btn.secondary ? ' secondary' : '');
        button.textContent = btn.text;
        button.onclick = function() {
          closeDataModal();
          if (btn.callback) btn.callback();
        };
        buttonsEl.appendChild(button);
      });
      
      modal.style.display = 'flex';
    }
    
    function closeDataModal() {
      var modal = document.getElementById('data-modal');
      modal.style.display = 'none';
    }
    
    function showDataAlert(title, message, callback) {
      showDataModal(title, message, [
        { text: 'OK', callback: callback }
      ]);
    }
    
    function showDataConfirm(title, message, onConfirm, onCancel) {
      showDataModal(title, message, [
        { text: 'CANCEL', secondary: true, callback: onCancel },
        { text: 'CONFIRM', callback: onConfirm }
      ]);
    }

    // Export all localStorage data to a JSON file
    function exportLocalData() {
      try {
        var exportData = {};
        
        // Iterate through all localStorage keys
        for (var i = 0; i < localStorage.length; i++) {
          var key = localStorage.key(i);
          // Only export chiriboga-related keys
          if (key && key.indexOf('chiriboga') === 0) {
            exportData[key] = localStorage.getItem(key);
          }
        }
        
        var keyCount = Object.keys(exportData).length;
        if (keyCount === 0) {
          showDataAlert('NO DATA', 'There is no saved data to export.');
          return;
        }
        
        // Create the export object with metadata
        var exportWrapper = {
          version: 1,
          exportDate: new Date().toISOString(),
          data: exportData
        };
        
        // Create and download the file
        var blob = new Blob([JSON.stringify(exportWrapper, null, 2)], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'chiriboga-backup-' + new Date().toISOString().slice(0, 10) + '.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        showDataAlert('EXPORT COMPLETE', 'Your data has been saved to a backup file.');
        console.log('Data exported successfully');
      } catch (e) {
        console.error('Failed to export data:', e);
        showDataAlert('EXPORT FAILED', 'Could not export data: ' + e.message);
      }
    }
    
    // Import localStorage data from a JSON file
    function importLocalData(event) {
      var file = event.target.files[0];
      if (!file) return;
      
      // Limit file size to 5MB to prevent abuse
      if (file.size > 5 * 1024 * 1024) {
        showDataAlert('FILE TOO LARGE', 'Backup file exceeds the 5MB size limit.');
        event.target.value = '';
        return;
      }
      
      var reader = new FileReader();
      reader.onload = function(e) {
        try {
          var importWrapper = JSON.parse(e.target.result);
          
          // Validate the import file
          if (!importWrapper || !importWrapper.data || typeof importWrapper.data !== 'object') {
            throw new Error('Invalid backup file format');
          }
          
          // Filter to only allow chiriboga-prefixed keys (security)
          var validKeys = {};
          var skippedKeys = 0;
          for (var key in importWrapper.data) {
            if (importWrapper.data.hasOwnProperty(key)) {
              if (typeof key === 'string' && key.indexOf('chiriboga') === 0) {
                validKeys[key] = importWrapper.data[key];
              } else {
                skippedKeys++;
              }
            }
          }
          
          var keyCount = Object.keys(validKeys).length;
          if (keyCount === 0) {
            throw new Error('No valid data found in backup');
          }
          
          // Build confirmation message
          var exportDate = importWrapper.exportDate ? new Date(importWrapper.exportDate).toLocaleDateString() : 'Unknown';
          var message = 'Restore data from this backup?';
          message += '<br><br>Backup date: <strong>' + exportDate + '</strong>';
          message += '<br><br><span style="color:var(--crt-green-muted);font-size:11px;">This will overwrite your current settings and saves.</span>';
          
          showDataConfirm('RESTORE DATA', message, function() {
            // Import each validated key
            for (var key in validKeys) {
              if (validKeys.hasOwnProperty(key)) {
                localStorage.setItem(key, validKeys[key]);
              }
            }
            
            showDataAlert('IMPORT COMPLETE', 'Your data has been restored.<br><br>The page will now reload.', function() {
              location.reload();
            });
          });
        } catch (err) {
          console.error('Failed to import data:', err);
          showDataAlert('IMPORT FAILED', 'Could not restore data: ' + err.message);
        }
      };
      
      reader.readAsText(file);
      
      // Reset the file input so the same file can be selected again
      event.target.value = '';
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
      
      // Capture current width/height of menu buttons before hiding (for short resolutions)
      var rect = menu.getBoundingClientRect();
      var w = rect.width;
      var h = rect.height;
      menu.style.display='none';
      panel.style.width = w + 'px';
      panel.style.height = h + 'px';
      panel.style.maxHeight = h + 'px';
      panel.style.minWidth = w + 'px';
      panel.style.minHeight = h + 'px';
      panel.style.display='flex';
    }
    
    function closeTutorial(){
      var screenContent = document.querySelector('.screen-content');
      document.getElementById('tutorial-panel').style.display='none';
      document.getElementById('menu-buttons').style.display='flex';
      // Clear explicit width/height so menu layout can adapt on resize
      var p = document.getElementById('tutorial-panel');
      p.style.width='';
      p.style.height='';
      p.style.minWidth='';
      p.style.minHeight='';
      p.style.maxHeight='';
      screenContent.style.width='';
      screenContent.style.height='';
      screenContentWidth = null;
      screenContentHeight = null;
    }
    
    // Gauntlet submenu toggle
    function showGauntletSubmenu() {
      var mainBtn = document.getElementById('gauntlet-main');
      var submenu = document.getElementById('gauntlet-submenu');
      if (mainBtn && submenu) {
        mainBtn.style.display = 'none';
        submenu.style.display = 'flex';
        // Update continue button state based on localStorage
        updateGauntletContinueButton();
      }
    }
    
    function hideGauntletSubmenu() {
      var mainBtn = document.getElementById('gauntlet-main');
      var submenu = document.getElementById('gauntlet-submenu');
      if (mainBtn && submenu) {
        mainBtn.style.display = 'block';
        submenu.style.display = 'none';
      }
    }
    
    // Check if a saved gauntlet exists in localStorage
    function hasGauntletSave() {
      try {
        var savedJson = localStorage.getItem('chiriboga-gauntlet-save');
        if (savedJson) {
          var saveData = JSON.parse(savedJson);
          // Check if save has required data
          return saveData && saveData.r && saveData.g;
        }
      } catch (e) {
        console.error("Error checking gauntlet save:", e);
      }
      return false;
    }
    
    // Update the continue button state
    function updateGauntletContinueButton() {
      var continueBtn = document.getElementById('gauntlet-continue-btn');
      if (continueBtn) {
        if (hasGauntletSave()) {
          continueBtn.classList.remove('disabled');
        } else {
          continueBtn.classList.add('disabled');
        }
      }
    }
    
    // Handle Continue button click
    function handleGauntletContinue(evt) {
      // Initialize achievements if not already present
      initializeAchievements();
      
      var continueBtn = document.getElementById('gauntlet-continue-btn');
      if (!hasGauntletSave() || continueBtn.classList.contains('disabled')) {
        return false;
      }
      
      try {
        var savedJson = localStorage.getItem('chiriboga-gauntlet-save');
        var saveData = JSON.parse(savedJson);
        
        // Navigate to gauntlet.php with saved parameters
        var gauntletUrl = 'gauntlet.php?r=' + saveData.r + '&g=' + saveData.g;
        window.location.href = gauntletUrl;
      } catch (e) {
        console.error("Error loading gauntlet save:", e);
        var btn = evt.target;
        btn.innerHTML = 'ERROR';
        setTimeout(function() {
          btn.innerHTML = 'CONTINUE';
        }, 1500);
      }
    }
    
    function handleGauntletNew(evt) {
      // Initialize achievements if not already present
      initializeAchievements();
      
      // Check if there are enough gauntlet precons before launching
      var gauntletCorpDecks = preconDecks.filter(function(d) {
        if (!isPreconEnabledForGauntlet(d)) return false;
        if (!cardSet[d.identity]) return false;
        var identity = cardSet[d.identity];
        return identity.player === corp;
      });
      
      // Need at least gauntletLength precons total
      var gauntletLength = settingsOverrides.gauntletLength || 4;
      var newBtn = evt.target;
      
      if (gauntletCorpDecks.length < gauntletLength) {
        newBtn.innerHTML = 'MISSING';
        setTimeout(function() {
          newBtn.innerHTML = 'NEW';
        }, 1500);
        return;
      }
      
      // Need at least 1 precon for each corp faction
      var requiredFactions = ['Jinteki', 'Haas-Bioroid', 'NBN', 'Weyland Consortium'];
      var missingFaction = false;
      for (var i = 0; i < requiredFactions.length; i++) {
        var faction = requiredFactions[i];
        var hasFaction = gauntletCorpDecks.some(function(d) {
          return cardSet[d.identity].faction === faction;
        });
        if (!hasFaction) {
          missingFaction = true;
          break;
        }
      }
      
      if (missingFaction) {
        newBtn.innerHTML = 'MISSING';
        setTimeout(function() {
          newBtn.innerHTML = 'NEW';
        }, 1500);
        return;
      }
      
      LaunchGauntlet();
    }
    
    // Hide gauntlet submenu when clicking elsewhere
    document.addEventListener('click', function(e) {
      var container = document.getElementById('gauntlet-container');
      if (container && !container.contains(e.target)) {
        hideGauntletSubmenu();
      }
    });
    
    function startTutorial(mentorIndex) {
      // Initialize achievements if not already present
      initializeAchievements();
      
      var tutorials = [
        { side: 'r', mentor: 0 },
        { side: 'r', mentor: 1 },
        { side: 'r', mentor: 2 },
        { side: 'r', mentor: 3 },
        { side: 'r', mentor: 4 },
        { side: 'c', mentor: 5 }
      ];
      var t = tutorials[mentorIndex];
      if (!t) return;
      window.location.href = 'engine.php?p=' + t.side + '&mentor=' + t.mentor + '&t=1';
    }
  </script>
</body>
</html>