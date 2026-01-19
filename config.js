// =============================================================================
// SET REGISTRY - Controls which card sets are loaded in each game mode
// =============================================================================
// 
// This is the central configuration for set availability across game modes.
// Sets are loaded dynamically based on user settings stored in localStorage.
// These arrays define the DEFAULT sets when no user settings exist.
//
// =============================================================================
// HOW TO ADD A NEW SET
// =============================================================================
//
// 1. CREATE THE SET FILE
//    - Create a new file: sets/yoursetname.js
//    - Follow the format of existing set files (e.g., systemgateway.js)
//    - Each card needs a unique ID in an unused range:
//        Core Set:           1000-1999
//        Downfall:           26001-26065
//        System Gateway:     30000-30999
//        System Update 2021: 31000-31999
//        Midnight Sun:       33000-33065
//        Parhelion:          33066-33128
//        Elevation:          35000-35999
//    - Choose an unused range for your new set (e.g., 36000-36999)
//
// 2. ADD TO availableSets BELOW
//    Add an entry to the availableSets object:
//
//    yoursetname: { 
//      file: 'yoursetname',      // Filename without .js extension (in /sets/)
//      code: 'yrsn',             // Short code for localStorage (2-4 chars)
//      name: 'Your Set Name',    // Display name shown in settings UI
//      hidden: true,             // true = hidden until easter egg revealed
//      untested: true,           // true = shows "(Untested)" in settings
//      idRange: [36000, 36999],  // Card ID range [start, end] for this set
//    },
//
// 3. ADD TO CARD DATA (for card images and metadata)
//    - Add card entries to carddata/carddata.json
//    - Each entry needs: code, title, pack_code (your set's code)
//    - This enables card images and NRDB-style data lookups
//
// 4. (OPTIONAL) ADD TO DEFAULT SETS IF YOU WANT ALWAYS LOAD THEM LIKE SYSTEM GATEWAY
//    - Add 'yoursetname' to gauntletSets array for gauntlet mode defaults
//    - Add 'yoursetname' to decklauncherSets array for custom game defaults
//    - Note: systemgateway is always loaded and cannot be disabled
//
// 5. (OPTIONAL) The display order in index.php settings UI follows the order
//    sets are defined in availableSets above. Place new sets in desired position.
//
// =============================================================================
// SET PROPERTIES REFERENCE
// =============================================================================
//
// file:     The filename in /sets/ without the .js extension
// code:     Short identifier used in localStorage and internal references
// name:     Human-readable name displayed in the settings UI
// hidden:   If true, set won't appear in settings until user clicks the
//           "Load Sets" label 6 times (easter egg). Use for incomplete sets.
// untested: If true, "(Untested)" is appended to the name in settings UI
// idRange:  [start, end] - The card ID range for this set. Used to map cards
//           to sets when carddata.json doesn't have pack_code info.
//
// =============================================================================
// CURRENT SETS
// =============================================================================
//
//   Key              File               Code   Description
//   ─────────────────────────────────────────────────────────────────────
//   coreset          coreset.js         core   Original Core Set (1000-1999)
//   downfall         downfall.js        df     Downfall (26001-26065)
//   uprising         uprising.js        ur     Uprising (26066-26130)
//   systemgateway    systemgateway.js   sg     System Gateway (30000-30999)
//   systemupdate2021 systemupdate2021.js su21  System Update 2021 (31000-31999)
//   midnightsun      midnightsun.js     ms     Midnight Sun (33000-33065)
//   parhelion        parhelion.js       ph     Parhelion (33066-33128)
//   rebellion        rebellion.js       rwr    Rebellion Without Rehearsal (34066-34130)
//   elevation        elevation.js       elev   Elevation (35000-35999)
//
// Special engine-only sets (not in registry, loaded directly by engine.php):
//   gauntlet         gauntlet.js        -      Gauntlet-specific cards
//   tutorial         tutorial.js        -      Tutorial cards
//
// =============================================================================

