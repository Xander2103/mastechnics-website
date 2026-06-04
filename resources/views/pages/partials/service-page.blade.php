@php
    $siteName = config('site.name');

    // Detect service key from config by matching the current translation slug
    $currentServiceKey = null;
    foreach (config('services', []) as $key => $service) {
        $trans = $service['translations'][$locale] ?? $service['translations']['nl'] ?? [];
        if (($trans['slug'] ?? null) === $translation->slug) {
            $currentServiceKey = $key;
            break;
        }
    }

    $serviceIcons = [
        'heating' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
        'airco' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></svg>',
        'plumbing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>',
        'ventilation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2v6h-6"/><path d="M21 13a9 9 0 1 1-3-7.7L21 8"/></svg>',
        'water-softeners' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z"/><path d="M12.56 6.6A10.97 10.97 0 0 0 14 3.02c.5 2.5 2 4.9 4 6.5s3 3.5 3 5.5a6.98 6.98 0 0 1-11.91 4.97"/></svg>',
        'cold-rooms' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="2" x2="12" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><path d="m20 16-4-4 4-4"/><path d="m4 8 4 4-4 4"/><path d="m16 4-4 4-4-4"/><path d="m8 20 4-4 4 4"/></svg>',
    ];

    // ── Shared labels ──────────────────────────────────────────────────────────
    $labels = [
        'nl' => [
            'type'       => 'Service',
            'quote'      => 'Vraag een offerte of interventie aan',
            'what'       => 'Wat kunnen we voor u doen?',
            'situations' => 'Typische situaties',
            'why'        => 'Waarom ' . $siteName . ' voor dit?',
            'cta_badge'  => 'Direct starten',
            'cta_title'  => 'Klaar om een aanvraag in te dienen?',
            'cta_text'   => 'Beschrijf uw probleem of project via de slimme aanvraagflow en wij nemen zo snel mogelijk contact op.',
            'cta_button' => 'Start aanvraag',
        ],
        'fr' => [
            'type'       => 'Service',
            'quote'      => 'Demander un devis ou une intervention',
            'what'       => 'Que pouvons-nous faire pour vous ?',
            'situations' => 'Situations typiques',
            'why'        => 'Pourquoi ' . $siteName . ' pour cela ?',
            'cta_badge'  => 'Commencer maintenant',
            'cta_title'  => 'Prêt à soumettre une demande ?',
            'cta_text'   => 'Décrivez votre problème ou projet via le flux de demande intelligent et nous vous contacterons dès que possible.',
            'cta_button' => 'Démarrer ma demande',
        ],
        'en' => [
            'type'       => 'Service',
            'quote'      => 'Request a quote or call-out',
            'what'       => 'How can we help?',
            'situations' => 'Typical situations',
            'why'        => 'Why ' . $siteName . ' for this?',
            'cta_badge'  => 'Get started',
            'cta_title'  => 'Ready to submit a request?',
            'cta_text'   => 'Describe your issue or project via the smart request flow and we will get back to you as soon as possible.',
            'cta_button' => 'Start request',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];
    $requestSlug = $locale === 'fr' ? 'demande' : ($locale === 'en' ? 'request' : 'aanvraag');

    // ── Service-specific content ───────────────────────────────────────────────
    $serviceContent = [
        'heating' => [
            'nl' => [
                'situations' => [
                    'Jaarlijks onderhoud van gasketel of condensatieketel',
                    'Herstelling bij storing, geen warm water of verwarming die uitvalt',
                    'Plaatsing of vervanging van verwarmingsinstallatie',
                    'Advies over overstap naar warmtepomp of zuiniger systeem',
                    'Controle en afstelling van ketel na aankoop woning',
                ],
                'highlights' => [
                    ['title' => 'Erkende gastechnicus', 'description' => 'Alle werken worden uitgevoerd door een erkende technicus conform de geldende veiligheidsregelgeving.'],
                    ['title' => 'Alle systemen', 'description' => 'Gas, stookolie, warmtepomp: wij werken met alle gangbare verwarmingstypes voor woning en bedrijf.'],
                    ['title' => 'Snelle interventie bij panne', 'description' => 'Bij dringende storingen reageren we snel met een diagnose en concrete oplossing.'],
                ],
            ],
            'fr' => [
                'situations' => [
                    'Entretien annuel de chaudière gaz ou à condensation',
                    'Réparation en cas de panne, pas d\'eau chaude ou chauffage en panne',
                    'Installation ou remplacement d\'une installation de chauffage',
                    'Conseil pour passer à une pompe à chaleur ou un système plus économique',
                    'Contrôle et réglage de chaudière après achat immobilier',
                ],
                'highlights' => [
                    ['title' => 'Technicien gaz certifié', 'description' => 'Tous les travaux sont réalisés par un technicien certifié conformément aux règles de sécurité en vigueur.'],
                    ['title' => 'Tous les systèmes', 'description' => 'Gaz, mazout, pompe à chaleur : nous travaillons avec tous les types de chauffage courants pour habitations et entreprises.'],
                    ['title' => 'Intervention rapide en cas de panne', 'description' => 'En cas de panne urgente, nous réagissons rapidement avec un diagnostic et une solution concrète.'],
                ],
            ],
            'en' => [
                'situations' => [
                    'Annual maintenance of gas boiler or condensing boiler',
                    'Repair for breakdowns, no hot water or failed heating',
                    'Installation or replacement of a heating system',
                    'Advice on switching to a heat pump or more efficient system',
                    'Boiler check and calibration after purchasing a property',
                ],
                'highlights' => [
                    ['title' => 'Certified gas technician', 'description' => 'All work is carried out by a certified technician in compliance with current safety regulations.'],
                    ['title' => 'All system types', 'description' => 'Gas, oil, heat pump: we work with all common heating types for homes and businesses.'],
                    ['title' => 'Fast breakdown response', 'description' => 'For urgent breakdowns we respond quickly with a diagnosis and concrete solution.'],
                ],
            ],
        ],

        'airco' => [
            'nl' => [
                'situations' => [
                    'Plaatsing en installatie van split-unit airco voor slaapkamer, woonkamer of bureau',
                    'Offerte voor multi-split systeem (meerdere kamers of zones)',
                    'Jaarlijks onderhoud, reiniging en regasering',
                    'Herstelling bij slecht koelen, waterlek of foutcode op display',
                    'Advies bij vervanging of uitbreiding van bestaand systeem',
                ],
                'highlights' => [
                    ['title' => 'Erkend F-gas installateur', 'description' => 'Werken aan koelmiddelen vereisen een F-gas certificaat. Onze technici zijn volledig erkend voor alle gangbare koelmiddelen.'],
                    ['title' => 'Alle merken en systemen', 'description' => 'Van Daikin en Mitsubishi tot Samsung en LG: wij installeren en onderhouden airco van alle merken.'],
                    ['title' => 'Duidelijke offerte na intake', 'description' => 'Via de slimme aanvraagflow verzamelen we de juiste kamergegevens voor een correcte en kloppende offerte.'],
                ],
            ],
            'fr' => [
                'situations' => [
                    'Installation d\'une unité split pour chambre, salon ou bureau',
                    'Devis pour un système multi-split (plusieurs pièces ou zones)',
                    'Entretien annuel, nettoyage et recharge en réfrigérant',
                    'Réparation en cas de mauvais refroidissement, fuite d\'eau ou code d\'erreur',
                    'Conseil pour le remplacement ou l\'extension d\'un système existant',
                ],
                'highlights' => [
                    ['title' => 'Installateur F-gaz certifié', 'description' => 'Les travaux sur les fluides frigorigènes nécessitent une certification F-gaz. Nos techniciens sont entièrement certifiés.'],
                    ['title' => 'Toutes les marques et systèmes', 'description' => 'De Daikin et Mitsubishi à Samsung et LG : nous installons et entretenons la climatisation de toutes les marques.'],
                    ['title' => 'Devis clair après prise en charge', 'description' => 'Via le flux de demande intelligent, nous collectons les données correctes pour un devis précis et complet.'],
                ],
            ],
            'en' => [
                'situations' => [
                    'Installation of a split-unit air conditioner for bedroom, living room or office',
                    'Quote for a multi-split system (multiple rooms or zones)',
                    'Annual maintenance, cleaning and refrigerant top-up',
                    'Repair for poor cooling, water leak or error code on display',
                    'Advice on replacing or extending an existing system',
                ],
                'highlights' => [
                    ['title' => 'Certified F-gas installer', 'description' => 'Work on refrigerants requires F-gas certification. Our technicians are fully certified for all common refrigerants.'],
                    ['title' => 'All brands and systems', 'description' => 'From Daikin and Mitsubishi to Samsung and LG: we install and maintain air conditioning from all brands.'],
                    ['title' => 'Clear quote after intake', 'description' => 'Via the smart request flow we collect the right room data for an accurate and complete quote.'],
                ],
            ],
        ],

        'plumbing' => [
            'nl' => [
                'situations' => [
                    'Waterlek of wateroverlast in woning of bedrijf',
                    'Verstopte afvoer, gootsteen, toilet of riolering',
                    'Plaatsing of vervanging van toilet, wastafel, douche of bad',
                    'Herstelling of vervanging van kranen en mengkranen',
                    'Aansluiting van toestellen (wasmachine, vaatwasser, boiler)',
                ],
                'highlights' => [
                    ['title' => 'Snelle interventie bij waterlekkage', 'description' => 'Bij waterschade reageren wij snel om verdere schade te beperken en de oorzaak op te lossen.'],
                    ['title' => 'Particulier én professioneel', 'description' => 'Van éénmalige herstelling in de woning tot grotere sanitaire projecten in bedrijfspanden.'],
                    ['title' => 'Van A tot Z', 'description' => 'Wij begeleiden volledige badkamerprojecten: van ontwerp en afbraak tot het plaatsen van het nieuwe sanitair.'],
                ],
            ],
            'fr' => [
                'situations' => [
                    'Fuite d\'eau ou dégâts des eaux dans une habitation ou une entreprise',
                    'Évacuation bouchée, évier, toilette ou égout obstrué',
                    'Installation ou remplacement de toilette, lavabo, douche ou baignoire',
                    'Réparation ou remplacement de robinets et mitigeurs',
                    'Raccordement d\'appareils (lave-linge, lave-vaisselle, chauffe-eau)',
                ],
                'highlights' => [
                    ['title' => 'Intervention rapide en cas de fuite', 'description' => 'En cas de dégâts des eaux, nous intervenons rapidement pour limiter les dégâts et résoudre la cause.'],
                    ['title' => 'Particuliers et professionnels', 'description' => 'D\'une réparation ponctuelle dans une habitation à des projets sanitaires importants dans des locaux professionnels.'],
                    ['title' => 'De A à Z', 'description' => 'Nous accompagnons les projets de salle de bain complets : de la conception et démolition à la pose du nouveau sanitaire.'],
                ],
            ],
            'en' => [
                'situations' => [
                    'Water leak or water damage in a home or business',
                    'Blocked drain, sink, toilet or sewer',
                    'Installation or replacement of toilet, washbasin, shower or bath',
                    'Repair or replacement of taps and mixers',
                    'Connection of appliances (washing machine, dishwasher, water heater)',
                ],
                'highlights' => [
                    ['title' => 'Fast response to water leaks', 'description' => 'For water damage we respond quickly to limit further damage and resolve the root cause.'],
                    ['title' => 'Residential and commercial', 'description' => 'From a one-off repair in a home to larger sanitary projects in commercial premises.'],
                    ['title' => 'Full project management', 'description' => 'We handle complete bathroom projects from start to finish: design, demolition and installation of new fixtures.'],
                ],
            ],
        ],

        'ventilation' => [
            'nl' => [
                'situations' => [
                    'Plaatsing van nieuw ventilatiesysteem type C of type D',
                    'Onderhoud en reiniging van bestaande ventilatiekanalen en units',
                    'Problemen met slechte luchtkwaliteit, vocht of condensatie',
                    'Vervanging van verouderde ventilatieunit',
                    'Technische audit of opmaak EPB-attest voor ventilatiesysteem',
                ],
                'highlights' => [
                    ['title' => 'EPB-conforme installatie', 'description' => 'Onze ventilatieoplossingen voldoen aan de EPB-eisen voor energieprestatie. Wij leveren de nodige documentatie op.'],
                    ['title' => 'Woning en bedrijf', 'description' => 'Zowel voor residentiële woningen en appartementen als voor kantoor- en commerciële ruimtes.'],
                    ['title' => 'Energiezuinig advies', 'description' => 'Wij adviseren over de meest energiezuinige en kostenefficiënte ventilatieoplossing voor uw situatie.'],
                ],
            ],
            'fr' => [
                'situations' => [
                    'Installation d\'un nouveau système de ventilation type C ou type D',
                    'Entretien et nettoyage de gaines et d\'unités de ventilation existantes',
                    'Problèmes de mauvaise qualité d\'air, d\'humidité ou de condensation',
                    'Remplacement d\'une unité de ventilation vétuste',
                    'Audit technique ou établissement d\'une attestation EPB pour système de ventilation',
                ],
                'highlights' => [
                    ['title' => 'Installation conforme EPB', 'description' => 'Nos solutions de ventilation répondent aux exigences PEB en matière de performance énergétique. Nous fournissons la documentation nécessaire.'],
                    ['title' => 'Habitations et entreprises', 'description' => 'Pour les maisons et appartements résidentiels comme pour les bureaux et locaux commerciaux.'],
                    ['title' => 'Conseils énergétiques', 'description' => 'Nous vous conseillons sur la solution de ventilation la plus économe en énergie et rentable pour votre situation.'],
                ],
            ],
            'en' => [
                'situations' => [
                    'Installation of a new ventilation system type C or type D',
                    'Maintenance and cleaning of existing ventilation ducts and units',
                    'Problems with poor air quality, moisture or condensation',
                    'Replacement of an outdated ventilation unit',
                    'Technical audit or EPB certificate for ventilation system',
                ],
                'highlights' => [
                    ['title' => 'EPB-compliant installation', 'description' => 'Our ventilation solutions meet EPB energy performance requirements. We provide all necessary documentation.'],
                    ['title' => 'Homes and businesses', 'description' => 'For both residential homes and apartments and commercial or office premises.'],
                    ['title' => 'Energy-efficient advice', 'description' => 'We advise on the most energy-efficient and cost-effective ventilation solution for your situation.'],
                ],
            ],
        ],

        'water-softeners' => [
            'nl' => [
                'situations' => [
                    'Advies en plaatsing van nieuwe waterverzachter',
                    'Onderhoud, reiniging en zoutbijvulling van bestaand toestel',
                    'Kalkproblemen op kranen, toestellen of verwarmingselementen',
                    'Vervanging van defecte regeneratieklep of elektronica',
                    'Controle van waterhardheid en correcte instelling van het toestel',
                ],
                'highlights' => [
                    ['title' => 'Kwalitatieve toestellen', 'description' => 'Wij werken met waterverzachters van erkende merken met bewezen betrouwbaarheid en lange levensduur.'],
                    ['title' => 'Volledige installatie en inregeling', 'description' => 'Van aansluiting op de waterleiding tot de correcte programmainstelling voor uw waterverbruik en hardheid.'],
                    ['title' => 'Periodiek onderhoud', 'description' => 'Een waterverzachter heeft regelmatig onderhoud nodig. Wij kunnen een onderhoudsafspraak of contract inplannen.'],
                ],
            ],
            'fr' => [
                'situations' => [
                    'Conseil et installation d\'un nouvel adoucisseur d\'eau',
                    'Entretien, nettoyage et rechargement en sel d\'un appareil existant',
                    'Problèmes de calcaire sur robinets, appareils ou éléments chauffants',
                    'Remplacement d\'une vanne de régénération défectueuse ou d\'électronique',
                    'Contrôle de la dureté de l\'eau et réglage correct de l\'appareil',
                ],
                'highlights' => [
                    ['title' => 'Appareils de qualité', 'description' => 'Nous travaillons avec des adoucisseurs de marques reconnues offrant une fiabilité et une durée de vie prouvées.'],
                    ['title' => 'Installation et réglage complets', 'description' => 'Du raccordement à la canalisation d\'eau au réglage correct du programme selon votre consommation et la dureté de l\'eau.'],
                    ['title' => 'Entretien périodique', 'description' => 'Un adoucisseur nécessite un entretien régulier. Nous pouvons planifier un rendez-vous ou un contrat d\'entretien.'],
                ],
            ],
            'en' => [
                'situations' => [
                    'Advice and installation of a new water softener',
                    'Maintenance, cleaning and salt refill of an existing unit',
                    'Limescale problems on taps, appliances or heating elements',
                    'Replacement of a faulty regeneration valve or electronics',
                    'Water hardness check and correct calibration of the unit',
                ],
                'highlights' => [
                    ['title' => 'Quality appliances', 'description' => 'We work with water softeners from recognised brands with proven reliability and longevity.'],
                    ['title' => 'Full installation and calibration', 'description' => 'From water connection to correct programme setting for your water consumption and hardness level.'],
                    ['title' => 'Periodic servicing', 'description' => 'A water softener needs regular maintenance. We can schedule a service appointment or maintenance contract.'],
                ],
            ],
        ],

        'cold-rooms' => [
            'nl' => [
                'situations' => [
                    'Plaatsing en installatie van nieuwe koelcel of koudekamer',
                    'Onderhoud van koelaggregaat, verdamper en koelcircuit',
                    'Herstelling bij temperatuurproblemen of compressordefect',
                    'Advies bij nieuwe koelruimte, uitbreiding of modernisering',
                    'Energetische audit en optimalisatie van bestaande koelinstallatie',
                ],
                'highlights' => [
                    ['title' => 'Erkend F-gas installateur', 'description' => 'Alle werken aan koelinstallaties met koelmiddelen worden uitgevoerd door erkende F-gas technici conform de geldende regelgeving.'],
                    ['title' => 'Horeca, voeding en industrie', 'description' => 'Van kleine koelcel in een restaurant tot grote industriële koelruimte: wij werken met commerciële klanten in alle sectoren.'],
                    ['title' => 'Snel ter plaatse bij problemen', 'description' => 'Temperatuurproblemen in een koelcel kunnen snel grote gevolgen hebben. Wij streven naar een snelle interventie voor professionele klanten.'],
                ],
            ],
            'fr' => [
                'situations' => [
                    'Installation d\'une nouvelle chambre froide ou d\'un local froid',
                    'Entretien d\'un groupe frigorifique, d\'un évaporateur et du circuit frigorifique',
                    'Réparation en cas de problèmes de température ou de panne de compresseur',
                    'Conseil pour une nouvelle chambre froide, extension ou modernisation',
                    'Audit énergétique et optimisation d\'une installation frigorifique existante',
                ],
                'highlights' => [
                    ['title' => 'Installateur F-gaz certifié', 'description' => 'Tous les travaux sur des installations avec fluides frigorigènes sont réalisés par des techniciens F-gaz certifiés.'],
                    ['title' => 'Horeca, alimentation et industrie', 'description' => 'De la petite chambre froide dans un restaurant aux grandes chambres industrielles : nous travaillons avec des clients commerciaux de tous secteurs.'],
                    ['title' => 'Intervention rapide en cas de problème', 'description' => 'Les problèmes de température dans une chambre froide peuvent avoir de lourdes conséquences. Nous visons une intervention rapide.'],
                ],
            ],
            'en' => [
                'situations' => [
                    'Installation of a new cold room or cold storage facility',
                    'Maintenance of refrigeration unit, evaporator and cooling circuit',
                    'Repair for temperature problems or compressor failure',
                    'Advice on new cold room, extension or modernisation',
                    'Energy audit and optimisation of an existing refrigeration installation',
                ],
                'highlights' => [
                    ['title' => 'Certified F-gas installer', 'description' => 'All work on refrigeration installations with refrigerants is carried out by certified F-gas technicians.'],
                    ['title' => 'Catering, food and industry', 'description' => 'From small cold rooms in a restaurant to large industrial refrigeration: we work with commercial clients across all sectors.'],
                    ['title' => 'Fast response to problems', 'description' => 'Temperature problems in a cold room can quickly cause serious damage. We aim for fast response times for commercial clients.'],
                ],
            ],
        ],
    ];

    $currentContent = $serviceContent[$currentServiceKey][$locale]
        ?? ($currentServiceKey ? ($serviceContent[$currentServiceKey]['nl'] ?? null) : null);
@endphp

<div class="service-page service-page--{{ $currentServiceKey ?? '' }}">

{{-- ═══════════════════════════════════════════════════════════
     Hero
═══════════════════════════════════════════════════════════ --}}
<section class="service-hero">
    <div class="container">
        <div class="service-hero-inner">

            <div class="service-hero-text">
                <span class="eyebrow">{{ $text['type'] }}</span>
                <h1>{{ $translation->title }}</h1>
                @if ($translation->intro)
                    <p class="service-intro">{{ $translation->intro }}</p>
                @endif
                <div class="button-row">
                    <a class="button button-primary"
                       href="{{ route('pages.show', ['locale' => $locale, 'slug' => $requestSlug]) }}">
                        {{ $text['quote'] }}
                    </a>
                </div>
            </div>

            @if ($currentServiceKey === 'heating')
                {{-- Glass badge icon — background image (verwarming-hero.webp) comes from CSS --}}
                <div class="service-hero-badge-icon" aria-hidden="true">
                    {!! $serviceIcons['heating'] !!}
                </div>
            @elseif ($currentServiceKey && isset($serviceIcons[$currentServiceKey]))
                <div class="service-hero-icon" aria-hidden="true">
                    {!! $serviceIcons[$currentServiceKey] !!}
                </div>
            @endif

        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     Compact overview: "What we do" + "Typical situations"
═══════════════════════════════════════════════════════════ --}}
<section class="service-overview-section">
    <div class="container">
        <div class="service-overview-grid{{ !$currentContent ? ' service-overview-grid--single' : '' }}">

            <div class="service-overview-card">
                <h2>{{ $text['what'] }}</h2>
                @if ($translation->content)
                    <p>{{ $translation->content }}</p>
                @endif

            </div>

            @if ($currentContent && !empty($currentContent['situations']))
                <div class="service-overview-card">
                    <h2>{{ $text['situations'] }}</h2>
                    <ul class="use-cases-list">
                        @foreach ($currentContent['situations'] as $situation)
                            <li>{{ $situation }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     Why Mastechnics — compact 3-card row
═══════════════════════════════════════════════════════════ --}}
@if ($currentContent && !empty($currentContent['highlights']))
    <section class="service-why-section">
        <div class="container">
            <h2 class="service-why-heading">{{ $text['why'] }}</h2>
            <div class="service-highlights-grid">
                @foreach ($currentContent['highlights'] as $item)
                    <article class="service-card">
                        <h3>{{ $item['title'] }}</h3>
                        <p>{{ $item['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- ═══════════════════════════════════════════════════════════
     CTA
═══════════════════════════════════════════════════════════ --}}
<section class="service-cta-section">
    <div class="container">
        <div class="home-cta">
            <div>
                <span class="eyebrow eyebrow-dark">{{ $text['cta_badge'] }}</span>
                <h2>{{ $text['cta_title'] }}</h2>
                <p>{{ $text['cta_text'] }}</p>
            </div>
            <a class="button button-light button-large"
               href="{{ route('pages.show', ['locale' => $locale, 'slug' => $requestSlug]) }}">
                {{ $text['cta_button'] }}
            </a>
        </div>
    </div>
</section>

</div>{{-- .service-page --}}
