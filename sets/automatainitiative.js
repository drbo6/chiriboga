//CARD DEFINITIONS FOR THE AUTOMATA INITIATIVE
setIdentifiers.push('tai');

//Saci (34017)
//Criminal Program: Trojan, cost 1, MU 1, influence 1
//Install only on a piece of ice.
//Whenever host ice is rezzed or derezzed, gain 3 credits.
cardSet[34017] = {
  title: "Saci",
  imageFile: "34017.png",
  player: runner,
  faction: "Criminal",
  influence: 1,
  cardType: "program",
  subTypes: ["Trojan"],
  installCost: 1,
  memoryCost: 1,
  
  //Install only on a piece of ice.
  installOnlyOn: function (card) {
    if (!CheckCardType(card, ["ice"])) return false;
    return true;
  },
  
  //Whenever host ice is rezzed, gain 3 credits.
  automaticOnRez: {
    Resolve: function (card) {
      //Only trigger when host ice is rezzed
      if (!this.host) return;
      if (card != this.host) return;
      Log(GetTitle(this) + " triggers");
      GainCredits(runner, 3, "", this);
    },
  },
  
  //Whenever host ice is derezzed, gain 3 credits.
  automaticOnDerez: {
    Resolve: function (card) {
      //Only trigger when host ice is derezzed
      if (!this.host) return;
      if (card != this.host) return;
      Log(GetTitle(this) + " triggers");
      GainCredits(runner, 3, "", this);
    },
  },
  
  //Tell Corp AI that this trojan is not a serious threat - Corp should still rez ice
  //Unlike Tranquilizer which derezzes ice, Saci only gives Runner 3c which is acceptable
  AIHostedDoesNotPreventRez: true,
  
  //AI: Prefer to install on unrezzed ice (to get credit when it rezzes)
  AIPreferredInstallChoice: function (choices) {
    //Find the best target - prefer unrezzed ice on servers we want to run
    var bestIndex = -1;
    var bestValue = 0;
    
    for (var i = 0; i < choices.length; i++) {
      var targetIce = choices[i].host;
      if (!targetIce) continue;
      
      var value = 1; //base value
      
      //Prefer unrezzed ice (we'll get credits when it rezzes)
      if (!targetIce.rezzed) {
        value += 5;
      }
      
      //Prefer ice on central servers (more likely to run there)
      var server = GetServer(targetIce);
      if (server == corp.HQ || server == corp.RnD || server == corp.archives) {
        value += 2;
      }
      
      //Prefer outermost ice (more likely to be encountered/rezzed)
      if (server && server.ice) {
        var iceIndex = server.ice.indexOf(targetIce);
        if (iceIndex == server.ice.length - 1) {
          value += 1; //outermost
        }
      }
      
      if (value > bestValue) {
        bestValue = value;
        bestIndex = i;
      }
    }
    
    //Install if we found any reasonable target
    if (bestValue >= 1) {
      return bestIndex;
    }
    
    return -1;
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping if there's unrezzed ice to target
    var installedCorpCards = InstalledCards(corp);
    for (var i = 0; i < installedCorpCards.length; i++) {
      var card = installedCorpCards[i];
      if (CheckCardType(card, ["ice"]) && !card.rezzed) {
        return true;
      }
    }
    //Less valuable but still ok if there's rezzed ice (derez effects exist)
    for (var i = 0; i < installedCorpCards.length; i++) {
      if (CheckCardType(installedCorpCards[i], ["ice"])) {
        return true;
      }
    }
    return false;
  },
  
  //Low priority for economy since it's conditional
  AIEconomyInstall: 1,
};