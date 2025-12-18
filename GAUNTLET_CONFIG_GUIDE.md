# Gauntlet Configuration Guide

## Overview

The `config-gauntlet.js` file controls which cards are available in gauntlet mode. It has two main sections:

### 1. Fixed Cards

Specific card IDs that are always included. Every card listed here will have exactly 3 copies in the gauntlet pool.

```javascript
fixedCards: [
  30028, // Jailbreak
  30029, // Overclock
  30030, // Sure Gamble
  // ... more cards
]
```

### 2. Random Card Requirements

Dynamically selects random cards based on card type and subtype. Cards are selected from the full card pool and added to the subset.

#### Structure of a Requirement

```javascript
{
  quantity: 3,                              // How many cards to select
  cardType: 'hardware',                     // Card type (identity, agenda, event, etc.)
  matchSubtypes: ['Console'],               // Card MUST have ALL these subtypes
  excludeSubtypes: []                       // Card MUST NOT have ANY of these
}
```

- **quantity**: Number of cards to select (each gets 3 copies)
- **cardType**: Must match card's `.cardType` property exactly (lowercase)
- **matchSubtypes**: Card must have ALL listed subtypes to be selected. Empty array = any subtypes OK
- **excludeSubtypes**: Card is excluded if it has ANY of these subtypes. Empty array = no exclusions

#### Supported Card Types

- `identity`
- `agenda`
- `operation`
- `event`
- `asset`
- `upgrade`
- `hardware`
- `program`
- `resource`
- `ice`

#### Common Subtypes

**Hardware:**
- Console
- Chip
- Link
- etc.

**Programs:**
- Icebreaker
- Fracter (paired with Icebreaker)
- Decoder (paired with Icebreaker)
- Killer (paired with Icebreaker)
- AI (paired with Icebreaker)
- Virus
- Trojan
- etc.

**Resources:**
- Connection
- Seedy
- Job
- etc.

**Events:**
- Run
- Sabotage
- etc.

## Examples

### Get 3 random Killer icebreakers:
```javascript
{ quantity: 3, cardType: 'program', matchSubtypes: ['Icebreaker', 'Killer'], excludeSubtypes: [] }
```

### Get 2 non-console hardware:
```javascript
{ quantity: 2, cardType: 'hardware', matchSubtypes: [], excludeSubtypes: ['Console'] }
```

### Get 5 random events of any type:
```javascript
{ quantity: 5, cardType: 'event', matchSubtypes: [], excludeSubtypes: [] }
```

### Get 4 programs that are NOT icebreakers:
```javascript
{ quantity: 4, cardType: 'program', matchSubtypes: [], excludeSubtypes: ['Icebreaker'] }
```

## How It Works

1. **Fixed cards are added first** - These are guaranteed in the pool with 3 copies each
2. **Random selections are made in order** - Each requirement is processed sequentially
3. **No duplicates** - Cards already used (either as fixed or randomly selected) cannot be selected again
4. **Each random card gets 3 copies** - Every selected card is added 3 times to the final deck
5. **Console output** - All selected cards are logged to browser console for verification

## Total Cards in Pool

The gauntlet subset size = (# fixed cards × 3) + (total random cards selected × 3)

For the default config:
- 6 fixed cards × 3 = 18 copies
- ~17 random cards × 3 = ~51 copies
- **Total ≈ 69 cards** (typical 40-card runner deck can draw heavily from this pool)
