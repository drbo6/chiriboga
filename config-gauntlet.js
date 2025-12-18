// Gauntlet Mode Configuration
// Define the card subset that is available in gauntlet mode

var gauntletConfig = {
  // ===== FIXED CARDS =====
  // Specific card IDs with exact quantities
  // Each card object specifies: id (card ID) and quantity (how many copies)
  fixedCards: [
    { id: 30028, quantity: 3 },  // Jailbreak
    { id: 30029, quantity: 3 },  // Overclock
    { id: 30030, quantity: 3 },  // Sure Gamble
    { id: 30033, quantity: 3 },  // Smartware Distributor
    { id: 30034, quantity: 3 },  // Verbal Plasticity
    { id: 30032, quantity: 3 }   // Mayfly
  ],

  // ===== RANDOM CARD POOL SPECIFICATIONS =====
  // Define how many random cards to add from each category
  // quantity = total number of random selections to make (cards can repeat)
  // Each object specifies: quantity, cardType, and subtypes to match
  // Subtypes are case-sensitive and must match exactly as printed on cards
  randomCardRequirements: [
    // Hardware
    { quantity: 2, cardType: 'hardware', matchSubtypes: ['Console'], excludeSubtypes: [] },
    { quantity: 4, cardType: 'hardware', matchSubtypes: [], excludeSubtypes: ['Console'] },
    
    // Resources (any subtype)
    { quantity: 15, cardType: 'resource', matchSubtypes: [], excludeSubtypes: [] },
    
    // Programs - Icebreakers
    { quantity: 3, cardType: 'program', matchSubtypes: ['Icebreaker', 'Killer'], excludeSubtypes: [] },
    { quantity: 3, cardType: 'program', matchSubtypes: ['Icebreaker', 'Fracter'], excludeSubtypes: [] },
    { quantity: 3, cardType: 'program', matchSubtypes: ['Icebreaker', 'Decoder'], excludeSubtypes: [] },
    { quantity: 1, cardType: 'program', matchSubtypes: ['Icebreaker', 'AI'], excludeSubtypes: [] },
    
    // Programs - Other
    { quantity: 10, cardType: 'program', matchSubtypes: [], excludeSubtypes: ['Icebreaker'] },
    
    // Events
    { quantity: 18, cardType: 'event', matchSubtypes: [], excludeSubtypes: [] }
  ]
};
