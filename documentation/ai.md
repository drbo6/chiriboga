# AI Implementation Tutorial

This document explains how the AI players in this Netrunner simulator work, and how to add AI support to new cards.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [How the AI System Works](#2-how-the-ai-system-works)
3. [The ELO Property](#3-the-elo-property)
4. [Runner AI Hooks](#4-runner-ai-hooks)
   - [4.1 Standard Icebreakers — `AIImplementBreaker`](#41-standard-icebreakers--aiimplementbreaker)
   - [4.2 Special / Trojan Breakers — `AISpecialBreaker`, `AIMatchingBreakerInstalled`](#42-special--trojan-breakers)
   - [4.3 Fixed-Strength Breakers — `AIFixedStrength`](#43-fixed-strength-breakers)
   - [4.4 Install Decisions — `AIPreferredInstallChoice`, `AIWastefulToInstall`, `AIOkToTrash`](#44-install-decisions)
   - [4.5 Worth Keeping in Hand — `AIWorthKeeping`](#45-worth-keeping-in-hand)
   - [4.6 Economy Cards — `AIEconomyInstall`, `AIEconomyTrigger`, `AIWouldTrigger`](#46-economy-cards)
   - [4.7 Run Events — `AIRunEventExtraPotential`, `AIRunEventModify/Restore`, `AIRunEventExtraCredits`](#47-run-events)
   - [4.8 Access Boosters — `AIAdditionalAccess`](#48-access-boosters)
   - [4.9 Install-Before-Run — `AIInstallBeforeRun`, `AIInstallBeforeInstall`](#49-install-before-run)
   - [4.10 Non-Run Events — `AIWouldPlay`, `AIWastefulToPlay`, `AIPreferredPlayChoice`](#410-non-run-events)
   - [4.11 Draw Cards — `AIPlayToDraw`, `AIDrawInstall`](#411-draw-cards)
   - [4.12 Tutor Cards — `AIIcebreakerTutor`](#412-tutor-cards)
   - [4.13 Run Ability Extras — `AIRunAbilityExtraPotential`, `AIRunExtraPotential`, `AIBreachNotRequired`](#413-run-ability-extras)
   - [4.14 Trash Cost Reduction — `AIReducesTrashCost`](#414-trash-cost-reduction)
   - [4.15 Inline AI Code for Runner](#415-inline-ai-code-for-runner)
5. [Corp AI Hooks](#5-corp-ai-hooks)
   - [5.1 ICE — `AIImplementIce`](#51-ice--aiimplementice)
   - [5.2 ICE Subroutine Type Reference](#52-ice-subroutine-type-reference)
   - [5.3 Bioroid Click-Break — Corp `AIImplementBreaker`](#53-bioroid-click-break)
   - [5.4 ICE Install Decisions — `AIWorthwhileIce`](#54-ice-install-decisions)
   - [5.5 Upgrades — `AIDefensiveValue`, `AIIsScoringUpgrade`, `AILimitPerServer`, `AIPreventBreach`](#55-upgrades)
   - [5.6 Operations — `AIFastAdvance`, `AIDamageOperation`, `AITagPunishment`, `AIWouldPlay`, `AIWouldPlayBeforeScore`](#56-operations)
   - [5.7 Agendas — `AIAdvancementLimit`, `AIOverAdvance`](#57-agendas)
   - [5.8 Inline AI Code for Corp](#58-inline-ai-code-for-corp)
6. [The Run Calculator (`rc`)](#6-the-run-calculator-rc)
7. [Quick Reference Table](#7-quick-reference-table)
8. [Step-by-Step Worked Example](#8-step-by-step-worked-example)

---

## 1. Project Overview

This project is a browser-based implementation of the **Netrunner** card game. Two players compete:

- **The Corp** — builds and defends servers, installs ice, and tries to score agendas.
- **The Runner** — attacks those servers, breaks through ice, and tries to steal agendas.

Cards are defined in JavaScript files inside the `sets/` folder. Each card is a plain JavaScript object with properties describing its rules: cost, abilities, subroutines, and so on.

The game can be played against an AI opponent. The AI is driven by two classes:

- `CorpAI` in `ai_corp.js`
- `RunnerAI` in `ai_runner.js`

These classes contain the general decision-making logic — how to economise, when to run, how to protect servers, etc. However, they cannot know every card's specific quirks. That is where **AI hooks** come in.

---

## 2. How the AI System Works

### The Hook Pattern

Each card can define additional properties whose names start with `AI`. The AI engine checks for these properties at the right moment during decision-making and calls them.

For example, a standard icebreaker defines `AIImplementBreaker`, a function that the run calculator calls to understand how that breaker deals with ice. An economy card defines `AIEconomyInstall` to indicate its install priority. A run event defines `AIRunEventExtraPotential` to suggest when it should be played.

If a card does **not** define a given hook, the engine applies a sensible default (or ignores that card for that purpose).

### The Run Calculator

The most complex hooks (icebreakers, ICE) work through an object called the **Run Calculator** (`rc`). The RunnerAI and CorpAI both use it to simulate hypothetical runs: given the ice on a server and the breakers the runner has, can the runner get through? At what credit cost? Is it worthwhile?

The RunCalculator calls each breaker's `AIImplementBreaker` function in turn, and each ICE card's `AIImplementIce` function, to build up a picture of what a run would cost. You do not manually interact with it except through these hooks — `rc` is passed into your hook function as an argument, and you use its helper methods to describe your card's abilities. The full method reference is in [section 6](#6-the-run-calculator-rc).

### What happens without hooks

If you add a new card without any AI hooks:

- **Icebreakers** will not be used to break ice (the run calculator will not know they exist).
- **Economy cards** will not be bought or triggered.
- **Run events** will not be played.
- **ICE** will be treated as having a single `misc_moderate` subroutine per subroutine it has, which is a rough fallback.
- Generic cards will still be installed and played if nothing marks them as wasteful, but with no particular intelligence.

A card with no AI support is playable by a human but will be ignored or misused by the AI. The goal of this tutorial is to help you describe your card precisely enough for the AI to use it well.

### Checking if AI is active

Inside a card's `Enumerate` or `Resolve` function, you can run code specifically for AI players:

```js
if (corp.AI != null) {
    // corp-side AI code
}

if (runner.AI != null) {
    // runner-side AI code
}
```

This is useful for more complex cards where the AI needs to make a choice mid-resolution.

---

## 3. The ELO Property

Every card has an `elo` property — a number representing the card's general power level. It is used as a tiebreaker when the AI must choose between cards that are otherwise equally attractive.

```js
cardSet[30005] = {
    title: "Buzzsaw",
    elo: 1615,
    // ...
};
```

Think of this number as an approximate Elo rating. Stronger, more universally useful cards have higher values. Weaker, situational, or card-draw cards have lower values.

**When you add a new card**, assign a reasonable Elo. Start around 1500 for an average card, higher for a powerful card, lower for a niche card.

---

## 4. Runner AI Hooks

### 4.1 Standard Icebreakers — `AIImplementBreaker`

Standard icebreakers have a subtype like `Barrier`, `Code Gate`, or `Sentry` and typically cost credits to pump strength and to break subroutines. The run calculator needs to know these costs to decide whether a run is worthwhile.

You implement this via `AIImplementBreaker`, a function the run calculator calls once per breaker for each piece of ice it is evaluating. Your job inside this function is simple: **describe how this breaker can handle this specific piece of ice, and at what cost**. The run calculator will work out the rest.

#### How the result array works

The function receives a `result` array (initially empty) and must return it with your card's options appended. The pattern is always:

```js
result = result.concat( /* breaking options */ );
return result;
```

You never construct the option objects manually. Instead, you call helper methods on `rc` (the RunCalculator) which generate the correctly structured objects and return them as an array. The most important of these is `rc.ImplementIcebreaker(...)`, which handles all standard icebreaker logic. There are also `rc.SrBreak(...)` and `rc.StrModify(...)` for more exotic cards — all are documented in [section 6](#6-the-run-calculator-rc).

If your breaker cannot interact with the ice being evaluated (wrong subtype, wrong server, etc.), simply return `result` unchanged.

**Signature:**
```js
AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft)
```

- `rc` — the `RunCalculator` instance. Use its helper methods (described in [section 6](#6-the-run-calculator-rc)).
- `result` — accumulator array of break options. Always start with the provided `result` and concatenate to it.
- `point` — the current calculator checkpoint. Passed through to `rc` methods unchanged — you do not need to interpret it.
- `server` — the server being run.
- `cardStrength` — this breaker's current strength (already includes any active modifiers).
- `iceAI` — an object describing the ice from the run calculator's perspective:
  - `iceAI.ice` — the actual ice card object.
  - `iceAI.subTypes` — array of subtype strings (e.g. `["Barrier"]`).
  - `iceAI.strength` — the ice's current effective strength.
  - `iceAI.sr` — the subroutine descriptions (you don't need to read these in `AIImplementBreaker`).
- `iceStrength` — the ice's current strength (same as `iceAI.strength`, provided for convenience).
- `clicksLeft` — how many runner clicks remain in this hypothetical.
- `creditsLeft` — how many credits are left in the run budget.

For a standard icebreaker you will almost always call `rc.ImplementIcebreaker(...)`, which takes the breaker's costs and works out all the ways it can break the ice:

```js
rc.ImplementIcebreaker(
    point,
    this,           // the breaker card
    cardStrength,   // breaker's current strength
    iceAI,          // ice context object
    iceStrength,    // ice's current strength
    iceSubTypes,    // array of subtypes this breaker can handle, e.g. ["Barrier"]
    costToUpStr,    // credit cost per strength boost
    amtToUpStr,     // strength gained per boost
    costToBreak,    // credit cost per break use
    amtToBreak,     // subroutines broken per use
    creditsLeft     // remaining credit budget
)
```

The four numbers at the end — `costToUpStr`, `amtToUpStr`, `costToBreak`, `amtToBreak` — are the heart of the description. They say: "it costs X credits to gain Y strength, and Z credits to break W subroutines." For Buzzsaw: 3 credits to gain 1 strength, 1 credit to break 2 subroutines. For a simple breaker that costs 2 credits per strength and 1 credit per subroutine, you would pass `2, 1, 1, 1`.

**Example — Buzzsaw (Code Gate decoder, 3[c]/+1 str, 1[c]/break up to 2 subs):**

```js
AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    result = result.concat(
        rc.ImplementIcebreaker(
            point, this, cardStrength, iceAI, iceStrength,
            ["Code Gate"],
            3, 1,   // 3[c] to boost +1 str
            1, 2,   // 1[c] to break up to 2 subs
            creditsLeft
        )
    );
    return result;
},
```

**Example — Carmen (Sentry killer, 2[c]/+3 str, 1[c]/break 1 sub):**

```js
AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    result = result.concat(
        rc.ImplementIcebreaker(
            point, this, cardStrength, iceAI, iceStrength,
            ["Sentry"],
            2, 3,   // 2[c] to boost +3 str
            1, 1,   // 1[c] to break 1 sub
            creditsLeft
        )
    );
    return result;
},
```

**Example — Marjanah (Fracter with variable break cost):**

The break cost changes based on game state. Compute the cost first, then pass it:

```js
AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    var marcost = 2;
    if (this.madeSuccessfulRunThisTurn) marcost = 1;
    result = result.concat(
        rc.ImplementIcebreaker(
            point, this, cardStrength, iceAI, iceStrength,
            ["Barrier"],
            1, 1,       // 1[c] to boost +1 str
            marcost, 1, // variable cost to break 1 sub
            creditsLeft
        )
    );
    return result;
},
```

**Example — Unity (Decoder with dynamic strength pump):**

The amount per strength boost depends on installed breakers. Use `rc.precalculated.runnerInstalledIcebreakersLength`:

```js
AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    var strup = rc.precalculated.runnerInstalledIcebreakersLength;
    result = result.concat(
        rc.ImplementIcebreaker(
            point, this, cardStrength, iceAI, iceStrength,
            ["Code Gate"],
            1, strup,   // 1[c] to boost (amount = number of installed breakers)
            1, 1,
            creditsLeft
        )
    );
    return result;
},
```

**Also add `AIPreferredInstallChoice`:**

For icebreakers, the AI needs to know they are safe to install during an action. The simplest version avoids installing on the last click (to preserve options):

```js
AIPreferredInstallChoice: function(choices) {
    if (runner.clickTracker < 2) return -1; // don't install on last click
    return 0; // install
},
```

Return `-1` to decline, or a non-negative index into `choices` to accept.

---

### 4.2 Special / Trojan Breakers

Some programs break ice without having the `Icebreaker` subtype, or work in unusual ways. Examples are Botulus and Tranquilizer (Virus/Trojan programs that are hosted on ice).

Mark them with `AISpecialBreaker: true` so the AI treats them differently from conventional icebreakers:

```js
AISpecialBreaker: true,
```

You still implement `AIImplementBreaker` to explain how they break ice, and `AIMatchingBreakerInstalled` so the AI can check whether a given piece of ice is already covered.

**`AIMatchingBreakerInstalled(iceCard)`** — Called on every installed program to find a match for a given ice. Return `this` if this card can handle that ice, or `null` if not.

```js
// Botulus: only matches the ice it is hosted on
AIMatchingBreakerInstalled: function(iceCard) {
    if (this.host) {
        var knownToBeDisabled = false;
        if (PlayerCanLook(runner, this.host)) knownToBeDisabled = this.host.AIDisablesHostedPrograms;
        if (iceCard == this.host && !knownToBeDisabled) return this;
    }
    return null;
},
```

```js
// Tranquilizer: matches only the hosted ice
AIMatchingBreakerInstalled: function(iceCard) {
    if (iceCard == this.host) return this;
    return null;
},
```

**`AIPreferredInstallChoice(choices)`** — Choose which ice to host on. Use `runner.AI._highestThreatScoreIce(excludeList, minRezCost)` to find the highest-threat ice that is not already covered:

```js
// Botulus: installs on the highest-threat ice that doesn't already have a special breaker
AIPreferredInstallChoice: function(choices) {
    var htsi = runner.AI._highestThreatScoreIce(
        [this].concat(runner.AI._iceHostingSpecialBreakers())
    );
    if (htsi) {
        for (var i = 0; i < choices.length; i++) {
            if (htsi == choices[i].host) return i;
        }
    }
    return -1; // don't install
},
```

```js
// Tranquilizer: same, but requires at least 4 rez cost on the target
AIPreferredInstallChoice: function(choices) {
    var htsi = runner.AI._highestThreatScoreIce(
        [this].concat(runner.AI._iceHostingSpecialBreakers()),
        4  // minimum rez cost
    );
    if (htsi) {
        for (var i = 0; i < choices.length; i++) {
            if (htsi == choices[i].host) return i;
        }
    }
    return -1;
},
```

**Hypothetical run preparation** — When the AI calculates whether it can run a server, it may need to simulate the Trojan already being in position. Use these two paired hooks:

```js
AIPrepareHypotheticalForRC: function(preferredHost) {
    this.host = preferredHost;
    this.virus = 1;        // simulate starting counters
},
AIRestoreHypotheticalFromRC: function() {
    this.host = null;
    this.virus = 0;
},
```

**`AIOkToTrash()`** — Return `true` if the card can safely be trashed (for example, if its host ability has been disabled):

```js
AIOkToTrash: function() {
    if (this.host) {
        if (this.host.AIDisablesHostedPrograms) return true;
    }
    return false;
},
```

**Breaking implementation for Trojans** — Use `rc.SrBreak` to represent breaking subroutines from hosted counters:

```js
// Botulus: can break 1 sub per hosted virus counter
AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    if (this.host == iceAI.ice) {
        var sr_broken_by_this = 0;
        for (var i = 0; i < point.sr_broken.length; i++) {
            if (point.sr_broken[i].use == this) sr_broken_by_this++;
        }
        if (sr_broken_by_this < Counters(this, "virus")) {
            result = result.concat(rc.SrBreak(this, iceAI, point, 1));
        }
    }
    return result;
},
```

---

### 4.3 Fixed-Strength Breakers

Some breakers never pump strength in the conventional sense (e.g. Mayfly uses a general `[]` subtype match). Mark them with `AIFixedStrength: true` so the AI knows to install strength-reduction tools like Leech alongside them:

```js
// Mayfly: AI icebreaker that breaks any subroutine for 1[c]
AIFixedStrength: true,  // (implied by [] subtype — mark if card needs this flag)
```

In the `AIImplementBreaker`, use an empty subtype array `[]` to match all ice regardless of subtype:

```js
AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    if (iceAI.ice.cannotBreakUsingAIPrograms) return result;
    // only use Mayfly for worthwhile targets unless a spare is in hand
    var anotherInGrip = runner.grip.some(c => c.title == "Mayfly");
    if (!runner.AI || runner.AI._getCachedPotential(server) > 1.5 || anotherInGrip) {
        result = result.concat(
            rc.ImplementIcebreaker(
                point, this, cardStrength, iceAI, iceStrength,
                [],      // no subtype restriction
                1, 1,    // 1[c] to boost +1 str
                1, 1,    // 1[c] to break 1 sub
                creditsLeft
            )
        );
    }
    return result;
},
```

---

### 4.4 Install Decisions

**`AIPreferredInstallChoice(choices)`**

Called when the AI considers installing this card. Return `-1` to decline, or the index of the preferred choice to accept.

Use this if the card has hosting requirements, or if there are conditions that make installing unwise:

```js
// Don't install if this is the last click (save the click for a run)
AIPreferredInstallChoice: function(choices) {
    if (runner.clickTracker < 2) return -1;
    return 0;
},
```

```js
// Carmen: don't need to save a click if already got a run discount
AIPreferredInstallChoice: function(choices) {
    if (runner.clickTracker < 2 && !this.madeSuccessfulRunThisTurn) return -1;
    return 0;
},
```

**`AIWastefulToInstall()`**

Return `true` if installing this card right now would be a waste (e.g. a unique card that is already installed):

```js
// Conduit: don't install a second copy
AIWastefulToInstall: function() {
    for (var j = 0; j < runner.rig.programs.length; j++) {
        if (runner.rig.programs[j].title == "Conduit") return true;
    }
    return false;
},
```

```js
// Mayfly: one at a time only
AIWastefulToInstall: function() {
    return runner.rig.programs.some(p => p.title == "Mayfly");
},
```

**`AIOkToTrash()`**

Return `true` if this installed card can be trashed to make room for another (MU or hand limit):

```js
AIOkToTrash: function() {
    // safe to trash if its ability has been disabled by the host
    if (this.host && this.host.AIDisablesHostedPrograms) return true;
    return false;
},
```

---

### 4.5 Worth Keeping in Hand

**`AIWorthKeeping(installedRunnerCards, spareMU)`**

Called during the discard-to-hand-limit phase. Return `true` to try to keep this card in hand instead of discarding it. Also influences whether the AI plays cards that install from hand (like Mutual Favor).

- `installedRunnerCards` — array of all currently installed Runner cards.
- `spareMU` — memory units available before this card is installed.

```js
// Sure Gamble: always keep (it's always useful)
AIWorthKeeping: function(installedRunnerCards, spareMU) {
    return true;
},
```

```js
// Fermenter: keep if broke (economy needed)
AIWorthKeeping: function(installedRunnerCards, spareMU) {
    if (Credits(runner) < 5) return true;
    return false;
},
```

```js
// Conduit: keep if a run into R&D is possible and no Conduit is installed
AIWorthKeeping: function(installedRunnerCards, spareMU) {
    if (!this.AIWastefulToInstall()) {
        if (runner.AI._getCachedCost(corp.RnD) != Infinity) return true;
    }
    return false;
},
```

```js
// DZMZ Optimizer (memory hardware): keep if MU is tight
AIWorthKeeping: function(installedRunnerCards, spareMU) {
    if (spareMU < 2) return true;
    return false;
},
```

---

### 4.6 Economy Cards

Economy cards generate credits. The AI needs to know when to install them and when to fire their abilities.

**`AIEconomyInstall()`**

Return a priority number (higher = more urgent) for installing this card. Return `0` or don't define this hook if the card is not an economy card.

```js
// Telework Contract: moderate install priority
AIEconomyInstall: function() {
    return 2; // priority 2
},
```

```js
// Smartware Distributor: low install priority
AIEconomyInstall: function() {
    return 1; // priority 1
},
```

**`AIEconomyTrigger`** (a plain number, not a function)

Priority for triggering this card's economy ability. Higher = trigger sooner:

```js
AIEconomyTrigger: 2,  // click-for-credit type abilities
AIEconomyTrigger: 1,  // slower economy
```

**`AIWouldTrigger()`**

Return `true` to allow the ability to be triggered. Return `false` to skip it this turn. Use this for conditional economy cards:

```js
// Fermenter: only trigger when there are at least 3 virus counters
AIWouldTrigger: function() {
    if (!CheckCounters(this, "virus", 3)) return false;
    return true;
},
```

```js
// Pennyshaver: don't use as a pure click-for-credit when overdrawn
AIWouldTrigger: function() {
    if (runner.grip.length > MaxHandSize(runner)) {
        if (!CheckCounters(this, "credits", 1)) return false;
    }
    return true;
},
```

```js
// Smartware Distributor: don't trigger if already loaded, or if a less-loaded copy should go first
AIWouldTrigger: function() {
    var counters = Counters(this, "credits");
    if (counters > 2) return false;
    for (var i = 0; i < runner.rig.resources.length; i++) {
        if (runner.rig.resources[i] !== this && runner.rig.resources[i].title == "Smartware Distributor") {
            if (Counters(runner.rig.resources[i], "credits") < counters) return false;
        }
    }
    return true;
},
```

---

### 4.7 Run Events

Run events (those with subtype `Run`) require special treatment. Instead of `AIWouldPlay`, use potential-based hooks.

**`AIRunEventExtraPotential(server, potential)`**

Called for each server the AI might run. Return a float:
- `0` — don't play this event for this server.
- A positive number — add this to the server's run potential score. The AI will use the event if it has the highest combined potential.

```js
// Overclock: save for high-value targets only
AIRunEventExtraPotential: function(server, potential) {
    if (potential > 1.5) return 0.01; // any positive = "yes"
    return 0;
},
```

```js
// Jailbreak: only use on HQ or R&D with no unrezzed ice
AIRunEventExtraPotential: function(server, potential) {
    if (server == corp.HQ || server == corp.RnD) {
        if (runner.AI._rootKnownToContainCopyOfCard(server, "Crisium Grid")) return 0;
        var unrezzedIce = server.ice.filter(i => !i.rezzed).length;
        if (unrezzedIce == 0) {
            if (server == corp.HQ) return 0.5 * runner.AI._additionalHQAccessValue(this);
            if (server == corp.RnD) return 0.5 * runner.AI._countNewCardsThatWouldBeAccessedInRnD(2);
        }
    }
    return 0;
},
```

```js
// Tread Lightly: useful for targets with multiple unrezzed ice
AIRunEventExtraPotential: function(server, potential) {
    var unrezzedIce = server.ice.filter(i => !i.rezzed).length;
    if (potential > 1.5 || unrezzedIce > 1) {
        if (AvailableCredits(corp) < 5 + 5 * unrezzedIce + runner.creditPool) {
            return 0.1 * unrezzedIce;
        }
    }
    return 0;
},
```

**`AIRunEventModify(server)` / `AIRunEventRestore(server)`**

Use these if the run event temporarily changes game state for the run calculation. Modify in `AIRunEventModify` and undo in `AIRunEventRestore`:

```js
// Tread Lightly: increases ice rez costs, so simulate the corp having less money
AIRunEventModify: function(server) {
    this.storedCorpCreditPool = corp.creditPool;
    corp.creditPool -= 3;
},
AIRunEventRestore: function(server) {
    corp.creditPool = this.storedCorpCreditPool;
},
```

**`AIRunEventExtraCredits`** (a plain number)

If the run event gives the runner extra credits to spend during the run (like Overclock), declare the amount here. The AI will subtract the play cost to determine net gain:

```js
// Overclock: 5 credits on the card, costs 1 to play = net 4
AIRunEventExtraCredits: 5,
```

---

### 4.8 Access Boosters

Some cards (hardware, programs) passively increase how many cards the runner accesses per run.

**`AIAdditionalAccess(server)`**

Return the number of additional cards accessed in `server` when this card is active. Return `0` if it does not apply (wrong server, already used, etc.):

```js
// Docklands Pass: +1 access to HQ, first time per turn
AIAdditionalAccess: function(server) {
    if (server != corp.HQ) return 0;
    if (this.breachedHQThisTurn) return 0; // first time only
    return 1;
},
```

```js
// Jailbreak: +1 access to HQ or R&D if run succeeds
AIAdditionalAccess: function(server) {
    if (server != corp.HQ && server != corp.RnD) return 0;
    if (runner.AI._rootKnownToContainCopyOfCard(server, "Crisium Grid")) return 0;
    return 1;
},
```

---

### 4.9 Install-Before-Run

Some cards are best installed immediately before running (because they give run bonuses). The AI checks this before deciding to run.

**`AIInstallBeforeRun(server, potential, useRunEvent, runCreditCost, runClickCost)`**

Return a positive number to suggest installing this card before running `server`. Return `0` to not suggest it. Higher numbers take priority:

```js
// Leech: install before central server runs (it gains counters on central runs)
AIInstallBeforeRun: function(server, potential, useRunEvent, runCreditCost, runClickCost) {
    if (typeof server.cards !== "undefined") return 1; // yes, for central servers
    return 0;
},
```

```js
// Docklands Pass: install before HQ run if it's worth keeping
AIInstallBeforeRun: function(server, potential, useRunEvent, runCreditCost, runClickCost) {
    if (server == corp.HQ) {
        if (runner.AI.cardsWorthKeeping.includes(this)) return 1;
    }
    return 0;
},
```

```js
// Red Team: install before central server runs that haven't been made this turn
AIInstallBeforeRun: function(server, potential, useRunEvent, runCreditCost, runClickCost) {
    if (runner.AI._rootKnownToContainCopyOfCard(server, "Crisium Grid")) return 0;
    if (typeof server.cards !== "undefined") { // central server
        var alreadyRunThisTurn = (server == corp.HQ) ? this.runHQ :
                                 (server == corp.RnD) ? this.runRnD : this.runArchives;
        if (!alreadyRunThisTurn) return 1;
    }
    return 0;
},
```

**`AIInstallBeforeInstall(cardToInstall)`**

Return `true` to request that this card be installed before `cardToInstall`. Used by support cards (like Cookbook) that must be in play before the card they benefit:

```js
// Cookbook: install before any Virus program you can afford
AIInstallBeforeInstall: function(cardToInstall) {
    if (CheckSubType(cardToInstall, "Virus")) {
        if (AvailableCredits(runner) >= InstallCost(this) + InstallCost(cardToInstall)) {
            return true;
        }
    }
    return false;
},
```

---

### 4.10 Non-Run Events

For events that are **not** run events, use these hooks.

**`AIWouldPlay()`**

Return `true` to allow the AI to play this event. Return `false` to skip it:

```js
// VRcation: draw 4 cards, lose a click — don't play on the last click
AIWouldPlay: function() {
    if (runner.clickTracker == 2) return false;
    if (runner.AI._currentOverDraw() + 2 < runner.AI._maxOverDraw()) return true;
    return false;
},
```

**`AIWastefulToPlay()`**

Return `true` if playing this card right now would be wasteful:

```js
// Creative Commission: gain 5[c] but lose a click — wasteful on 2nd-to-last click
AIWastefulToPlay: function() {
    if (runner.clickTracker == 2) return true;
    return false;
},
```

**`AIPreferredPlayChoice(choices)`**

If the event presents a choice on play, return the preferred index, or `-1` to decline playing:

```js
AIPreferredPlayChoice: function(choices) {
    return 0; // always choose the first option
},
```

---

### 4.11 Draw Cards

**`AIPlayToDraw`** (plain number)

A priority number for events that draw cards. Higher = AI tries to play this earlier when it needs cards:

```js
// VRcation: draws 4 cards — high priority
AIPlayToDraw: 3,

// Some 1-card draw events
AIPlayToDraw: 1,
```

**`AIDrawInstall()`**

Return a priority number for **resources or hardware** that passively increase card draw (e.g. Verbal Plasticity). Higher = install sooner when the runner needs card draw:

```js
AIDrawInstall: function() {
    return 1; // low priority install for draw
},
```

---

### 4.12 Tutor Cards

Cards that search for and fetch another card (tutors) need to tell the AI what they would fetch.

**`AIIcebreakerTutor(installedRunnerCards)`**

Return the icebreaker card object (from the stack) that this tutor would likely fetch, or `null` if nothing useful:

```js
// Mutual Favor: fetch the most-needed icebreaker that's in the stack but not installed
AIIcebreakerTutor: function(installedRunnerCards) {
    return runner.AI._icebreakerInPileNotInHandOrArray(runner.stack, installedRunnerCards);
},
```

---

### 4.13 Run Ability Extras

Some installed cards give passive or active bonuses to running a server.

**`AIRunAbilityExtraPotential(server, potential)`**

Return a float for extra potential gained by using this card's active ability during a run on `server`. Used for cards like Conduit and Red Team that have a run-related ability:

```js
// Conduit: worth using if we can access new cards in R&D
AIRunAbilityExtraPotential: function(server, potential) {
    if (server == corp.RnD) {
        if (runner.AI._rootKnownToContainCopyOfCard(corp.RnD, "Crisium Grid")) return 0;
        var conduitDepth = Counters(this, "virus") + 1;
        var bonusCards = runner.AI._countNewCardsThatWouldBeAccessedInRnD(conduitDepth, []);
        if (bonusCards > 0) return bonusCards - 1;
    }
    return 0;
},
```

**`AIRunExtraPotential(server, potential)`**

Return a passive extra potential value for simply running `server` when this card is installed (without needing to use an ability):

```js
// Conduit: passively benefits from R&D runs (gain virus counters)
AIRunExtraPotential: function(server, potential) {
    if (server == corp.RnD) {
        var conduitDepth = Counters(this, "virus") + 1;
        if (conduitDepth < corp.RnD.cards.length) return 0.5;
    }
    return 0;
},
```

**`AIBreachNotRequired`** (boolean)

Set to `true` if this card's benefit is gained without needing to breach the server. This tells the AI to consider the benefit even when it cannot breach:

```js
// Red Team: credits from successful run even without breach
AIBreachNotRequired: true,
```

---

### 4.14 Trash Cost Reduction

**`AIReducesTrashCost(card)`**

Return by how many credits this card reduces the trash cost of `card`. Used by hardware like Carnivore that lets you trash accessed cards at a discount:

```js
// Carnivore: can trash card for free by trashing 2 grip cards (so reduction = full trash cost)
AIReducesTrashCost: function(card) {
    if (this.usedThisTurn) return 0;
    if (runner.AI._rootKnownToContainCopyOfCard(GetServer(card), "Urtica Cipher")) return 0;
    if (runner.grip.length - runner.AI.cardsWorthKeeping.length < 2) return 0;
    return TrashCost(card); // full reduction
},
```

---

### 4.15 Inline AI Code for Runner

Some cards are complex enough that the AI logic is placed directly inside `Enumerate` or `Resolve`, guarded by `if (runner.AI != null)`. The AI communicates its choice by setting `runner.AI.preferred`.

**Setting a preference:**
```js
runner.AI.preferred = { title: "CardTitle", option: choice };
// "title" must match the title of the currentPhase the choice belongs to
```

**Example — Zahya Sadeghi (identity, always gains credits after a central run):**
```js
if (runner.AI != null) {
    runner.AI._log("I know this one");
    var choice = choices[0]; // always gain the credits
    runner.AI.preferred = { title: "Zahya Sadeghi", option: choice };
}
```

**Example — Conduit (always places a virus counter after a successful R&D run):**
```js
if (runner.AI != null) {
    runner.AI._log("I know this one");
    var choice = choices[0]; // always place a counter
    runner.AI.preferred = { title: "Conduit", option: choice };
}
```

**Example — Mutual Favor (installs the most-needed icebreaker from the stack):**

In `Enumerate`, return only the preferred choice:
```js
if (runner.AI != null && choices.length > 0) {
    var preferredCard = runner.AI._icebreakerInPileNotInHandOrArray(runner.stack, InstalledCards(runner));
    var choice = choices[0];
    for (var i = 0; i < choices.length; i++) {
        if (choices[i].card == preferredCard) choice = choices[i];
    }
    return [choice]; // return only the preferred option
}
```

In `Resolve`, prefer the install option:
```js
if (runner.AI != null && choices.length > 0) {
    runner.AI._log("I know this one");
    var choice = choices[0]; // choose install if possible
    runner.AI.preferred = { title: "Mutual Favor", option: choice };
}
```

**Useful helper methods on `runner.AI`:**
- `runner.AI._log(msg)` — debug logging
- `runner.AI._getCachedCost(server)` — returns `Infinity` if the server is considered unrunnable
- `runner.AI._getCachedPotential(server)` — estimated value of running this server
- `runner.AI._matchingBreakerInstalled(iceCard)` — finds installed breaker for given ice
- `runner.AI._highestThreatScoreIce(excludeList, minRezCost)` — finds most threatening ice
- `runner.AI._iceHostingSpecialBreakers()` — ice that already have Trojan breakers
- `runner.AI._icebreakerInPileNotInHandOrArray(pile, installedCards)` — finds the most-needed breaker in a pile
- `runner.AI._rootKnownToContainCopyOfCard(server, title)` — returns true if server is known to contain a named card
- `runner.AI._additionalHQAccessValue(usingCard)` — estimated value of extra HQ accesses
- `runner.AI._countNewCardsThatWouldBeAccessedInRnD(depth, excludeCards)` — new R&D cards at given depth
- `runner.AI.cardsWorthKeeping` — array of hand cards the AI wants to keep
- `runner.AI._currentOverDraw()` — how many cards over hand limit the runner is
- `runner.AI._maxOverDraw()` — maximum acceptable overdraw amount

---

## 5. Corp AI Hooks

### 5.1 ICE — `AIImplementIce`

ICE needs to tell the run calculator what its subroutines do. This is the most important hook for Corp cards.

**Signature:**
```js
AIImplementIce: function(rc, result, maxCorpCred, incomplete)
```

- `rc` — the `RunCalculator`.
- `result` — an object with `{ ice, subTypes, sr, strength, encounterEffects }`. You modify `result.sr` and return `result`.
- `maxCorpCred` — corp's available credits (used for cost assumptions about unknown ice).
- `incomplete` — `true` if the subroutine list may be incomplete (e.g. face-down ice). In this case, be defensive and avoid assigning punishing effects to those slots.

**`result.sr`** is an array of subroutine entries. Each entry is an array of "OR" branches. Each branch is an array of effect strings. The run calculator will try to find a path through the ice that avoids the worst effects.

See [section 5.2](#52-ice-subroutine-type-reference) for the full list of effect strings.

**Simple example — Ping (ETR Barrier):**
```js
AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    result.sr = [[["endTheRun"]]];
    return result;
},
```

**Example — Brân 1.0 (install ice inward + 2 x ETR):**
```js
AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    result.sr = [
        [["misc_serious"]],   // sub 1: install ice inward (serious)
        [["endTheRun"]],      // sub 2: end the run
        [["endTheRun"]],      // sub 3: end the run
    ];
    return result;
},
```

**Example — Funhouse (encounter effect + tag subroutine):**
```js
AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    // encounter effect: take 1 tag or end the run (OR branches)
    result.encounterEffects = [["endTheRun"], ["tag"]];
    // subroutine: pay 4[c] or take a tag
    result.sr = [
        [["payCredits", "payCredits", "payCredits", "payCredits"], ["tag"]],
    ];
    return result;
},
```

**Example — Ansel 1.0 (complex Sentry with context-dependent effects):**
```js
AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    // sub 1: trash a runner card (serious if programs installed)
    var installedPrograms = ChoicesInstalledCards(runner, c => CheckCardType(c, ["program"]));
    if (rc.precalculated.runnerInstalledCardsLength > 0) {
        if (installedPrograms.length > 0) result.sr.push([["misc_serious"]]);
        else result.sr.push([["misc_moderate"]]);
    } else result.sr.push([[]]); // blank if nothing to trash

    // sub 2: corp installs a card
    if (corp.HQ.cards.length == 0 && corp.archives.cards.length == 0) result.sr.push([[]]);
    else result.sr.push([["misc_moderate"]]);

    // sub 3: runner cannot steal or trash (very serious)
    if (incomplete) result.sr.push([[]]);
    else result.sr.push([["misc_serious"]]);

    return result;
},
```

**Example — Karunā (conditional ETR via jack out option):**
```js
AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    result.sr = [
        // sub 1: 2 net damage, then runner may jack out (ETR variant)
        [["netDamage", "netDamage", "endTheRun"], ["netDamage", "netDamage"]],
        // sub 2: 2 net damage
        [["netDamage", "netDamage"]],
    ];
    return result;
},
```

---

### 5.2 ICE Subroutine Type Reference

These are the effect strings you can use inside `result.sr` arrays:

| String | Meaning |
|---|---|
| `"endTheRun"` | Ends the run. Paths that fire this are avoided. |
| `"netDamage"` | 1 net damage. Include once per point of damage. |
| `"tag"` | Gives the runner 1 tag. |
| `"loseCredits"` | The runner loses 1 credit from their main pool (not extra credits). Will not reduce below zero. |
| `"payCredits"` | Makes the runner pay 1 credit. If unaffordable, this path is skipped. Use for subroutines with a credit-pay option (like Tollbooth or Funhouse's opt-out). |
| `"misc_minor"` | A minor Corp-side benefit (e.g. corp gains 1 credit). |
| `"misc_moderate"` | A moderate threat (e.g. trash 1 program). Paths with this are avoided if possible. |
| `"misc_serious"` | A serious threat (e.g. install another ice inward, runner cannot steal). Treated similarly to `endTheRun`. |

**Tips:**
- For subroutines with optional choices (e.g. "pay 4[c] or take a tag"), represent each option as a separate branch in the OR array.
- For unknown or partially-known effects, default to `misc_moderate`.
- Use `incomplete: true` guards when a subroutine list might be partial to avoid over-punishing the runner AI.

---

### 5.3 Bioroid Click-Break

Bioroid ice can have their subroutines broken by spending runner clicks. Add `AIImplementBreaker` to the ice itself:

```js
// Brân 1.0: can break 1 sub per click spent
AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    if (this == iceAI.ice) {
        if (clicksLeft > 0) {
            var breakresult = rc.SrBreak(this, iceAI, point, 1);
            for (var j = 0; j < breakresult.length; j++) {
                breakresult[j].runner_clicks_spent += 1;
            }
            result = result.concat(breakresult);
        }
    }
    return result;
},
```

---

### 5.4 ICE Install Decisions

**`AIWorthwhileIce(server, purpose)`**

Return `true` if this ice is worth installing in `server` for the given `purpose`. Purpose is a string like `"protect"` or `"scoring"`. Generally you do not need to implement this unless the ice has special restrictions.

---

### 5.5 Upgrades

**`AIDefensiveValue(server)`**

Return a numeric value representing how much protection this upgrade provides to `server`. Higher values make the AI more likely to install it. Return `0` to decline:

```js
// Manegarm Skunkworks: strong defensive upgrade
AIDefensiveValue: function(server) {
    return 4; // arbitrary, tune by observation
},
```

```js
// Anoetic Void: only useful in protected remote servers
AIDefensiveValue: function(server) {
    if (!server) return 0;
    if (typeof server.cards != 'undefined') return 0; // not in centrals
    if (server.ice.length < 1) return 0; // needs ice
    return 3;
},
```

**`AIIsScoringUpgrade`** (boolean)

Set to `true` if this upgrade is used for fast advancing agendas. This tells the AI to prioritise it in scoring servers:

```js
AIIsScoringUpgrade: true,
```

**`AILimitPerServer(server)`**

Return the maximum number of copies of this upgrade allowed per server. Prevents the AI from piling up multiple copies:

```js
AILimitPerServer: function(server) {
    return 1; // only 1 copy per server
},
```

**`AIPreventBreach(server)`**

Return `true` if this upgrade (when rezzed) will prevent the runner from breaching `server`. Used by the Corp AI to assess whether a server is truly safe:

```js
// An upgrade that ends the run before breach
AIPreventBreach: function(server) {
    // implement conditions under which this triggers
    return true;
},
```

**`AIWouldTrigger()`**

Return `true` to allow an upgrade's defensive ability to fire. Return `false` to suppress it:

```js
// Manegarm Skunkworks: don't fire if a kill is possible on breach
AIWouldTrigger: function() {
    if (corp.AI._potentialDamageOnBreach(attackedServer) > runner.grip.length) return false;
    return true;
},
```

```js
// Anoetic Void: don't fire if there is an ambush in the server, or no cards to discard from HQ
AIWouldTrigger: function() {
    if (corp.AI._isAmbush(GetServer(this))) return false;
    var cardsInServer = GetServer(this).root.length + (GetServer(this).cards || []).length;
    if (cardsInServer < 2) return false;
    if (corp.HQ.cards.length - corp.AI._agendasInHand() < 2) return false;
    return true;
},
```

---

### 5.6 Operations

**`AIFastAdvance`** (boolean)

Set to `true` if this operation is used to fast advance an agenda:

```js
// Seamless Launch: places 2 advancement counters on an already-installed card
AIFastAdvance: true,
```

**`AIDamageOperation`** (boolean)

Set to `true` for operations that deal damage to the runner:

```js
// Neurospike: X net damage
AIDamageOperation: true,
```

**`AITagPunishment`** (number)

Set to the minimum number of tags needed for this operation to be worthwhile. The AI will play it against a tagged runner:

```js
// Public Trail: punishes tagged runners
AITagPunishment: 1, // needs at least 1 tag
```

**`AIWouldPlay()`**

Return `true` to allow the operation to be played. Return `false` to hold it:

```js
// An operation that's only useful in specific circumstances
AIWouldPlay: function() {
    if (SpecificCondition()) return true;
    return false;
},
```

**`AIWouldPlayBeforeScore(cardToScore, serverToScoreIn)`**

Return `true` if this operation should be played immediately before scoring `cardToScore` in `serverToScoreIn`:

```js
AIWouldPlayBeforeScore: function(cardToScore, serverToScoreIn) {
    // play this before scoring if conditions are met
    return true;
},
```

**`AIIsRecurOrTutor`** (boolean)

Set to `true` for recursion or tutor operations. These are given lower priority so they don't crowd out economy plays:

```js
AIIsRecurOrTutor: true,
```

---

### 5.7 Agendas

**`AIAdvancementLimit()`**

Return the number of advancement counters to place on this agenda. Normally you do not need this (the AI reads `advancementRequirement`). Use it for advanceable assets/ambushes that should be advanced to a non-standard level:

```js
// Urtica Cipher: advance to 4 counters to maximise net damage
AIAdvancementLimit: function() {
    return 4;
},
```

**`AIOverAdvance`** (boolean)

Set to `true` if the AI should place extra counters on an already-scoreable agenda (e.g. for point bonuses from over-advancement):

```js
AIOverAdvance: true,
```

---

### 5.8 Inline AI Code for Corp

Like Runner, complex Corp cards include AI logic directly inside `Enumerate` or `Resolve`. Use `corp.AI.preferred` to communicate choices.

**Setting a preference (by option title):**
```js
if (corp.AI != null) {
    corp.AI._log("I know this one");
    var choice = choices[0]; // set your preferred choice
    corp.AI.preferred = { title: "CardTitle", option: choice };
}
```

**Setting a preference (install command):**
```js
if (corp.AI != null) {
    corp.AI.preferred = {
        command: "install",
        cardToInstall: cardObject,
        serverToInstallTo: serverObject
    };
}
```

**Example — Ansel 1.0 (install from HQ or Archives after checking AI install preference):**
```js
if (corp.AI != null) {
    var choice = choicesA[choicesA.length - 1]; // default: continue

    var archivesBestOption = (archivesOptions.length > 0)
        ? corp.AI._bestInstallOption(archivesOptions, false)
        : -1;
    var handBestOption = (archivesBestOption < 0 && handOptions.length > 0)
        ? corp.AI._bestInstallOption(handOptions, true)
        : -1;

    if (archivesBestOption > -1) choice = archivesChoice;
    else if (handBestOption > -1) choice = handChoice;

    corp.AI._log("I think " + choice.label + " would be best right now");
    corp.AI.preferred = { title: "Ansel 1.0", option: choice };
}
```

**Example — Brân 1.0 (install ice from hand or archives via subroutine):**
```js
// When choosing source (HQ or Archives):
if (corp.AI != null) {
    corp.AI._log("I know this one");
    var choice = choicesA[0]; // default
    if (archivesOptions.length > 0 && handOptions.length > 0)
        choice = choicesA[1]; // prefer Archives
    corp.AI.preferred = { title: "Brân 1.0", option: choice };
}

// When choosing specific ice from the selected source:
if (corp.AI != null) {
    // choose highest-printed-rez-cost ice that can be afforded and is worth rezzing
    var best = choicesB[0].card;
    var bestRC = best.rezCost;
    var wouldRezBest = CheckCredits(corp, RezCost(best), "rezzing", best) && corp.AI._iceWorthRezzing(best, RezCost(best), attackedServer);
    for (var i = 1; i < choicesB.length; i++) {
        var c = choicesB[i].card;
        var cRezCost = RezCost(c);
        if (c.rezCost > bestRC || !wouldRezBest) {
            if (CheckCredits(corp, cRezCost, "rezzing", c) && corp.AI._iceWorthRezzing(c, cRezCost, attackedServer)) {
                best = c;
                bestRC = c.rezCost;
                wouldRez = true;
            }
        }
    }
    corp.AI.preferred = { command: "install", cardToInstall: best, serverToInstallTo: attackedServer };
}
```

**Useful helper methods on `corp.AI`:**
- `corp.AI._log(msg)` — debug logging
- `corp.AI._bestInstallOption(optionList, inhibit)` — returns best index to install, or `-1` if none desirable
- `corp.AI._cardShouldBeFastAdvanced(card)` — returns true if the given card should be fast advanced
- `corp.AI._iceWorthRezzing(ice, cost, server)` — returns true if the ice is worth rezzing
- `corp.AI._isAScoringServer(server)` — true if the server can be used for scoring
- `corp.AI._potentialDamageOnBreach(server)` — estimated damage runner would take
- `corp.AI._agendasInHand()` — count of agendas currently in HQ
- `corp.AI._isAmbush(server)` — true if server contains an ambush card
- `corp.AI._bestRecurToHQOption(options, serverUnderThreat, useNowIfPossible)` — picks the best card to recur to HQ

---

## 6. The Run Calculator (`rc`)

The `RunCalculator` class simulates hypothetical runs to determine their cost and feasibility. It is passed to both `AIImplementBreaker` and `AIImplementIce` as the first argument `rc`.

### `rc.ImplementIcebreaker(...)`

The central method for describing how a breaker handles ice:

```
rc.ImplementIcebreaker(
    point,          // current checkpoint
    card,           // the breaker card (this)
    cardStrength,   // breaker's current strength (may include temp boosts)
    iceAI,          // ice AI object (from IceAI())
    iceStrength,    // ice's current strength
    iceSubTypes,    // array of subtypes this breaker handles ([] = all)
    costToUpStr,    // credit cost per pump
    amtToUpStr,     // strength gained per pump
    costToBreak,    // credit cost per break use
    amtToBreak,     // subroutines broken per use
    creditsLeft     // credit budget remaining
)
```

Returns an array of breaking possibilities. Always concatenate onto `result`.

### `rc.SrBreak(card, iceAI, point, count)`

Break `count` subroutines using `card` for free (no credit cost). Returns breaking possibilities. Used by click-break (Bioroid) and counter-based breakers (Botulus):

```js
var breakresult = rc.SrBreak(this, iceAI, point, 1); // break 1 sub
```

### `rc.StrModify(card, iceCard, point, amount, persistent)`

Reduce or increase the targeted ice's strength by `amount`. Returns a strength-modification result. Use negative values to reduce:

```js
// Leech: reduce ice strength by 1 (persistent past the encounter)
var modresult = rc.StrModify(this, iceAI.ice, point, -1, true);
modresult.virus_counters_spent += 1;
result = result.concat(modresult);
```

### `rc.precalculated`

Pre-computed data filled in before the run calculation begins. Useful properties:
- `rc.precalculated.runnerInstalledCardsLength` — number of installed Runner cards
- `rc.precalculated.runnerInstalledIcebreakersLength` — number of installed icebreakers

### Checking whether runner.AI owns the rc

Some ICE (e.g. Diviner) needs to avoid using information the runner wouldn't know. Check:

```js
if (!runner.AI || runner.AI.rc !== rc) {
    // corp is running the hypothetical — use defaults
    result.sr = [[["netDamage"]]];
    return result;
}
// runner.AI is running the hypothetical — can use hand knowledge
```

---

## 7. Quick Reference Table

### Runner AI Hooks

| Hook | Type | Purpose |
|---|---|---|
| `AIImplementBreaker` | function | Describe how this breaker handles ice in the run calculator |
| `AIPreferredInstallChoice(choices)` | function | Return preferred install index, or -1 to decline |
| `AIWastefulToInstall()` | function | Return true if installing is wasteful |
| `AIOkToTrash()` | function | Return true if this installed card can be trashed for space |
| `AIWorthKeeping(installed, spareMU)` | function | Return true to keep this card in hand during discard |
| `AISpecialBreaker` | bool | Marks non-standard breakers (Trojans etc.) |
| `AIFixedStrength` | bool | Marks breakers that can't pump strength normally |
| `AIMatchingBreakerInstalled(iceCard)` | function | Return self if this covers the given ice, else null |
| `AIPrepareHypotheticalForRC(host)` | function | Pre-run: set up fake state for run calculation |
| `AIRestoreHypotheticalFromRC()` | function | Post-run: restore state after run calculation |
| `AIEconomyInstall()` | function | Return priority for economy install, 0 to skip |
| `AIEconomyTrigger` | number | Priority for triggering economy ability |
| `AIWouldTrigger()` | function | Return true to allow ability trigger |
| `AIWouldPlay()` | function | Return true to play this event |
| `AIWastefulToPlay()` | function | Return true if playing is wasteful |
| `AIPreferredPlayChoice(choices)` | function | Return preferred choice index, -1 to hold |
| `AIRunEventExtraPotential(server, potential)` | function | Extra run potential float for run events |
| `AIRunEventModify(server)` | function | Temporarily modify game state for run calc |
| `AIRunEventRestore(server)` | function | Restore state after run calc |
| `AIRunEventExtraCredits` | number | Credits this run event provides |
| `AIAdditionalAccess(server)` | function | Return extra accesses for given server |
| `AIInstallBeforeRun(server, ...)` | function | Return priority to install before running server |
| `AIInstallBeforeInstall(card)` | function | Return true to install this before the given card |
| `AIRunAbilityExtraPotential(server, pot)` | function | Extra potential from using this card's run ability |
| `AIRunExtraPotential(server, pot)` | function | Passive extra potential from having this installed |
| `AIBreachNotRequired` | bool | True if benefit doesn't require breach |
| `AIReducesTrashCost(card)` | function | Return how much this reduces the trash cost of card |
| `AIPlayToDraw` | number | Priority for playing this card to draw |
| `AIDrawInstall()` | function | Priority for installing this draw-enabling card |
| `AIIcebreakerTutor(installed)` | function | Return the icebreaker this tutor would fetch |
| `AIPermitMoreLeeches(installed)` | function | Card-specific install limit check |

### Corp AI Hooks

| Hook | Type | Purpose |
|---|---|---|
| `AIImplementIce(rc, result, maxCred, incomplete)` | function | Describe what subroutines do in the run calculator |
| `AIImplementBreaker` | function | For bioroid ice: how the runner click-breaks them |
| `AIWorthwhileIce(server, purpose)` | function | Return true if ice is worth installing there |
| `AIDefensiveValue(server)` | function | Numeric protection value of this upgrade |
| `AIIsScoringUpgrade` | bool | True if this is a fast-advance scoring upgrade |
| `AILimitPerServer(server)` | function | Max copies of this card per server |
| `AIPreventBreach(server)` | function | True if this upgrade prevents breach |
| `AIWouldTrigger()` | function | Return true to allow upgrade ability to fire |
| `AIFastAdvance` | bool | True if this operation is used for fast advancing |
| `AIDamageOperation` | bool | True if this operation deals damage |
| `AITagPunishment` | number | Min tags needed for this punishment op to fire |
| `AIWouldPlay()` | function | Return true to play this operation |
| `AIWouldPlayBeforeScore(card, server)` | function | Return true to play before scoring |
| `AIIsRecurOrTutor` | bool | True for recursion/tutor ops (lower priority) |
| `AIAdvancementLimit()` | function | Custom advancement counter target |
| `AIOverAdvance` | bool | True if AI should over-advance this agenda |
| `AIRezForFree()` | function | True if this ice should be rezzed at zero cost to corp for on-rez effect |

---

## 8. Step-by-Step Worked Example

This section walks through adding AI support to two hypothetical new cards.

---

### Runner Card: "Spike Drill" (Fracter, 2[c]/+2 str, 2[c]/break 1 sub)

```js
cardSet[99001] = {
    title: "Spike Drill",
    elo: 1500,           // average power level
    player: runner,
    faction: "Anarch",
    influence: 2,
    cardType: "program",
    subTypes: ["Icebreaker", "Fracter"],
    memoryCost: 1,
    installCost: 4,
    strength: 2,
    strengthBoost: 0,
    modifyStrength: {
        Resolve: function(card) {
            if (card == this) return this.strengthBoost;
            return 0;
        },
    },
    abilities: [
        {
            text: "Break 1 barrier subroutine.",
            Enumerate: function() { /* ... game code ... */ },
            Resolve: function(params) { /* ... game code ... */ },
        },
        {
            text: "+2 strength.",
            Enumerate: function() { /* ... game code ... */ },
            Resolve: function(params) { /* ... game code ... */ },
        },
    ],
    responseOnEncounterEnds: {
        Resolve: function() { this.strengthBoost = 0; },
        automatic: true,
    },

    // --- AI HOOKS ---

    // Tell the run calculator how to use this breaker.
    // It's a Fracter: 2[c] pumps +2 str, 2[c] breaks 1 sub.
    AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
        result = result.concat(
            rc.ImplementIcebreaker(
                point, this, cardStrength, iceAI, iceStrength,
                ["Barrier"],  // handles Barrier ice
                2, 2,         // 2[c] per +2 strength
                2, 1,         // 2[c] per 1 subroutine broken
                creditsLeft
            )
        );
        return result;
    },

    // Don't install on the last click (save it for a run instead).
    AIPreferredInstallChoice: function(choices) {
        if (runner.clickTracker < 2) return -1;
        return 0;
    },

    // Reasonably powerful, so keep it in hand until installed.
    AIWorthKeeping: function(installedRunnerCards, spareMU) {
        // keep if no fracter is installed yet (need it eventually)
        for (var i = 0; i < installedRunnerCards.length; i++) {
            if (CheckSubType(installedRunnerCards[i], "Fracter")) return false;
        }
        return true; // keep a fracter in hand until one is installed
    },
};
```

---

### Corp Card: "Spike Trap" (Code Gate, 4 strength, two subroutines: 1 net damage, then ETR)

```js
cardSet[99002] = {
    title: "Spike Trap",
    elo: 1520,
    player: corp,
    faction: "Jinteki",
    influence: 2,
    cardType: "ice",
    rezCost: 4,
    strength: 4,
    subTypes: ["Code Gate"],
    subroutines: [
        {
            text: "Do 1 net damage.",
            Resolve: function() { Damage("net", 1, true); },
        },
        {
            text: "End the run.",
            Resolve: function() { EndTheRun(); },
        },
    ],

    // --- AI HOOKS ---

    // Describe the subroutines to the run calculator.
    // Sub 1: deals 1 net damage.
    // Sub 2: ends the run.
    AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
        result.sr = [
            [["netDamage"]],   // sub 1: 1 net damage
            [["endTheRun"]],   // sub 2: end the run
        ];
        return result;
    },
};
```

---

### Corp Card: "Rapid Mobilization" (Operation, fast advance: place 2 counters)

This is a fast-advance operation similar to Seamless Launch.

```js
cardSet[99003] = {
    title: "Rapid Mobilization",
    elo: 1800,
    player: corp,
    faction: "Haas-Bioroid",
    influence: 2,
    cardType: "operation",
    playCost: 1,

    Enumerate: function() {
        var choices = ChoicesInstalledCards(corp, function(card) {
            if (CheckAdvance(card)) {
                if (corp.AI != null) return corp.AI._cardShouldBeFastAdvanced(card);
                return true;
            }
        });
        if (corp.AI != null) {
            if (this.AIPreferredTarget) {
                for (var i = 0; i < choices.length; i++) {
                    if (choices[i].card == this.AIPreferredTarget) return [choices[i]];
                }
            }
            Shuffle(choices); // randomise to avoid predictability
        }
        return choices;
    },
    Resolve: function(params) {
        PlaceAdvancement(params.card, 2);
    },

    // Tell the AI this is a fast-advance card.
    AIFastAdvance: true,
};
```

---

That covers the full AI hook system. With these patterns you can add solid AI support to almost any card in the game. When in doubt, look at how an existing similar card implements its hooks in the `sets/` files — particularly `systemgateway.js`, which is the most comprehensively annotated set.
