<?php

/**
 * Editorial content for the municipality landing pages.
 *
 * Deliberately NOT a template with the town name swapped in — that is what
 * Google calls a doorway page. Every entry describes something that is
 * actually different about working in that municipality: the housing stock,
 * the sub-villages, whether homes sit on the gas grid, which language the
 * customers speak. If an entry ever stops saying something specific, the page
 * should be merged into the service-area hub instead.
 *
 * Slug = Str::slug(name) from config('site.service_areas'), identical across
 * locales: place names are not translated.
 */
return [

    // ── Tervuren ────────────────────────────────────────────────────────────
    'tervuren' => [
        'postal_code' => '3080',
        'neighbourhoods' => ['Tervuren-centrum', 'Duisburg', 'Vossem', 'Moorsel'],
        'nl' => [
            'intro' => 'Tervuren combineert een historische dorpskern met veel vrijstaande woningen en villa\'s aan de rand van het Zoniënwoud. In de praktijk komen we hier vaak grotere installaties tegen die in fases zijn verbouwd: een ketel uit een andere periode dan de radiatoren, sanitair dat bij een aanbouw is bijgeplaatst, en ventilatie die achteraf werd toegevoegd.',
            'points' => [
                ['title' => 'Ook in Duisburg, Vossem en Moorsel', 'description' => 'De deelgemeenten liggen buiten de dorpskern, maar horen bij hetzelfde werkgebied. Interventies daar worden in dezelfde ronde ingepland als Tervuren-centrum, zonder aparte verplaatsingsregeling.'],
                ['title' => 'Oudere villa\'s en stookolieketels', 'description' => 'Een deel van het woningbestand verwarmt nog op stookolie. Wij geven neutraal advies over wat vervangen door een condensatieketel of een warmtepomp in uw specifieke situatie realistisch oplevert — inclusief het geval waarin uitstellen de betere keuze is.'],
                ['title' => 'Grote woningen vragen zonering', 'description' => 'Bij ruime woningen volstaat één airco-unit zelden. Welke kamers echt gekoeld moeten worden en of een multi-split zinvol is, bepalen we tijdens de intake in plaats van achteraf op de werf.'],
            ],
            'faqs' => [
                ['question' => 'Werken jullie ook in Duisburg, Vossem en Moorsel?', 'answer' => 'Ja. De deelgemeenten van Tervuren horen volledig bij ons werkgebied. Vermeld bij uw aanvraag gewoon het adres, dan plannen we de interventie samen met de andere afspraken in Tervuren.'],
                ['question' => 'Kan ik in Tervuren ook in het Frans of Engels geholpen worden?', 'answer' => 'Ja. Aanvragen, offertes en communicatie verlopen in het Nederlands, Frans of Engels, naar keuze. U kiest de taal bij het indienen van uw aanvraag.'],
            ],
        ],
        'fr' => [
            'intro' => 'Tervuren associe un noyau villageois historique à de nombreuses maisons quatre façades et villas en bordure de la forêt de Soignes. Dans la pratique, nous y rencontrons souvent des installations plus grandes transformées par étapes : une chaudière d\'une autre époque que les radiateurs, du sanitaire ajouté lors d\'une annexe, et une ventilation installée après coup.',
            'points' => [
                ['title' => 'Également à Duisburg, Vossem et Moorsel', 'description' => 'Les sections communales se situent hors du centre, mais font partie de la même zone d\'intervention. Les interventions y sont planifiées dans la même tournée que Tervuren-centre, sans supplément de déplacement distinct.'],
                ['title' => 'Villas anciennes et chaudières mazout', 'description' => 'Une partie du parc immobilier se chauffe encore au mazout. Nous donnons un avis neutre sur ce qu\'un passage à une chaudière à condensation ou à une pompe à chaleur rapporte réellement dans votre situation — y compris lorsque reporter est le meilleur choix.'],
                ['title' => 'Les grandes maisons demandent un zonage', 'description' => 'Dans une habitation spacieuse, une seule unité de climatisation suffit rarement. Quelles pièces doivent réellement être refroidies et si un multi-split a du sens : nous le déterminons lors de la prise d\'informations, pas sur chantier.'],
            ],
            'faqs' => [
                ['question' => 'Intervenez-vous aussi à Duisburg, Vossem et Moorsel ?', 'answer' => 'Oui. Les sections communales de Tervuren font entièrement partie de notre zone d\'intervention. Indiquez simplement l\'adresse dans votre demande et nous planifions l\'intervention avec les autres rendez-vous à Tervuren.'],
                ['question' => 'Puis-je être servi en français ou en anglais à Tervuren ?', 'answer' => 'Oui. Les demandes, devis et communications se font en néerlandais, français ou anglais, au choix. Vous choisissez la langue au moment d\'introduire votre demande.'],
            ],
        ],
        'en' => [
            'intro' => 'Tervuren combines a historic village core with many detached houses and villas along the edge of the Sonian Forest. In practice we often find larger installations here that were altered in stages: a boiler from a different era than the radiators, plumbing added during an extension, and ventilation fitted afterwards.',
            'points' => [
                ['title' => 'Duisburg, Vossem and Moorsel included', 'description' => 'The outlying villages sit outside the centre but belong to the same service area. Call-outs there are scheduled in the same round as Tervuren centre, with no separate travel arrangement.'],
                ['title' => 'Older villas and oil-fired boilers', 'description' => 'Part of the housing stock still runs on heating oil. We give neutral advice on what switching to a condensing boiler or a heat pump realistically delivers in your situation — including the case where postponing is the better call.'],
                ['title' => 'Large homes need zoning', 'description' => 'In a spacious property a single air conditioning unit is rarely enough. Which rooms genuinely need cooling, and whether a multi-split makes sense, is something we establish during intake rather than on site.'],
            ],
            'faqs' => [
                ['question' => 'Do you also work in Duisburg, Vossem and Moorsel?', 'answer' => 'Yes. The outlying villages of Tervuren are fully part of our service area. Just mention the address in your request and we will schedule the visit together with our other Tervuren appointments.'],
                ['question' => 'Can I be helped in French or English in Tervuren?', 'answer' => 'Yes. Requests, quotes and correspondence run in Dutch, French or English, whichever you prefer. You pick the language when submitting your request.'],
            ],
        ],
    ],

    // ── Overijse ────────────────────────────────────────────────────────────
    'overijse' => [
        'postal_code' => '3090',
        'neighbourhoods' => ['Overijse-centrum', 'Jezus-Eik', 'Maleizen', 'Terlanen', 'Tombeek'],
        'nl' => [
            'intro' => 'Overijse is een uitgestrekte gemeente met een sterk golvend landschap en veel vrijstaande woningen op hellende percelen. Dat heeft technische gevolgen: leidingtracés zijn langer, condensafvoeren van airco en condensatieketels vragen extra aandacht, en woningen met veel glas op het zuiden warmen in de zomer snel op.',
            'points' => [
                ['title' => 'Van Jezus-Eik tot Terlanen', 'description' => 'De deelgemeenten liggen ver uit elkaar. Voor onderhoud plannen we daarom bij voorkeur meerdere adressen in dezelfde streek op dezelfde dag, wat de wachttijd voor iedereen korter maakt.'],
                ['title' => 'Koellast door grote raampartijen', 'description' => 'Veel woningen uit de jaren 70-90 hebben ruime glaspartijen. Wij berekenen het benodigde vermogen op basis van kamer, oriëntatie en beglazing — een te zwaar toestel koelt slechter en verbruikt meer dan een correct gedimensioneerd toestel.'],
                ['title' => 'Hard leidingwater', 'description' => 'Zoals in het grootste deel van Vlaams-Brabant is het leidingwater hier hard. Dat is de reden waarom kalk zich hier sneller vastzet op boilers, kranen en warmtewisselaars, en waarom een correct ingestelde waterverzachter hier meer oplevert dan elders.'],
            ],
            'faqs' => [
                ['question' => 'Komen jullie ook in Jezus-Eik, Maleizen, Terlanen en Tombeek?', 'answer' => 'Ja, alle deelgemeenten van Overijse horen bij het werkgebied. Geef bij uw aanvraag het volledige adres op, zodat de rit correct ingepland kan worden.'],
                ['question' => 'Hoe weet ik welk aircovermogen mijn kamer nodig heeft?', 'answer' => 'Dat hoeft u niet zelf te bepalen. In de aanvraagflow vraagt Mastechnics naar de kamergrootte, oriëntatie en het type beglazing. Op basis daarvan stellen wij het vermogen voor — te zwaar gedimensioneerd koelt namelijk minder comfortabel dan correct gedimensioneerd.'],
            ],
        ],
        'fr' => [
            'intro' => 'Overijse est une commune étendue au relief marqué, avec de nombreuses maisons quatre façades sur des terrains en pente. Cela a des conséquences techniques : les tracés de conduites sont plus longs, les évacuations de condensats des climatisations et chaudières à condensation demandent une attention particulière, et les habitations très vitrées au sud chauffent vite en été.',
            'points' => [
                ['title' => 'De Jésus-Eik à Terlanen', 'description' => 'Les sections communales sont éloignées les unes des autres. Pour l\'entretien, nous planifions donc de préférence plusieurs adresses du même secteur le même jour, ce qui réduit le délai d\'attente pour tout le monde.'],
                ['title' => 'Charge de refroidissement due aux grandes baies vitrées', 'description' => 'Beaucoup d\'habitations des années 70 à 90 possèdent de larges surfaces vitrées. Nous calculons la puissance nécessaire selon la pièce, l\'orientation et le vitrage — un appareil surdimensionné refroidit moins bien et consomme davantage.'],
                ['title' => 'Eau de distribution calcaire', 'description' => 'Comme dans la majeure partie du Brabant flamand, l\'eau de distribution est dure ici. C\'est pourquoi le calcaire se fixe plus vite sur les boilers, robinets et échangeurs, et pourquoi un adoucisseur correctement réglé rapporte davantage ici qu\'ailleurs.'],
            ],
            'faqs' => [
                ['question' => 'Intervenez-vous aussi à Jésus-Eik, Maleizen, Terlanen et Tombeek ?', 'answer' => 'Oui, toutes les sections d\'Overijse font partie de la zone d\'intervention. Indiquez l\'adresse complète dans votre demande afin que le déplacement soit correctement planifié.'],
                ['question' => 'Comment savoir quelle puissance de climatisation il me faut ?', 'answer' => 'Vous n\'avez pas à le déterminer vous-même. Le formulaire de demande de Mastechnics vous interroge sur la taille de la pièce, son orientation et le type de vitrage. Nous proposons la puissance sur cette base — un appareil surdimensionné refroidit en effet moins confortablement qu\'un appareil correctement dimensionné.'],
            ],
        ],
        'en' => [
            'intro' => 'Overijse is a large municipality with strongly rolling terrain and many detached houses on sloping plots. That has technical consequences: pipe runs are longer, condensate drainage from air conditioners and condensing boilers needs extra care, and homes with a lot of south-facing glass heat up quickly in summer.',
            'points' => [
                ['title' => 'From Jezus-Eik to Terlanen', 'description' => 'The outlying villages are far apart. For servicing we therefore prefer to schedule several addresses in the same area on the same day, which shortens the wait for everyone.'],
                ['title' => 'Cooling load from large glazed areas', 'description' => 'Many homes built between the 1970s and 1990s have generous glazing. We size the required capacity from room, orientation and glazing type — an oversized unit actually cools less comfortably and consumes more than a correctly sized one.'],
                ['title' => 'Hard tap water', 'description' => 'As in most of Flemish Brabant, the tap water here is hard. That is why limescale builds up faster on water heaters, taps and heat exchangers, and why a properly calibrated water softener pays off more here than elsewhere.'],
            ],
            'faqs' => [
                ['question' => 'Do you also cover Jezus-Eik, Maleizen, Terlanen and Tombeek?', 'answer' => 'Yes, every part of Overijse is inside the service area. Provide the full address with your request so the journey can be scheduled correctly.'],
                ['question' => 'How do I know what air conditioning capacity my room needs?', 'answer' => 'You do not have to work that out yourself. The Mastechnics request flow asks about room size, orientation and glazing type. We propose the capacity based on that — an oversized unit cools less comfortably than a correctly sized one.'],
            ],
        ],
    ],

    // ── Hoeilaart ───────────────────────────────────────────────────────────
    'hoeilaart' => [
        'postal_code' => '1560',
        'neighbourhoods' => ['Hoeilaart-centrum', 'Groenendaal'],
        'nl' => [
            'intro' => 'Hoeilaart ligt ingesloten door het Zoniënwoud en heeft een compacte kern met veel rijwoningen en halfopen bebouwing uit de serreperiode. Veel van die woningen zijn intussen grondig gerenoveerd, waardoor moderne installaties op oude leidingtracés en oude schouwen zijn aangesloten — precies de combinatie waar problemen ontstaan.',
            'points' => [
                ['title' => 'Renovaties op bestaande schouwen', 'description' => 'Een condensatieketel stelt andere eisen aan de rookgasafvoer dan het toestel dat er eerder stond. Bij vervanging controleren wij eerst of de bestaande schouw geschikt is of gevoerd moet worden, vóór de offerte, niet erna.'],
                ['title' => 'Vocht en ventilatie in gerenoveerde woningen', 'description' => 'Beter isoleren zonder de ventilatie mee te herzien geeft condens op koudebruggen en schimmel in de badkamer. Wij kijken bij een vochtprobleem daarom altijd naar de ventilatie, niet enkel naar het sanitair.'],
                ['title' => 'Compacte kern, beperkte buitenruimte', 'description' => 'Bij rijwoningen is de plaatsing van een buitenunit voor airco of warmtepomp een echte ontwerpvraag: geluid naar de buren, afstand tot de perceelgrens en condensafvoer bepalen mee wat kan.'],
            ],
            'faqs' => [
                ['question' => 'Kan er een airco geplaatst worden bij een rijwoning in Hoeilaart?', 'answer' => 'Meestal wel, maar de plaats van de buitenunit is bepalend. Geluidsproductie richting de buren, de afstand tot de perceelgrens en de condensafvoer bepalen samen welke opstelling haalbaar is. Foto\'s van de gevel en de koer bij uw aanvraag versnellen die inschatting aanzienlijk.'],
                ['question' => 'Moet mijn schouw aangepast worden bij een nieuwe ketel?', 'answer' => 'Dat hangt af van het bestaande kanaal. Een condensatieketel produceert koudere, vochtige rookgassen die een geschikte, meestal gevoerde afvoer vereisen. Wij controleren dat vooraf, zodat de kost van een eventuele voering al in de offerte staat.'],
            ],
        ],
        'fr' => [
            'intro' => 'Hoeilaart est enclavée dans la forêt de Soignes et possède un noyau compact avec de nombreuses maisons mitoyennes et semi-mitoyennes datant de l\'époque des serres. Beaucoup ont depuis été rénovées en profondeur, si bien que des installations modernes sont raccordées à d\'anciens tracés et d\'anciennes cheminées — précisément la combinaison qui pose problème.',
            'points' => [
                ['title' => 'Rénovations sur cheminées existantes', 'description' => 'Une chaudière à condensation impose d\'autres exigences d\'évacuation des fumées que l\'appareil qui la précédait. Lors d\'un remplacement, nous vérifions d\'abord si la cheminée existante convient ou doit être tubée — avant le devis, pas après.'],
                ['title' => 'Humidité et ventilation dans les maisons rénovées', 'description' => 'Mieux isoler sans revoir la ventilation provoque de la condensation sur les ponts thermiques et des moisissures dans la salle de bain. Face à un problème d\'humidité, nous examinons donc toujours la ventilation, pas seulement le sanitaire.'],
                ['title' => 'Noyau compact, espace extérieur limité', 'description' => 'Pour les maisons mitoyennes, l\'emplacement de l\'unité extérieure d\'une climatisation ou d\'une pompe à chaleur est une vraie question de conception : bruit vers les voisins, distance à la limite de propriété et évacuation des condensats déterminent ce qui est réalisable.'],
            ],
            'faqs' => [
                ['question' => 'Peut-on installer une climatisation dans une maison mitoyenne à Hoeilaart ?', 'answer' => 'En général oui, mais l\'emplacement de l\'unité extérieure est déterminant. Le bruit vers les voisins, la distance à la limite de propriété et l\'évacuation des condensats déterminent ensemble la configuration possible. Des photos de la façade et de la cour jointes à votre demande accélèrent nettement cette évaluation.'],
                ['question' => 'Ma cheminée doit-elle être adaptée pour une nouvelle chaudière ?', 'answer' => 'Cela dépend du conduit existant. Une chaudière à condensation produit des fumées plus froides et humides qui exigent une évacuation adaptée, généralement tubée. Nous le vérifions au préalable afin que le coût d\'un éventuel tubage figure déjà dans le devis.'],
            ],
        ],
        'en' => [
            'intro' => 'Hoeilaart is enclosed by the Sonian Forest and has a compact centre with many terraced and semi-detached houses from the greenhouse era. Many have since been thoroughly renovated, which means modern installations sit on old pipe runs and old flues — exactly the combination where problems appear.',
            'points' => [
                ['title' => 'Renovations on existing flues', 'description' => 'A condensing boiler places different demands on flue gas discharge than the appliance it replaces. When replacing, we first check whether the existing flue is suitable or needs lining — before the quote, not after.'],
                ['title' => 'Damp and ventilation in renovated homes', 'description' => 'Improving insulation without revisiting ventilation produces condensation on cold bridges and mould in the bathroom. So when there is a damp problem we always look at ventilation, not only at the plumbing.'],
                ['title' => 'Compact centre, limited outdoor space', 'description' => 'For terraced houses, positioning the outdoor unit of an air conditioner or heat pump is a genuine design question: noise towards neighbours, distance to the boundary and condensate drainage all decide what is possible.'],
            ],
            'faqs' => [
                ['question' => 'Can air conditioning be fitted to a terraced house in Hoeilaart?', 'answer' => 'Usually yes, but the position of the outdoor unit is decisive. Noise towards neighbours, distance to the property boundary and condensate drainage together determine which layout is workable. Photos of the façade and yard with your request speed that assessment up considerably.'],
                ['question' => 'Does my flue need modifying for a new boiler?', 'answer' => 'That depends on the existing duct. A condensing boiler produces cooler, moist flue gases that require a suitable, usually lined, discharge. We check this beforehand so the cost of any lining is already in the quote.'],
            ],
        ],
    ],

    // ── Huldenberg ──────────────────────────────────────────────────────────
    'huldenberg' => [
        'postal_code' => '3040',
        'neighbourhoods' => ['Huldenberg', 'Neerijse', 'Sint-Agatha-Rode', 'Loonbeek', 'Ottenburg'],
        'nl' => [
            'intro' => 'Huldenberg is landelijk en verspreid over vijf dorpskernen in en rond de Dijlevallei. Het woningbestand bevat veel oudere hoeves en vrijstaande woningen, waarvan een deel niet op het aardgasnet is aangesloten. Dat maakt de vraag "wat vervang ik mijn verwarming door" hier fundamenteel anders dan in een stedelijke gemeente.',
            'points' => [
                ['title' => 'Woningen zonder aardgasaansluiting', 'description' => 'Waar geen gasnet ligt, blijft de keuze beperkt tot stookolie, propaan of een warmtepomp. Wij vergelijken die opties op basis van uw isolatiepeil en afgiftesysteem — een warmtepomp op oude, hoge-temperatuurradiatoren levert zelden wat de brochure belooft.'],
                ['title' => 'Oudere hoeves en vochtproblemen', 'description' => 'Kelders en oude bijgebouwen in de Dijlevallei zijn vaak vochtig. Bij sanitair- en ventilatiewerk houden we rekening met die vochtbelasting in plaats van er een standaardoplossing op te plakken.'],
                ['title' => 'Vijf kernen, één planning', 'description' => 'Neerijse, Sint-Agatha-Rode, Loonbeek en Ottenburg liggen op afstand van elkaar. Voor onderhoud clusteren we adressen per streek, zodat verplaatsingstijd niet in uw factuur terechtkomt.'],
            ],
            'faqs' => [
                ['question' => 'Is een warmtepomp zinvol in een oudere woning in Huldenberg?', 'answer' => 'Dat hangt vooral af van het isolatiepeil en van het afgiftesysteem. Een warmtepomp werkt efficiënt bij lage watertemperaturen, dus met vloerverwarming of ruim gedimensioneerde radiatoren. Bij oude radiatoren op hoge temperatuur is eerst isoleren of het afgiftesysteem aanpassen meestal de betere volgorde. Wij zeggen het eerlijk als dat in uw geval zo is.'],
                ['question' => 'Werken jullie in alle deelgemeenten van Huldenberg?', 'answer' => 'Ja: Huldenberg, Neerijse, Sint-Agatha-Rode, Loonbeek en Ottenburg horen allemaal bij het werkgebied.'],
            ],
        ],
        'fr' => [
            'intro' => 'Huldenberg est une commune rurale répartie sur cinq noyaux villageois dans et autour de la vallée de la Dyle. Le parc immobilier compte beaucoup de fermes anciennes et de maisons quatre façades, dont une partie n\'est pas raccordée au réseau de gaz naturel. La question « par quoi remplacer mon chauffage » se pose donc ici tout autrement que dans une commune urbaine.',
            'points' => [
                ['title' => 'Habitations sans raccordement au gaz', 'description' => 'Là où il n\'y a pas de réseau de gaz, le choix se limite au mazout, au propane ou à une pompe à chaleur. Nous comparons ces options selon votre niveau d\'isolation et votre système d\'émission — une pompe à chaleur sur d\'anciens radiateurs haute température tient rarement les promesses de la brochure.'],
                ['title' => 'Fermes anciennes et problèmes d\'humidité', 'description' => 'Les caves et dépendances anciennes de la vallée de la Dyle sont souvent humides. Pour les travaux sanitaires et de ventilation, nous tenons compte de cette charge d\'humidité au lieu d\'y appliquer une solution standard.'],
                ['title' => 'Cinq noyaux, une seule planification', 'description' => 'Neerijse, Sint-Agatha-Rode, Loonbeek et Ottenburg sont distants les uns des autres. Pour l\'entretien, nous regroupons les adresses par secteur afin que le temps de déplacement ne se retrouve pas sur votre facture.'],
            ],
            'faqs' => [
                ['question' => 'Une pompe à chaleur a-t-elle du sens dans une maison ancienne à Huldenberg ?', 'answer' => 'Cela dépend surtout du niveau d\'isolation et du système d\'émission. Une pompe à chaleur est efficace à basse température d\'eau, donc avec un chauffage par le sol ou des radiateurs largement dimensionnés. Avec d\'anciens radiateurs haute température, isoler d\'abord ou adapter l\'émission est généralement le meilleur ordre. Nous vous le disons franchement si c\'est votre cas.'],
                ['question' => 'Intervenez-vous dans toutes les sections de Huldenberg ?', 'answer' => 'Oui : Huldenberg, Neerijse, Sint-Agatha-Rode, Loonbeek et Ottenburg font toutes partie de la zone d\'intervention.'],
            ],
        ],
        'en' => [
            'intro' => 'Huldenberg is rural and spread across five village centres in and around the Dijle valley. The housing stock includes many older farmhouses and detached homes, part of which are not connected to the natural gas grid. That makes the question "what do I replace my heating with" fundamentally different here than in an urban municipality.',
            'points' => [
                ['title' => 'Homes without a gas connection', 'description' => 'Where there is no gas grid, the choice narrows to heating oil, propane or a heat pump. We compare those options against your insulation level and emission system — a heat pump on old high-temperature radiators rarely delivers what the brochure promises.'],
                ['title' => 'Older farmhouses and damp', 'description' => 'Cellars and old outbuildings in the Dijle valley are often damp. For plumbing and ventilation work we account for that moisture load instead of applying a standard solution to it.'],
                ['title' => 'Five centres, one schedule', 'description' => 'Neerijse, Sint-Agatha-Rode, Loonbeek and Ottenburg sit some distance apart. For servicing we cluster addresses by area so travel time does not end up on your invoice.'],
            ],
            'faqs' => [
                ['question' => 'Does a heat pump make sense in an older home in Huldenberg?', 'answer' => 'That depends mainly on insulation level and emission system. A heat pump works efficiently at low water temperatures, so with underfloor heating or generously sized radiators. With old high-temperature radiators, insulating first or changing the emission system is usually the better order. We will say so plainly if that applies to you.'],
                ['question' => 'Do you work in all parts of Huldenberg?', 'answer' => 'Yes: Huldenberg, Neerijse, Sint-Agatha-Rode, Loonbeek and Ottenburg all fall inside the service area.'],
            ],
        ],
    ],

    // ── Bertem ──────────────────────────────────────────────────────────────
    'bertem' => [
        'postal_code' => '3060',
        'neighbourhoods' => ['Bertem', 'Leefdaal', 'Korbeek-Dijle'],
        'nl' => [
            'intro' => 'Bertem ligt tussen Leuven en de Brusselse rand en heeft een uitgesproken gemengd woningbestand: oude dorpskernen in Bertem, Leefdaal en Korbeek-Dijle naast recentere verkavelingen. Die twee groepen hebben zelden hetzelfde probleem — bij nieuwbouw draait het om ventilatie en regeling, bij de oude kernen om vervanging en herstelling.',
            'points' => [
                ['title' => 'Ventilatie type C en D in recente woningen', 'description' => 'Nieuwere woningen zijn luchtdicht gebouwd en hebben een verplicht ventilatiesysteem. Vuile filters of ontregelde debieten geven daar klachten over droge lucht, geluid of vocht. Wij meten en herstellen debieten in plaats van enkel filters te vervangen.'],
                ['title' => 'Oude dorpskernen, gefaseerde installaties', 'description' => 'In de historische kernen zijn verwarming en sanitair vaak in verschillende decennia aangelegd. Voor een herstelling is het dan belangrijk eerst te weten wat er precies ligt — daarom vragen we foto\'s van het toestel en het typeplaatje bij de aanvraag.'],
                ['title' => 'Vlot bereikbaar vanuit beide richtingen', 'description' => 'Bertem ligt centraal in ons werkgebied, tussen Leuven en de Druivenstreek. Dat maakt het inplannen van zowel onderhoud als dringende interventies hier praktisch eenvoudig.'],
            ],
            'faqs' => [
                ['question' => 'Hoe vaak moet een ventilatiesysteem type D onderhouden worden?', 'answer' => 'Filters vragen doorgaans enkele keren per jaar aandacht, afhankelijk van de omgeving en het gebruik. De unit zelf en de kanalen hebben periodiek een grondiger reiniging en een controle van de debieten nodig. Als de luchtdebieten niet meer kloppen, merkt u dat aan geluid, droge lucht of vocht in de badkamer.'],
                ['question' => 'Werken jullie ook in Leefdaal en Korbeek-Dijle?', 'answer' => 'Ja, beide deelgemeenten horen bij het werkgebied van Bertem.'],
            ],
        ],
        'fr' => [
            'intro' => 'Bertem se situe entre Louvain et la périphérie bruxelloise et présente un parc immobilier nettement mixte : anciens noyaux villageois à Bertem, Leefdaal et Korbeek-Dijle à côté de lotissements plus récents. Ces deux groupes ont rarement le même problème — dans le neuf il s\'agit de ventilation et de régulation, dans les anciens noyaux de remplacement et de réparation.',
            'points' => [
                ['title' => 'Ventilation type C et D dans les habitations récentes', 'description' => 'Les habitations récentes sont construites de manière étanche à l\'air et disposent d\'un système de ventilation obligatoire. Des filtres encrassés ou des débits déréglés y provoquent des plaintes d\'air sec, de bruit ou d\'humidité. Nous mesurons et rétablissons les débits au lieu de simplement remplacer les filtres.'],
                ['title' => 'Anciens noyaux, installations par phases', 'description' => 'Dans les noyaux historiques, chauffage et sanitaire ont souvent été posés à des décennies d\'écart. Pour une réparation, il faut donc d\'abord savoir ce qui est réellement en place — c\'est pourquoi nous demandons des photos de l\'appareil et de la plaque signalétique lors de la demande.'],
                ['title' => 'Facilement accessible des deux côtés', 'description' => 'Bertem occupe une position centrale dans notre zone d\'intervention, entre Louvain et le Druivenstreek. Cela simplifie la planification tant des entretiens que des interventions urgentes.'],
            ],
            'faqs' => [
                ['question' => 'À quelle fréquence entretenir un système de ventilation type D ?', 'answer' => 'Les filtres demandent généralement une attention plusieurs fois par an, selon l\'environnement et l\'usage. L\'unité elle-même et les gaines nécessitent périodiquement un nettoyage plus approfondi et un contrôle des débits. Si les débits ne sont plus corrects, cela se remarque au bruit, à l\'air sec ou à l\'humidité dans la salle de bain.'],
                ['question' => 'Intervenez-vous aussi à Leefdaal et Korbeek-Dijle ?', 'answer' => 'Oui, ces deux sections font partie de la zone d\'intervention de Bertem.'],
            ],
        ],
        'en' => [
            'intro' => 'Bertem sits between Leuven and the Brussels periphery and has a distinctly mixed housing stock: old village cores in Bertem, Leefdaal and Korbeek-Dijle alongside more recent housing estates. The two groups rarely have the same problem — in newer homes it is about ventilation and controls, in the old cores about replacement and repair.',
            'points' => [
                ['title' => 'Type C and D ventilation in recent homes', 'description' => 'Newer homes are built airtight and have a mandatory ventilation system. Dirty filters or drifted airflow rates cause complaints there about dry air, noise or damp. We measure and restore airflow rates instead of only swapping filters.'],
                ['title' => 'Old village cores, phased installations', 'description' => 'In the historic cores, heating and plumbing were often installed decades apart. For a repair it matters to know first what is actually there — which is why we ask for photos of the appliance and its nameplate with your request.'],
                ['title' => 'Easily reached from both directions', 'description' => 'Bertem lies centrally in our service area, between Leuven and the Druivenstreek. That makes scheduling both maintenance and urgent call-outs here practically straightforward.'],
            ],
            'faqs' => [
                ['question' => 'How often does a type D ventilation system need servicing?', 'answer' => 'Filters typically need attention several times a year, depending on surroundings and use. The unit itself and the ducts periodically need a deeper clean and an airflow check. When airflow rates drift you notice it as noise, dry air or damp in the bathroom.'],
                ['question' => 'Do you also work in Leefdaal and Korbeek-Dijle?', 'answer' => 'Yes, both villages fall inside the Bertem service area.'],
            ],
        ],
    ],

    // ── Wezembeek-Oppem ─────────────────────────────────────────────────────
    'wezembeek-oppem' => [
        'postal_code' => '1970',
        'neighbourhoods' => ['Wezembeek', 'Oppem'],
        'nl' => [
            'intro' => 'Wezembeek-Oppem ligt tegen Brussel aan en heeft een sterk tweetalig karakter en een mix van appartementsgebouwen en vrijstaande woningen. Voor technische opdrachten betekent dat twee dingen: de communicatie moet in het Nederlands of het Frans kunnen verlopen, en bij appartementen ligt de beslissing vaak niet alleen bij de bewoner.',
            'points' => [
                ['title' => 'Nederlands, Frans of Engels', 'description' => 'De volledige flow — aanvraag, offerte, bevestiging en communicatie — kan in het Nederlands, Frans of Engels verlopen. U kiest de taal, wij volgen ze consequent aan.'],
                ['title' => 'Appartementen en gemeenschappelijke delen', 'description' => 'Een buitenunit aan de gevel of een doorvoer door een gemeenschappelijke muur raakt aan het reglement van mede-eigendom. Wij leveren de technische informatie die u nodig hebt om dat bij de syndicus voorgelegd te krijgen, voordat er materiaal besteld wordt.'],
                ['title' => 'Kort bij Brussel, zonder Brusselse wachttijd', 'description' => 'De gemeente ligt binnen ons kerngebied rond de Druivenstreek, waardoor onderhoud en interventies hier in de normale planning passen.'],
            ],
            'faqs' => [
                ['question' => 'Mag ik zomaar een airco plaatsen in een appartement?', 'answer' => 'Meestal niet zonder toestemming. Een buitenunit aan de gevel of een doorboring naar een gemeenschappelijk deel valt onder het reglement van mede-eigendom en vraagt doorgaans goedkeuring van de algemene vergadering of de syndicus. Wij bezorgen u vooraf de technische gegevens — plaats, afmetingen, geluidsniveau en condensafvoer — zodat u een volledig dossier kunt voorleggen.'],
                ['question' => 'Kan alles in het Frans afgehandeld worden?', 'answer' => 'Ja. Aanvraag, offerte, bevestigingsmails en het contact zelf kunnen volledig in het Frans verlopen, net zoals in het Nederlands of het Engels.'],
            ],
        ],
        'fr' => [
            'intro' => 'Wezembeek-Oppem jouxte Bruxelles, présente un caractère fortement bilingue et un mélange d\'immeubles à appartements et de maisons quatre façades. Pour les missions techniques, cela implique deux choses : la communication doit pouvoir se faire en français ou en néerlandais, et dans les immeubles la décision n\'appartient souvent pas au seul occupant.',
            'points' => [
                ['title' => 'Français, néerlandais ou anglais', 'description' => 'L\'ensemble du parcours — demande, devis, confirmation et communication — peut se dérouler en français, en néerlandais ou en anglais. Vous choisissez la langue, nous la conservons de bout en bout.'],
                ['title' => 'Appartements et parties communes', 'description' => 'Une unité extérieure en façade ou un percement dans un mur commun relève du règlement de copropriété. Nous fournissons les informations techniques nécessaires pour soumettre le dossier au syndic, avant toute commande de matériel.'],
                ['title' => 'Proche de Bruxelles, sans les délais bruxellois', 'description' => 'La commune se situe dans notre zone centrale autour du Druivenstreek, ce qui permet d\'intégrer entretiens et interventions dans la planification normale.'],
            ],
            'faqs' => [
                ['question' => 'Puis-je installer une climatisation dans un appartement sans autorisation ?', 'answer' => 'Généralement non. Une unité extérieure en façade ou un percement vers une partie commune relève du règlement de copropriété et requiert habituellement l\'accord de l\'assemblée générale ou du syndic. Nous vous transmettons au préalable les données techniques — emplacement, dimensions, niveau sonore et évacuation des condensats — afin que vous puissiez présenter un dossier complet.'],
                ['question' => 'Tout peut-il être traité en français ?', 'answer' => 'Oui. La demande, le devis, les e-mails de confirmation et le contact lui-même peuvent se dérouler entièrement en français, tout comme en néerlandais ou en anglais.'],
            ],
        ],
        'en' => [
            'intro' => 'Wezembeek-Oppem borders Brussels, has a strongly bilingual character and mixes apartment buildings with detached houses. For technical work that means two things: communication has to be possible in Dutch or French, and in apartment buildings the decision often does not rest with the occupant alone.',
            'points' => [
                ['title' => 'Dutch, French or English', 'description' => 'The whole journey — request, quote, confirmation and correspondence — can run in Dutch, French or English. You pick the language and we keep to it throughout.'],
                ['title' => 'Apartments and common parts', 'description' => 'An outdoor unit on the façade or a penetration through a shared wall falls under the co-ownership rules. We supply the technical information you need to put that to the building manager, before any equipment is ordered.'],
                ['title' => 'Close to Brussels, without Brussels waiting times', 'description' => 'The municipality sits inside our core area around the Druivenstreek, so servicing and call-outs here fit into normal scheduling.'],
            ],
            'faqs' => [
                ['question' => 'Can I simply install air conditioning in an apartment?', 'answer' => 'Usually not without permission. An outdoor unit on the façade or a penetration into a common part falls under the co-ownership rules and normally needs approval from the general assembly or the building manager. We provide the technical details upfront — position, dimensions, sound level and condensate drainage — so you can submit a complete case.'],
                ['question' => 'Can everything be handled in French?', 'answer' => 'Yes. The request, the quote, confirmation emails and the contact itself can run entirely in French, just as in Dutch or English.'],
            ],
        ],
    ],
];
