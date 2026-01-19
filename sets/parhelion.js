//CARD DEFINITIONS FOR PARHELION
setIdentifiers.push('ph');

cardSet[33092] = {
  title: "Dr. Nuka Vrolyck",
  imageFile: "33092.png",
  player: runner,
  faction: "Shaper",
  influence: 2,
  cardType: "resource",
  subTypes: ["Connection"],
  installCost: 1,
  unique: true,
  //When you install this resource, load 2 power counters onto it. 
  //When it is empty, trash it.
  //[click], hosted power counter: Draw 3 cards.
  
  automaticOnInstall: {
    Resolve: function (card) {
      if (card == this) {
        AddCounters(this, "power", 2);
      }
    },
  },
  
  //Click ability: spend 1 power counter to draw 3 cards
  Enumerate: function () {
    if (!CheckActionPhase()) return [];
    if (!CheckClicks(1)) return [];
    if (!CheckCounters(this, "power", 1)) return [];
    return [{}];
  },
  Resolve: function (params) {
    SpendClicks(1);
    SpendCounters(this, "power", 1);
    Draw(runner, 3);
    //Check if empty and trash
    if (!CheckCounters(this, "power", 1)) {
      Log(GetTitle(this, true) + " trashed (empty)");
      MoveCard(this, runner.heap);
      this.faceUp = true;
    }
  },
  
  //AI helper functions
  AIWouldUse: function () {
    //Use if we have counters and could benefit from cards
    if (!CheckCounters(this, "power", 1)) return -1;
    //Good to use if we have few cards in hand
    if (runner.grip.length <= 3) return 3;
    //Moderate priority otherwise
    return 1;
  },
  
  AIEconomyInstall: function () {
    //Good card draw, moderate priority
    return 2;
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping - cheap card draw
    return true;
  },
};