var setRegistry = {
  // All available card sets with their file names and set codes
  // The 'code' is used for settings storage and UI checkboxes
  // Set 'hidden: true' to hide a set from the index.php settings UI
  // Set 'untested: true' to show "(Untested)" after the set name
  // Set 'idRange: [start, end]' for card ID range (used for set detection)
  // NOTE: Display order in settings UI follows the order defined here
  availableSets: {
    systemgateway:    { file: 'systemgateway',    code: 'sg',   name: 'System Gateway',     hidden: false, untested: false, idRange: [30000, 30999] },
    systemupdate2021: { file: 'systemupdate2021', code: 'su21', name: 'System Update 2021', hidden: false, untested: false, idRange: [31000, 31999] },
    downfall:         { file: 'downfall',         code: 'df',   name: 'Downfall',           hidden: true,  untested: true,  idRange: [26001, 26065] },
    midnightsun:      { file: 'midnightsun',      code: 'ms',   name: 'Midnight Sun',       hidden: true,  untested: true,  idRange: [33000, 33065] },
    parhelion:        { file: 'parhelion',        code: 'ph',   name: 'Parhelion',          hidden: true,  untested: true,  idRange: [33066, 33128] },
    elevation:        { file: 'elevation',        code: 'elev', name: 'Elevation',          hidden: false, untested: true,  idRange: [35000, 35999] },
    uprising:         { file: 'uprising',         code: 'ur',   name: 'Uprising',           hidden: true,  untested: true,  idRange: [26066, 26130] },
    rebellion:        { file: 'rebellion',        code: 'rwr',  name: 'Rebellion Without Rehearsal', hidden: true, untested: true, idRange: [34066, 34130] },
    coreset:          { file: 'coreset',          code: 'core', name: 'Core Set',           hidden: true,  untested: true,  idRange: [1000, 1999]   },    
  },

  // DEFAULT sets for GAUNTLET mode (when no localStorage settings exist)
  // User can change via Settings > Gauntlet Settings > Load Player Sets
  // Note: All sets are always LOADED in gauntlet.php so opponent decks work,
  // but only sets listed here (or in user's localStorage) populate the player's card pool
  gauntletSets: [
    'systemgateway',
    'systemupdate2021',
    // 'elevation',    
    // 'midnightsun',
    // 'parhelion',
    // 'coreset',    
  ],

  // DEFAULT sets for CUSTOM GAME / Decklauncher mode (when no localStorage settings exist)
  // User can change via Settings > Custom Game Settings > Load Sets
  decklauncherSets: [
    'systemgateway',
    'systemupdate2021',
    // 'elevation',
    // 'midnightsun',
    // 'parhelion',
    // 'coreset',    
  ],
};

// =============================================================================
// GAUNTLET MODE CONFIGURATION
// =============================================================================
// Define the card subset that is available in gauntlet mode

