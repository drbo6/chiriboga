# Netrunner: Solo Mode

A single-player implementation of Android: Netrunner with AI opponents, a rogue-lite gauntlet mode and a refined cyberpunk interface. Built on the [Chiriboga engine](https://github.com/bobtheuberfish/chiriboga) by bobtheuberfish.

Try it here: https://chiriboga.cronbach.com

![PHP](https://img.shields.io/badge/php-7.4%2B-blue)
![License](https://img.shields.io/badge/license-GPL--3.0-green)

## Features

- 🤖 **AI Opponent** - Simple Corp AI that builds servers, advances agendas, and makes strategic decisions
- 🎮 **Multiple Game Modes** - Quick Game, Custom Game, Gauntlet Mode, and Tutorial
- 📚 **Precon support** - Preconstructed decks for beginners to try out
- 🎲 **Rogue-lite** - Face sequential opponents with a limited card pool, earn credits, and build your deck

## How do I report bugs?

Post them here: https://github.com/drbo6/chiriboga/issues

If you do, there are two things that help a lot:
- If there is a relevant game state, please open the menu and click on "Download Debug Log". If you upload that txt file here, I can restore your game state on my computer.
- If you are on a computer, open the developer console (F12 in most browsers) and copy-paste any errors here.

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

# Missing Cards

The following Elevation cards are not yet implemented (9 cards):

## Jinteki
- **35047** - PT Untaian: Life's Building Blocks (Identity)
- **35048** - Proprionegation (Agenda) (Hidden Funds)
- **35056** - Mitra Aman (Upgrade - Clone) (Hidden Funds)

## NBN
- **35057** - Nebula Talent Management: Making Stars (Identity - Flip)
- **35058** - Synapse Global: Faster than Thought (Identity)
- **35059** - Embedded Reporting (Agenda - Initiative) (Fine Print)
- **35060** - Next Big Thing (Agenda - Initiative) (Fine Print)
- **35065** - Bigger Picture (Operation - Gray Ops) (Fine Print)
- **35066** - IP Enforcement (Operation - Gray Ops) (Fine Print)

I'll get to these when I have a minute.

# Setup

1. Clone the repository
2. Download the card images from [chiriboga.cronbach.com/images/images.zip](https://chiriboga.cronbach.com/images/images.zip)
3. Extract the zip into the project root (should create an `images/` folder)

> **Note:** Card images are not included in this repository due to licensing. See `LICENSE.txt` in the zip for attribution details.

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

The codebase provides two functions for setting up specific board states: `RunnerTestField()` and `CorpTestField()`. 

**These are called in `decks.js` after the normal deck loading**. There is a condition set to false that you can set to true to enable it and see it in action. (Just start a game to see the board state that is present there.)

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

## Debug URLs

The application leverages URI (Uniform Resource Identifier) as the primary mechanism for handling data transfer and state management. This URI-based approach provides a significant advantage for debugging workflows as you can store a URL to revist it later.

For example, launch the following to load a Gauntlet prior to the final boss:

```
gauntlet.php?c=N4IglgJgpgdgLmOBPEAuAzABk%20grAGhAGMBDAJwgGc0BtLHANn3vQHZns2Od2WAObugH9BATjHcALJikz6kgIxSl8gExT19XOm7bdO%20g0ncjJ3tgbnMlk8Oys59x5lbH6r7qwLvv97wsxFZgCg9BCVEM1I4MCowIMQhMCmMOsUgIZ063FUhlEAXUIYEgBbKDQQACUAV0oACygIAAIAGRJqmCI6kEIAMxIiBAB7GAqACRISSgBaACEwIbIhyB6QAFVKloq6uDgAB0pUAHojmCg4Mg6zigAjADoiIZKj2CPoIgBrABswSjgjoiiCB8TDaPjTSQMEisCG4KAkaY3PiiBGqVR8VhQUQ3Ii4TA3KBHaaUADmxOqqgU0z2ZCgRF%20Cxg02glDAJKZl3qjWmX3anW6hDqUwJsGgvXhcEaaH6X0oUEIf3ICBgJIAClAyB80EwQCS%20XAvucAMKLPYAOVK5VQupaClYRDGAH0AGIgAC%20QA&r=N4IglgJgpgdgLmOBPEAuAzABmwRgDQgDGAhgE4QDOaA2ltunnZgCyPYtvYCsnATK3V4AOPiMFjsvAJx8ZgufUycsypXXT51m+tszpeyg+qP0G6s6eUD61vQJz1GDjhuxjnchzgDsTzNL8A13QANj90X2DIh3R3PTjY8M89KQBdAF8gA&g=N4IgzgrgRmCmAuIBcoDMAGTrkEYA0IGmALMgEwFHoCsulmOAbHYQwOwtFmlL6vpkAHMlT0BAThFiMU-qhyyiqMoqzYkouT03ysnHCX2ZhvSgfSTThAzg5XdOS313cjZSxWvpUzewdR2nrqoJjr+HgC+BAD2AA6x0QB2sInwYMgA2qAAlgAmKfDZ8ACeIgZs6AQAxgCGAE656UgZRMT4reJ4HV2Y1KJEfT00-b2MQ9RjA5OYjJVEjBTzizPL6IwjaxvrQ4zEO3vzBzPTayeMnfMXM1fobKt3Qw9EbEe3r2zUj5-P3waMk+V2oCusDdE9yosISC3tCPrDfrdBLCkWCLgZBHN0Zj0IIgTicABdAiJGoAW1gyBAAGVYtkANawAAE0TqjKp8Dq9IpBAAZjUqoUkpSAOqwYoAGxqiVyjIAwkkwCzChBSSACABVABKABlKQALeDwWJgJAAelNyQ5EESyQaUAAdFVoqTTSlTfkqnTxdkwPBTRhUFVclVbABaGrBsih4jnKqhqCwQRx4g8wSwajUHCOHk8tim0NgADmBYgZBw8Yg2XFuWyiWLNXjCHgsDqoYA7izqwXaQzQyyCxyuWqQHqamAEyl8jzYDVm7lkFbYARffVCnWAAotunISogQs1a3wcUIeV1WIAOTJFKQIAAHgANAD6svQdXXICiOXyqSKpQ0WE+EBagaJoWiwE4AiGSClDYKCUSweDvEQ1AbhQoZiDmEhMPQNp0PaEhVmIVZBgGDYSJmV5dh2WD5homY6LWRCKkebCXked5vkwOEfmhXDdD4gwBJwyFhN4kTiH6QTJJwgFTmhf55LRNZxCJEASXJSlNQgMA9VgGVtQPRIqj1Yc+QFbIhRvAAJGox1DAAhCy6miPJhy1XUbwNI0TXNS06mtW1cgdJ0XTdD0vR9P0qnEXIMT6QRo0YGo2GjagZ3jQRxAbMghDYWBxCgKpqHQBN8yLEsy1DWI6lgKofQsxJQ3yMBskLRr-J0vTQ0la1jOHUdx1gSdYGnWc9IXfyl3AeBV1rQtNzqbcNAIfdD2PeBTwvK9KRfOpUAAPwAEVJd9PxAPICl-ERej4YDGkyJREOoYiyNe8ZXmoD6Ps4mgfozcYTgmAHxgY6hQdBp7EIWHZVm2eYfsYBGoahm5zi+dGeP48TsfkkTod0fG-gRRH5NkhSCZRP4lPOVT1OvEAABUakLRl7O02tYDAdJeX5QVEkpc97PPNydX1Q1jTNC0EH8m0WyCx1nVdRJ3VqiLfVNbMyEYXJUGIcNGEK6MqgCUNBBqQQo1gMgrbYHB3BQ3JiDK4tIEqmqam9EoqvFbTQxm4soHZ5Iuf6scJ2lEaZznCaICmlc6jXeatxYVbUnWzbLw0m8qk1YgwCsx82GwM6Lp-EprpwzQ7tA1pXmIH76-Qk4Yyb9CGOIdv28Q4hu6e7DyOGcYyMBkednwtZx7Ry4dhuIvHg2biuJ+7ipN46SJOhTNN5E57t83g4DE+-fN8pmhT+oU+LehC3ae26zWr1NkZoZbmQDMvnKQAKVrZs6WyEWPIjnFj5KWVpZZ2gVqFZW4VvTqyELiO2NBQyMFzMmNM1BwwYRSowWABsBDoEdnpVAzsKrlhqr6Fkc1Qx6hVFKX81CH4DhqC-UMEBYi5DGrkUModBrDVGtHJAi5lwzQTnNBaS1AKpyPCeFkW0s4gGILEVA959q7Q-HgL8l1y4aD+LuauD1egvSHsYgYf0zHUQsbRGejxx62xsY8e4jiMZLwxoJHGWNeKnx7p4+SeJGB+L8XjIJ8lpJwz+KE4mkTSbRIJtTOJ8Jr7YgxIk6+eJcS33kQAMTHPARkABBXIAA3KUVQmRWWgKZXmDUBZCwAWLbyks-IBTlsFRWYVVawKiqgKAxBza5HQKGNgPJHDRhqBgUM4hBCMBShMFCMUUzJTILkEhrtyx8l9OGIpJTYDUIqQQAa4cpxR3GoIyawjZobmTstPchlpEbVkZnembA6jEFlKgR854IDqM0WXP8rQq71Hus0Vo2EMLoUIoROuzdm5d3Qr3cY-dEUmN6G9KxaLrjWOeE4rF89cXPAXsvQla9iW6F3qSneO8D40CpUfXQuJUkMrpSJK+TKMn0wAKLSjmoycppI6Hlx5uZSyIBv6pFgH-OpnlgGNOls0iBIUlYq09J000HxxAhCgDmJq-xxDRigFAXVuJSmDMYGbKAuQcz4moCs0s5ZJxUJoXyxIV19lhyGhHfhJyhHTQuUnRaKdbnpweXfEAh1iDwGIPaAAahAXUJdvyFG0QMToQFAU1xRciwe8xsL4xWDDHYWxC37GLYcHYZwTjMUxi4zGbiT51oJmTJSdiwR4mbVCMEIlwS3GknPMEPaEQr1uLJNgw7h00XKOO24k7kl0qSegNllICnFKMrAckqRGTZIFCyUogqP43lFBKKUMp5SJEVKIlUkqgENN8rK8B8sFXtOVZFU0ggeQhjIB8csCw2ANgwn0CZQy9Y1HVUXIuxBorrBtZVQOVZ4B+2iN1HJmzl2lLXXB9+26eGHMjpwmOccRGJ3EeQFagaZFnkeZSRI6BDqoHtLeVAspvnnQTVdf83hAL6OBeBKCDFoJYF48hQTUFUI3DBSCvCEnWgQszQPAeVFS3orWAxaZOwmKsVYu8DizjbgIiEnpvERExJGf4uvdeZNzOKUUgum8WlOr6UMn1Xd1TrK2TAA5JyLl5walFlK69oCZaBVaVApVasooxTiiERKyVUrpSgJlbKuV8qFWKqVAsLtbVVRqnVFqSQmqc1au1bSukuE9SMiZV1vCPXHPnEgPk4o4DnNEZc-1SAxg3LWmRuR9NCzalsFUAumSPyqSObhpAsEQAjfgAAeQaC2TIlR8CLDwHsT4jBVLM0nDUKkToao1ZTRt6UW34DRGPPzJAHHdtFCaFmJEQFLtpGFJZPR92wCPcSAzWAt5EA3ijdkLddRihIEZAAamoAAHUSFZE7uRoiFJbIyQ62QsuFDh4yMAO29JA9DGQCHUPqyw-h4j5H2RUfo5ZJjxk2OIcAAoACytZsikhVMyHkjIDCMiqC9xkNR4jej0gASghwzaIM1xRA-Z5zvSV3hxSPWtqFIhZ4AmSQLdj24pohtj0lSBAoFwCFmHKskAqk4AnL3LkI0OA2zFFPbeSQy49RxHXBAOoxkxywHlIeHcrrPR5MNKu2IaRkDACiEBZ3NVUhTfiEkAoABJCOt5kCMAiEAA
```
