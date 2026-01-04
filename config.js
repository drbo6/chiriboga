// Gauntlet Mode Configuration
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
  startingCredits: 30000,

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
    
    // Card loss risk when attempting to disable perks
    // regularHack = disabling regular perks (1-3), bossHack = disabling boss perks (4-6)
    regularHackCardLossPercentage: 100,        // Chance (%) of triggering card loss on regular hack
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

  // ===== ALLOWED SETS =====
  // Specify which card sets to include in the gauntlet card pool
  // Leave empty array to allow all sets
  // Valid set codes: 'sg' (System Gateway), 'su21' (System Update 2021), 'ms' (Midnight Sun), 'elev' (Elevation)
  allowedSets: [
    'sg',     // System Gateway
    'su21',   // System Update 2021
    //'ms',     // Midnight Sun
    //'elev'    // Elevation
  ],

  // ===== LOCKED FIXED CARDS =====
  // If true, cards listed in fixedCards are excluded from the initial random card pool
  // If false, fixed cards can also appear in the initial random card pool
  lockedFixedCards: true,

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
    { quantity: 10, cardType: 'hardware', matchSubtypes: ['Console'], excludeSubtypes: [] },
    { quantity: 30, cardType: 'hardware', matchSubtypes: [], excludeSubtypes: ['Console'] },
    
    // Resources (any subtype)
    { quantity: 50, cardType: 'resource', matchSubtypes: [], excludeSubtypes: [] },
    
    // Programs - Icebreakers
    { quantity: 10, cardType: 'program', matchSubtypes: ['Icebreaker', 'Killer'], excludeSubtypes: [] },
    { quantity: 10, cardType: 'program', matchSubtypes: ['Icebreaker', 'Fracter'], excludeSubtypes: [] },
    { quantity: 10, cardType: 'program', matchSubtypes: ['Icebreaker', 'Decoder'], excludeSubtypes: [] },
    
    // Programs - Other
    { quantity: 40, cardType: 'program', matchSubtypes: [], excludeSubtypes: ['Icebreaker'] },
    
    // Events
    { quantity: 80, cardType: 'event', matchSubtypes: [], excludeSubtypes: [] }
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