var gauntletConfig = {
  // ===== GAUNTLET LENGTH =====
  // Number of opponents in a gauntlet run (one from each faction)
  gauntletLength: 8,

  // ===== HI-RES IMAGES =====
  // If true, the renderer will use high-resolution images from "images/hires" when available.
  // Set to false to always use low-res images.
  enableHiRes: false,

  // ===== STARTING CREDITS =====
  // Number of credits the player starts with in gauntlet mode
  startingCredits: 30,

  // ===== MATCH REWARDS =====
  // Credits awarded or deducted based on game outcomes
  // Positive values = credits gained, Negative values = credits lost
  matchRewards: {
    victory: 5,                // Credits for winning a match
    agendaPointStolen: 3,      // Credits per agenda point stolen from corp
    agendaPointScored: -2,     // Credits per agenda point scored by corp (negative = loss)
    minimalCredits: 10,        // Minimum credits player can have (prevents going negative)
    bossBeaten: 10,            // Additional credits for defeating a boss opponent
    creditScoreDivisor: 10     // Divide total credits by this number when calculating final score
  },

  // ===== HACK OPPONENT SETTINGS =====
  // Configuration for the hack opponent feature
  hackOpponent: {
    // Cost in credits for each hack action
    viewDecklistCost: 5,
    viewPerkCost: 5,
    disablePerkCost: 15,        // Cost for disabling regular perks (1-3)
    disableBossPerkCost: 30,    // Cost for disabling boss perks (4-6)
    
    // Success chance ranges (min/max percentage)
    // Actual chance is randomly determined within this range for each attempt
    viewDecklistChanceMin: 25,
    viewDecklistChanceMax: 75,
    viewPerkChanceMin: 75,
    viewPerkChanceMax: 95,
    disablePerkChanceMin: 50,
    disablePerkChanceMax: 75,
    disableBossPerkChanceMin: 25,
    disableBossPerkChanceMax: 50,
    
    // Prepare Hack settings - temporary bonus to success chance
    prepareHackCost: 3,              // Cost in credits to prepare a hack
    prepareHackBonusMin: 5,          // Minimum percentage bonus added
    prepareHackBonusMax: 15,         // Maximum percentage bonus added
    
    // Failure recovery settings - bonus after a failed hack attempt
    // The new chance after failure will always be higher than the failed attempt
    failureRecoveryBonusMin: 5,      // Minimum additional percentage after failure
    failureRecoveryBonusMax: 15,     // Maximum additional percentage after failure
    
    // Card loss risk when attempting to disable perks
    // regularHack = disabling regular perks (1-3), bossHack = disabling boss perks (4-6)
    regularHackCardLossPercentage: 25,        // Chance (%) of triggering card loss on regular hack
    regularHackIndividualCardLossPercentage: 50,  // Chance (%) per card up to max quantity
    regularHackCardLossMaxQuantity: 2,        // Maximum number of cards that can be lost
    bossHackCardLossPercentage: 40,           // Chance (%) of triggering card loss on boss hack
    bossHackIndividualCardLossPercentage: 60, // Chance (%) per card up to max quantity
    bossHackCardLossMaxQuantity: 3,           // Maximum number of cards that can be lost
    prioritizeDeckCardLoss: true              // If true, prioritize removing cards from current deck
  },

  // ===== SHOP SETTINGS =====
  // Configuration for the card shop
  shop: {
    rerollPacksCost: 5,         // Cost to re-roll the available packs
    unlockIdentityCost: 50      // Cost to unlock identity selection after first win
  },

  // ===== ALTERNATE FACTIONS =====
  // If true, each selected precon represents a different faction (one opponent per faction)
  // If false, randomly selects precons across all factions without faction requirement
  alternateFactions: true,

  // ===== NEUTRAL BOSS CHANCE =====
  // Chance (0.0 to 1.0) that a neutral deck will replace the final gauntlet opponent
  // Only applies when alternateFactions is true and neutral corp precons are available
  // Set to 0 to disable neutral bosses, 1.0 to always have a neutral final boss
  neutralBossChance: 1.0,

  // ===== BALANCED FACTIONS =====
  // If true, random card selection will balance across runner factions (Anarch, Criminal, Shaper, Neutral)
  // This prevents factions with more cards of a given type from dominating the card pool
  // If false, cards are selected purely randomly from the matching pool
  balancedFactions: true,

  // ===== STRICT PACKS =====
  // If true, shop packs only contain cards that match their category
  // (e.g. Anarch Pack = only Anarch + Neutral cards, Program Pack = only programs)
  // This makes the game easier but applies a 20% score penalty
  // If false, pack names only indicate weighted probabilities
  strictPacks: false,

  // ===== ALLOWED IDENTITIES =====
  // Specify which identities can be used in gauntlet mode
  // Leave empty array to allow all identities
  allowedIdentities: {
    // Runner identities (excluding The Catalyst and Esa Afzali)
    runnerIds: [
      35001, // Phoenix
      30001, // Rene
      30010, // Zahya
      30019, // Tao
      31001, // Quetzal
      31002, // Reina
      31013, // Ken
      31014, // Steve
      31025, // Ayla
      31026 // Rielle
    ],
    // Corp identities (currently unused)
    corpIds: []
  },

  // ===== LOCKED FIXED CARDS =====
  // If true, cards listed in fixedCards are excluded from the initial random card pool
  // If false, fixed cards can also appear in the initial random card pool
  lockedFixedCards: false,

  // ===== FIXED CARDS =====
  // Specific card IDs with exact quantities
  // Each card object specifies: id (card ID) and quantity (how many copies)
  fixedCards: [
    { id: 30028, quantity: 3 },  // Jailbreak
    { id: 30029, quantity: 3 },  // Overclock
    { id: 30030, quantity: 3 },  // Sure Gamble
    { id: 30031, quantity: 3 },  // T400 Memory Diamond
    { id: 30032, quantity: 3 },  // Mayfly    
    { id: 30033, quantity: 3 },  // Smartware Distributor
    { id: 30034, quantity: 3 },  // Verbal Plasticity
  ],

  // ===== RANDOM CARD POOL SPECIFICATIONS =====
  // Define how many random cards to add from each category
  // quantity = total number of random selections to make (cards can repeat)
  // Each object specifies: quantity, cardType, and subtypes to match
  // Subtypes are case-sensitive and must match exactly as printed on cards
  randomCardRequirements: [
    // Hardware
    { quantity: 1, cardType: 'hardware', matchSubtypes: ['Console'], excludeSubtypes: [] },
    { quantity: 3, cardType: 'hardware', matchSubtypes: [], excludeSubtypes: ['Console'] },
    
    // Resources (any subtype)
    { quantity: 5, cardType: 'resource', matchSubtypes: [], excludeSubtypes: [] },
    
    // Programs - Icebreakers
    { quantity: 1, cardType: 'program', matchSubtypes: ['Icebreaker', 'Killer'], excludeSubtypes: [] },
    { quantity: 1, cardType: 'program', matchSubtypes: ['Icebreaker', 'Fracter'], excludeSubtypes: [] },
    { quantity: 1, cardType: 'program', matchSubtypes: ['Icebreaker', 'Decoder'], excludeSubtypes: [] },
    
    // Programs - Other
    { quantity: 1, cardType: 'program', matchSubtypes: ['Trojan'], excludeSubtypes: ['Icebreaker'] },
    { quantity: 3, cardType: 'program', matchSubtypes: [], excludeSubtypes: ['Icebreaker'] },
    
    // Events
    { quantity: 8, cardType: 'event', matchSubtypes: [], excludeSubtypes: [] }
  ],

  // ===== CARD PACK CONFIGURATIONS =====
  // Define card pack types available for purchase with their distribution factors
  // Each pack specifies card quantity and factors for faction and card type probabilities
  cardPacks: [
    {
      name: 'Anarch Pack',
      cost: 10,
      cardQuantity: 5,
      factionFactors: {
        anarch: 5,
        criminal: 1,
        shaper: 1,
        neutral: 1
      },
      typeFactors: {
        event: 1,
        resource: 1,
        program: 1,
        hardware: 1
      }
    },
    {
      name: 'Criminal Pack',
      cost: 10,
      cardQuantity: 5,
      factionFactors: {
        anarch: 1,
        criminal: 5,
        shaper: 1,
        neutral: 1
      },
      typeFactors: {
        event: 1,
        resource: 1,
        program: 1,
        hardware: 1
      }
    },
    {
      name: 'Shaper Pack',
      cost: 10,
      cardQuantity: 5,
      factionFactors: {
        anarch: 1,
        criminal: 1,
        shaper: 5,
        neutral: 1
      },
      typeFactors: {
        event: 1,
        resource: 1,
        program: 1,
        hardware: 1
      }
    },
    {
      name: 'Event Pack',
      cost: 10,
      cardQuantity: 5,
      factionFactors: {
        anarch: 1,
        criminal: 1,
        shaper: 1,
        neutral: 1
      },
      typeFactors: {
        event: 4,
        resource: 1,
        program: 1,
        hardware: 1
      }
    },
    {
      name: 'Resource Pack',
      cost: 10,
      cardQuantity: 5,
      factionFactors: {
        anarch: 1,
        criminal: 1,
        shaper: 1,
        neutral: 1
      },
      typeFactors: {
        event: 1,
        resource: 4,
        program: 1,
        hardware: 1
      }
    },
    {
      name: 'Program Pack',
      cost: 10,
      cardQuantity: 5,
      factionFactors: {
        anarch: 1,
        criminal: 1,
        shaper: 1,
        neutral: 1
      },
      typeFactors: {
        event: 1,
        resource: 1,
        program: 4,
        hardware: 1
      }
    },
    {
      name: 'Hardware Pack',
      cost: 10,
      cardQuantity: 5,
      factionFactors: {
        anarch: 1,
        criminal: 1,
        shaper: 1,
        neutral: 1
      },
      typeFactors: {
        event: 1,
        resource: 1,
        program: 1,
        hardware: 4
      }
    }
  ]

};