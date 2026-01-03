# Netrunner: Solo Mode

A single-player implementation of Android: Netrunner with AI opponents and a refined cyberpunk interface. Built on the [Chiriboga engine](https://github.com/bobtheuberfish/chiriboga) by bobtheuberfish.

![PHP](https://img.shields.io/badge/php-7.4%2B-blue)
![License](https://img.shields.io/badge/license-GPL--3.0-green)

## Features

- 🤖 **AI Opponent** - Sophisticated Corp AI that builds servers, advances agendas, and makes strategic decisions
- 🎮 **Multiple Game Modes** - Quick Game, Custom Game, Gauntlet Mode, and Tutorial
- 🎨 **Cyberpunk Interface** - Retro terminal aesthetic with CRT effects and animations
- 📦 **Card Support** - System Gateway, System Update 2021, Midnight Sun, and Elevation sets
- 🏆 **Achievements System** - Track your progress and unlock rewards
- 🔧 **Deck Builder** - Visual deck construction with card filters and sorting

## Quick Start

### Requirements
- PHP 7.4 or higher (PHP 8.1+ recommended)
- A web server (Apache/Nginx) or PHP's built-in server

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/chiriboga.git
   cd chiriboga
   ```

2. **Start the development server**
   ```bash
   php -S localhost:8000
   ```

3. **Open in browser**
   ```
   http://localhost:8000
   ```

### For Production (Apache/Nginx)

1. Upload all files to your web server
2. Ensure the `.htaccess` file is present (Apache users)
3. Point your domain to the directory containing `index.php`
4. Access via your domain URL

**Note:** The included `.htaccess` provides security headers and optimizations. For Nginx, refer to the equivalent configuration in the comments.

## Game Modes

### Quick Game
Jump straight into a match with pre-selected runner and corp decks.

### Custom Game
Choose your identity, build or import custom decks, and configure game settings.

### Gauntlet Mode
Face a series of increasingly difficult AI opponents with a limited card pool. Earn credits by winning to buy more card packs and improve your deck.

### Tutorial
Learn the basics of Netrunner through guided scenarios.

## How to Play

1. Select a game mode from the main menu
2. Choose your Runner identity
3. Build a deck or select a preconstructed deck
4. Click "Launch Game" to start playing
5. Use clicks to perform actions during your turn
6. Make runs on Corp servers to steal agendas
7. First to 7 agenda points wins!

For complete rules, see the [Netrunner Comprehensive Rules](https://nullsignal.games/wp-content/uploads/2024/08/Null-Signal-Games-Netrunner-Comprehensive-Rules-v25.08.pdf).

## Credits

### Original Engine
**Chiriboga** - Developed by [bobtheuberfish](https://github.com/bobtheuberfish)  
Source: https://github.com/bobtheuberfish/chiriboga

### Solo Mode Extension
Developed by [DrBo6](https://github.com/drbo6)  
Enhanced interface, game modes, and Gauntlet system

### Preconstructed Decks
- Girometics SG+SU21 and NSG Core precons
- Additional precons curated by DrBo6

### Special Thanks
Testers: BadEpsilon, bowlsley, D-Smith, eniteris, Kwaice, Mentlegen, olompumpa, R41B, saff, Saintis, Ysengrin

## Legal

*Netrunner* and *Android* are trademarks of Fantasy Flight Publishing, Inc. and/or Wizards of the Coast LLC. This is a fan-made project and is not affiliated with or endorsed by FFG, WotC, or Null Signal Games.

Card art and symbols are property of Null Signal Games and used under [CC BY-ND 4.0](https://creativecommons.org/licenses/by-nd/4.0/).

---

# Developer Documentation

# Debugging and Testing Guide for Chiriboga

This guide explains how to create specific board states, enable debugging features, and test scenarios in the Netrunner implementation.

## Enabling Debug Mode

### Quick Setup

Add or modify these lines at the start of your game session (in browser console or in `decks.js` around line 712):

```javascript
enableDebugMenu = true;  // Shows the debug menu button in the UI
debugging = true;        // Enables detailed logging and pauses on errors
viewAllFronts = false;   // Set true to see all card faces (changes AI behavior)
```

### What Each Flag Does

| Flag | Effect |
|------|--------|
| `enableDebugMenu` | Displays a debug menu button in the game interface |
| `debugging` | Pauses execution on `console.error()` calls; enables extra AI logging |
| `viewAllFronts` | Shows card fronts for all cards when zoomed (note: AI plays differently when enabled) |

## Using the Debug Menu

Once `enableDebugMenu = true`, a debug button appears in the UI. It provides these functions:

| Function | Description |
|----------|-------------|
| Add Click | Gives the viewing player +1 click |
| Add Credit | Gives the viewing player +1 credit |
| Draw Card | Draws a card from stack/R&D to hand |
| Win Game | Immediately wins the game for the viewing player |
| Lose Game | Immediately loses the game for the viewing player |
| Add Card to Hand | Spawns any card from a dropdown menu into your hand |

## Creating a Test Board State

The codebase provides two functions for setting up specific board states: `RunnerTestField()` and `CorpTestField()`. These are called in `decks.js` after the normal deck loading.

### Card Numbers

Cards are identified by their **set number** (a 5-digit integer). You'll need to look these up in your card set definition file. Examples from the codebase:

- `31002` - A runner identity
- `30032` - A runner card (in heap)
- `31004` - A runner card (in stack)
- `30035` - A corp identity
- `30073`, `30072`, `30047` - Corp cards

### RunnerTestField Parameters

```javascript
RunnerTestField(
  identity,           // Set number for runner identity (e.g., 31002)
  heapCards,          // Array of set numbers for cards in heap
  stackCards,         // Array of set numbers for cards in stack
  gripCards,          // Array of set numbers for cards in grip (hand)
  installed,          // Array of set numbers for installed cards (auto-sorts by type)
  stolen,             // Array of set numbers for stolen agendas
  cardBackTexturesRunner,
  glowTextures,
  strengthTextures
);
```

### CorpTestField Parameters

```javascript
CorpTestField(
  identity,           // Set number for corp identity (e.g., 30035)
  archivesCards,      // Array of set numbers for cards in Archives
  rndCards,           // Array of set numbers for R&D (top card is last in array)
  hqCards,            // Array of set numbers for HQ (hand)
  archivesInstalled,  // Array of set numbers for ice/upgrades on Archives
  rndInstalled,       // Array of set numbers for ice/upgrades on R&D
  hqInstalled,        // Array of set numbers for ice/upgrades on HQ
  remotes,            // Array of arrays - each inner array is a remote server
  scored,             // Array of set numbers for scored agendas
  cardBackTexturesCorp,
  glowTextures,
  strengthTextures
);
```

### Example: Setting Up a Test Scenario

Paste this code in `decks.js` at line 711 (where it says `//PASTE REPLICATION CODE HERE`):

```javascript
debugging = true; // Enable detailed logging

RunnerTestField(31002,         // Identity
    [30032],                   // Heap: one card
    [31004,31004,31004,31004], // Stack: four copies of a card
    [31037,31037,31037],       // Grip: three cards in hand
    [30014,31008],             // Installed: two cards (auto-sorted by type)
    [],                        // Stolen: no agendas yet
    cardBackTexturesRunner,glowTextures,strengthTextures);

CorpTestField(30035,           // Identity
    [],                        // Archives: empty
    [30073,30072,30047],       // R&D: three cards
    [30065,31061,30039],       // HQ: three cards in hand
    [],                        // Archives ice/upgrades: none
    [31067],                   // R&D ice: one piece of ice
    [31067],                   // HQ ice: one piece of ice
    [[30047,30047]],           // Remotes: one server with two cards
    [],                        // Scored: no agendas
    cardBackTexturesCorp,glowTextures,strengthTextures);
```

### Setting Card Properties After Creation

After calling the test field functions, you can modify individual card properties:

```javascript
// Rez ice
corp.archives.ice[0].rezzed = true;
corp.RnD.ice[0].rezzed = true;
corp.HQ.ice[0].rezzed = true;
corp.remoteServers[0].ice[0].rezzed = true;

// Make a card known to the runner
corp.remoteServers[0].root[0].knownToRunner = true;

// Add advancement counters
corp.remoteServers[0].root[0].advancement = 2;

// Modify credits
GainCredits(runner, 5);
GainCredits(corp, 11);

// Add tags
AddTags(2);

// Set clicks remaining
runner.clickTracker = 3;
corp.clickTracker = 2;

// Set bad publicity
corp.badPublicity = 2;

// Set core damage
runner.coreDamage = 1;

// Change the current phase
ChangePhase(phases.corpActionMain);
ChangePhase(phases.runnerStartResponse);

// Start a run directly
MakeRun(corp.remoteServers[0]);
attackedServer = corp.RnD;
ChangePhase(phases.runApproachServer); // Skip all ice
```

### Common Phases

| Phase | Description |
|-------|-------------|
| `phases.corpMulligan` | Corp mulligan decision |
| `phases.runnerMulligan` | Runner mulligan decision |
| `phases.corpStartDraw` | Start of corp turn (mandatory draw) |
| `phases.corpActionMain` | Corp's action phase |
| `phases.corpDiscardStart` | Corp discard phase |
| `phases.runnerStartResponse` | Start of runner turn |
| `phases.runnerEndOfTurn` | End of runner turn |
| `phases.runApproachServer` | Runner approaching server (after ice) |

## Capturing Current Game State

### Using ReproductionCode()

At any point during a game, you can generate code that recreates the current board state:

```javascript
// In browser console:
console.log(ReproductionCode(true));  // true = include full state (credits, clicks, phase)
console.log(ReproductionCode(false)); // false = just card positions
```

This outputs JavaScript code you can paste into `decks.js` to reproduce the exact game state.

### Downloading the Full Log

The game captures all console output. To download it (including hidden information and reproduction code):

```javascript
DownloadCapturedLog();
```

This creates a file named `chiriboga-log-[timestamp].txt` containing:

- All console.log output from the game session
- Hidden information (contents of remote servers)
- ReproductionCode for recreating the state
- Version reference

## AI Debugging

### Viewing AI Decision Logs

Both AI modules (`ai_corp.js` and `ai_runner.js`) have internal logging via `_log()`:

```javascript
// In the AI class:
_log(message) {
  console.log("AI: " + message);  // Comment this line to suppress
}
```

When `debugging = true`, additional decision path information is logged for damage calculations and advancement planning.

### Key AI Debug Output Examples

The AI logs decisions like:

- `"AI: I will rez the approached ice"`
- `"AI: Nothing good to do..."`
- `"AI: Could do X damage on breach"`
- `"AI: Server protection scores: {...}"`
- `"AI: Suspected HQ: [card info] (info HQ score: X.X)"`

## Forcing AI Actions

The AI uses a `preferred` property to override its normal decision-making. You can set this to force specific actions for testing.

### Basic Preference Structure

```javascript
player.AI.preferred = {
  command: "commandName",    // The action type
  keyName: value,            // The target (card, server, etc.)
  nextPrefs: { ... }         // Optional: chained preference for sub-decisions
};
```

### Finding Card References

To find a specific card by name at runtime:

```javascript
// Search for a card in the card set by name
for (var id in cardSet) {
  if (cardSet[id].title && cardSet[id].title.toLowerCase().includes("hedge")) {
    console.log(id, cardSet[id].title);
  }
}

// Find a card already in hand
var targetCard = runner.grip.find(c => c.title === "Sure Gamble");
var corpCard = corp.HQ.cards.find(c => c.title === "Hedge Fund");

// Find an installed card
var installedCard = runner.rig.programs.find(c => c.title === "Corroder");
```

### Server References

| Server | Reference |
|--------|-----------|
| HQ | `corp.HQ` |
| R&D | `corp.RnD` |
| Archives | `corp.archives` |
| Remote 1 | `corp.remoteServers[0]` |
| Remote 2 | `corp.remoteServers[1]` |

### Corp AI Preferences

```javascript
// Play an operation
corp.AI.preferred = {
  command: "play",
  cardToPlay: corp.HQ.cards[0]
};

// Install a card in a specific server
corp.AI.preferred = {
  command: "install",
  cardToInstall: corp.HQ.cards[0],
  serverToInstallTo: corp.remoteServers[0]  // or corp.HQ, corp.RnD, null for new remote
};

// Advance a card
corp.AI.preferred = {
  command: "advance",
  cardToAdvance: corp.remoteServers[0].root[0]
};

// Rez a card
corp.AI.preferred = {
  command: "rez",
  cardToRez: corp.HQ.ice[0]
};

// Score an agenda
corp.AI.preferred = {
  command: "score",
  cardToScore: corp.remoteServers[0].root[0]
};

// Trigger an ability
corp.AI.preferred = {
  command: "trigger",
  cardToTrigger: someActiveCard
};

// Trash a runner's resource (when runner is tagged)
corp.AI.preferred = {
  command: "trash",
  cardToTrash: runner.rig.resources[0]
};

// Set trace strength
corp.AI.preferred = {
  command: "trace",
  strengthToIncrease: 5
};
```

### Runner AI Preferences

```javascript
// Play an event
runner.AI.preferred = {
  command: "play",
  cardToPlay: runner.grip[0]
};

// Make a run on a specific server
runner.AI.preferred = {
  command: "run",
  serverToRun: corp.RnD
};

// Install a card (optionally on a host)
runner.AI.preferred = {
  command: "install",
  cardToInstall: runner.grip[0],
  hostToInstallTo: someHostCard  // optional, for cards that host on others
};

// Trigger an ability
runner.AI.preferred = {
  command: "trigger",
  cardToTrigger: runner.rig.programs[0],
  abilityAlt: 0  // optional: which ability if card has multiple
};

// Trash a card
runner.AI.preferred = {
  command: "trash",
  cardToTrash: runner.rig.resources[0]
};
```

### Chaining Preferences for Sub-Decisions

Some actions require follow-up choices (e.g., playing a run event requires choosing a server). Use `nextPrefs` to chain preferences:

```javascript
// Play an event that targets a server (like Shred, Legwork, etc.)
runner.AI.preferred = {
  command: "play",
  cardToPlay: runner.grip.find(c => c.title === "Shred"),
  nextPrefs: {
    chooseServer: corp.RnD
  }
};

// Corp installs ice, then needs to choose position
corp.AI.preferred = {
  command: "install",
  cardToInstall: corp.HQ.cards.find(c => c.cardType === "ice"),
  serverToInstallTo: corp.HQ,
  nextPrefs: {
    // Additional preferences for ice position if needed
  }
};
```

### The `chooseServer` Special Preference

The `chooseServer` property works regardless of the current phase—useful for any server selection:

```javascript
// Force server choice in any context
runner.AI.preferred = {
  chooseServer: corp.archives
};
```

### Complete Example: Testing a Specific Card

```javascript
debugging = true;

// Set up runner with the card you want to test
RunnerTestField(31002,
    [],                      // heap
    [30001, 30001, 30001],   // stack
    [XXXXX],                 // grip - your test card's set number
    [],                      // installed
    [],                      // stolen
    cardBackTexturesRunner, glowTextures, strengthTextures);

// Give runner resources
runner.creditPool = 20;
runner.clickTracker = 4;

// Set up the turn
playerTurn = runner;
ChangePhase(phases.runnerActionMain);

// Force AI to play the card targeting R&D
runner.AI.preferred = {
  command: "play",
  cardToPlay: runner.grip[0],
  nextPrefs: {
    chooseServer: corp.RnD
  }
};
```

### Preference Reference Table

| Command | Key(s) | Player | Description |
|---------|--------|--------|-------------|
| `play` | `cardToPlay` | Both | Play an event/operation from hand |
| `install` | `cardToInstall`, `serverToInstallTo` | Corp | Install a card |
| `install` | `cardToInstall`, `hostToInstallTo` | Runner | Install a card |
| `run` | `serverToRun` | Runner | Initiate a run |
| `advance` | `cardToAdvance` | Corp | Advance an installed card |
| `rez` | `cardToRez` | Corp | Rez an installed card |
| `score` | `cardToScore` | Corp | Score an agenda |
| `trigger` | `cardToTrigger`, `abilityAlt` | Both | Use a card ability |
| `trash` | `cardToTrash` | Both | Trash a card |
| `trace` | `strengthToIncrease` | Corp | Set trace strength |
| (any) | `chooseServer` | Runner | Select a server (works in any phase) |
| (any) | `nextPrefs` | Both | Chain another preference for sub-decisions |

## Debugging Tips

1. **Start simple**: Begin with `enableDebugMenu = true` and use the UI buttons before writing test field code.

2. **Use ReproductionCode**: When you encounter a bug, immediately run `ReproductionCode(true)` in the console to capture the state before it changes.

3. **Check the console**: Open browser dev tools (F12) to see AI reasoning and error messages.

4. **Slow down AI**: Uncomment this line in `decks.js` to slow AI actions for observation:
   ```javascript
   mainLoopDelay = 50; // Default is faster; increase for debugging
   ```

5. **Test specific interactions**: Use `ChangePhase()` and `MakeRun()` to jump directly to the game state you want to test.

6. **Watch for LogError**: The `LogError()` function outputs to `console.error()`. When `debugging = true`, these will pause execution.

## Quick Reference: Browser Console Commands

```javascript
// Enable debug features at runtime
enableDebugMenu = true;
ShowDebugMenuButtonIfEnabled();
debugging = true;

// Check game state
console.log(runner.grip);           // Runner's hand
console.log(corp.HQ.cards);         // Corp's hand
console.log(corp.remoteServers);    // All remote servers
console.log(currentPhase.title);    // Current game phase

// Capture state
console.log(ReproductionCode(true));
DownloadCapturedLog();

// Modify state
GainCredits(runner, 10);
GainCredits(corp, 10);
runner.clickTracker = 4;
corp.clickTracker = 3;
```