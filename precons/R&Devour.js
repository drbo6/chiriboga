// Noise preconstructed deck
registerPrecon({
	// name: Display name of the precon deck
	name: "R&Devour",
	// identity: Card ID of the identity/commander for this deck
	identity: "1001",
	// useAsCustomDefault: Whether this deck is the default choice for its identity when auto-selecting
	useAsCustomDefault: false,
	// useForQuickGame: Whether to include this deck in Quick Game selection
	useForQuickGame: false,
	// useForGauntlet: Whether to include this deck in Gauntlet mode selection
	useForGauntlet: false,
	// useForCustomGame: Whether to include this deck in Custom Game mode
	useForCustomGame: true,
	// deck_set: The set or category this deck belongs to
	deck_set: "none",
	URL: "https://netrunnerdb.com/en/decklist/8b58fb00-8ba6-4445-aafd-d0a1f6d86a7a/starter-deck-runner-intermediate",
	notes: "This deck introduces virus programs and fixed-strength breakers. Yog.0 and Mimic break ice for free when Datasucker lowers their strength. Parasite destroys ice over time, and Medium lets you see more cards from R&D. Noise's ability mills a card whenever you install a virus, so keep installing to disrupt the Corp's plans. Special Order finds the breaker you need.",
	cards: {
		"1002": 2,  // Déjà Vu
		"1005": 3,  // Cyberfeeder
		"1006": 1,  // Grimoire
		"1008": 2,  // Datasucker
		"1009": 2,  // Djinn
		"1010": 2,  // Medium
		"1012": 3,  // Parasite
		"1014": 2,  // Yog.0
		"1019": 3,  // Easy Mark
		"1022": 2,  // Special Order
		"1038": 2,  // Akamatsu Mem Chip
		"1040": 2,  // The Personal Touch
		"1045": 2,  // Net Shield
		"1049": 3,  // Infiltration
		"1051": 3,  // Crypsis
		"1053": 3,  // Armitage Codebusting
		"30030": 3,  // Sure Gamble
		"31006": 2,  // Corroder
		"31008": 2,  // Mimic
		"31009": 1   // Ice Carver
	},
	// sets: The set codes this deck is designed with
	sets: ["core", "su21", "sg"]
});