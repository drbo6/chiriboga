//CARD DEFINITIONS FOR DOWNFALL
setIdentifiers.push('df');

//Chisel (26003)
//Anarch Program: Virus - Trojan, cost 2, MU 1, influence 4
//Install only on a piece of ice.
//Host ice gets -1 strength for each hosted virus counter.
//Whenever you encounter host ice, if its strength is 0 or less, trash it.
//Otherwise, place 1 virus counter on this program.
cardSet[26003] = {
  title: "Chisel",
  imageFile: "26003.png",
  player: runner,
  faction: "Anarch",
  influence: 4,
  cardType: "program",
  subTypes: ["Virus", "Trojan"],
  installCost: 2,
  memoryCost: 1,
  
  //Install only on a piece of ice.
  installOnlyOn: function (card) {
    if (!CheckCardType(card, ["ice"])) return false;
    return true;
  },
  
  //Host ice gets -1 strength for each hosted virus counter.
  modifyStrength: {
    Resolve: function (card) {
      //Only affect host ice, with null check for when host is trashed
      if (this.host && card == this.host) {
        return -Counters(this, "virus");
      }
      return 0; //no modification to strength
    },
  },
  
  //Whenever you encounter host ice, if its strength is 0 or less, trash it.
  //Otherwise, place 1 virus counter on this program.
  responseOnEncounter: {
    Enumerate: function (card) {
      //Only trigger when encountering host ice
      if (!CheckEncounter()) return [];
      if (!this.host) return [];
      if (attackedServer.ice[approachIce] != this.host) return [];
      return [{}]; //Mandatory trigger - one option
    },
    Resolve: function (params) {
      var hostIce = this.host;
      if (!hostIce) return; //Safety check
      var iceStrength = Strength(hostIce);
      
      if (iceStrength <= 0) {
        //Trash the host ice
        Log(GetTitle(this) + " trashes " + GetTitle(hostIce));
        Trash(hostIce, false);
        //End the encounter since the ice is gone
        //The runner will pass this position per CR rule 8.5.10
        encountering = false;
      } else {
        //Place 1 virus counter
        AddCounters(this, "virus", 1);
      }
    },
    text: "Chisel triggers",
    //Note: NOT automatic because Trash() causes phase changes for responseOnWouldTrash
  },
  
  //AI: Marks Chisel as a special breaker (non-icebreaker that deals with ice)
  AISpecialBreaker: true,
  
  //AI: Check if Chisel can handle specific ice (will trash it on encounter)
  AIMatchingBreakerInstalled: function (iceCard) {
    if (this.host && this.host == iceCard) {
      //Check if Chisel will trash the ice (strength <= 0 after virus reduction)
      var iceStrength = Strength(iceCard);
      if (iceStrength <= 0) {
        return this; //Chisel will trash this ice
      }
    }
    return null;
  },
  
  //AI: Prefer to install on high-value ice
  AIPreferredInstallChoice: function (choices) {
    //Don't install on ice that already has Chisel or similar trojans
    var htsi = runner.AI._highestThreatScoreIce([this].concat(runner.AI._iceHostingSpecialBreakers()));
    
    //Find the best target
    var bestIndex = -1;
    var bestValue = 0;
    
    for (var i = 0; i < choices.length; i++) {
      var targetIce = choices[i].host;
      if (!targetIce) continue;
      
      //Calculate value based on ice strength and rez cost
      var value = 0;
      
      //Prefer ice we know about (rezzed or known)
      if (targetIce.rezzed || targetIce.knownToRunner) {
        var iceStrength = Strength(targetIce);
        var iceCost = RezCost(targetIce);
        
        //Value based on how hard the ice is to deal with
        value = iceStrength + (iceCost / 2);
        
        //Bonus for ice we can destroy quickly (low strength)
        if (iceStrength <= 3) {
          value += 3;
        }
        
        //Bonus for expensive ice
        if (iceCost >= 5) {
          value += 2;
        }
      } else {
        //Unknown ice - moderate value
        value = 2;
      }
      
      //Prefer the highest threat score ice if available
      if (targetIce == htsi) {
        value += 5;
      }
      
      if (value > bestValue) {
        bestValue = value;
        bestIndex = i;
      }
    }
    
    //Only install if we found a reasonable target
    if (bestValue >= 2) {
      return bestIndex;
    }
    
    return -1; //don't install if no good target
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping if there's rezzed ice to target
    var installedCorpCards = InstalledCards(corp);
    for (var i = 0; i < installedCorpCards.length; i++) {
      var card = installedCorpCards[i];
      if (CheckCardType(card, ["ice"]) && (card.rezzed || card.knownToRunner)) {
        return true;
      }
    }
    //Also worth keeping if there's any ice at all
    for (var i = 0; i < installedCorpCards.length; i++) {
      if (CheckCardType(installedCorpCards[i], ["ice"])) {
        return true;
      }
    }
    return false;
  },
};