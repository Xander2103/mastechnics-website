<?php

namespace Database\Seeders;

use App\Models\PageTranslation;
use Illuminate\Database\Seeder;

class PageContentSeeder extends Seeder
{
    public function run(): void
    {
        $updates = [
            // ── Home ──────────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'home',
                'meta_title' => 'Mastechnics — Technische service voor verwarming, airco, sanitair en meer',
                'meta_description' => 'Mastechnics biedt technische service voor verwarming, airco, sanitair, ventilatie, waterverzachters en koeling. Vraag online een offerte of interventie aan.',
            ],
            ['locale' => 'fr', 'slug' => 'accueil',
                'meta_title' => 'Mastechnics — Service technique pour chauffage, climatisation, plomberie et plus',
                'meta_description' => 'Mastechnics propose un service technique pour chauffage, climatisation, plomberie, ventilation, adoucisseurs et réfrigération. Demandez un devis en ligne.',
            ],
            ['locale' => 'en', 'slug' => 'home',
                'meta_title' => 'Mastechnics — Technical service for heating, air conditioning, plumbing and more',
                'meta_description' => 'Mastechnics provides technical services for heating, air conditioning, plumbing, ventilation, water softeners and refrigeration. Request a quote online.',
            ],

            // ── Verwarming ────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'verwarming',
                'content' => 'Mastechnics verzorgt het onderhoud, de herstelling en de installatie van verwarmingssystemen voor particulieren en bedrijven. Van klassieke gasketel tot moderne warmtepomp: wij werken met alle gangbare systemen. Via een duidelijke technische intake zorgen wij voor snelle opvolging en eerlijk advies.',
                'meta_title' => 'Verwarming — Mastechnics | Onderhoud, herstelling en installatie',
                'meta_description' => 'Professionele verwarmingsservice: onderhoud, herstelling en installatie van gasketel, condensatieketel of warmtepomp. Vraag snel een offerte of interventie aan.',
            ],
            ['locale' => 'fr', 'slug' => 'chauffage',
                'content' => 'Mastechnics assure l\'entretien, la réparation et l\'installation de systèmes de chauffage pour particuliers et entreprises. Des chaudières gaz aux pompes à chaleur : nous travaillons avec tous les systèmes courants. Une prise en charge technique claire garantit un suivi rapide et un conseil honnête.',
                'meta_title' => 'Chauffage — Mastechnics | Entretien, réparation et installation',
                'meta_description' => 'Service de chauffage professionnel : entretien, réparation et installation de chaudière gaz, condensation ou pompe à chaleur. Demandez un devis ou une intervention.',
            ],
            ['locale' => 'en', 'slug' => 'heating',
                'content' => 'Mastechnics provides maintenance, repair and installation of heating systems for homes and businesses. From gas boilers to heat pumps: we work with all common systems. A clear technical intake ensures faster follow-up and honest advice.',
                'meta_title' => 'Heating — Mastechnics | Maintenance, repair and installation',
                'meta_description' => 'Professional heating service: maintenance, repair and installation of gas boiler, condensing boiler or heat pump. Request a fast quote or call-out.',
            ],

            // ── Airco ─────────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'airco',
                'content' => 'Mastechnics installeert, onderhoudt en herstelt airconditioningsystemen voor woning, kantoor en bedrijf. Wij werken met split-units, multi-split en commerciële systemen van alle gangbare merken. Een grondige technische intake helpt ons een scherpe en correcte offerte op te maken.',
                'meta_title' => 'Airco — Mastechnics | Installatie, onderhoud en herstelling',
                'meta_description' => 'Airco installatie, onderhoud en herstelling voor particulieren en bedrijven. Split-unit, multi-split of commercieel systeem. Vraag snel een offerte aan.',
            ],
            ['locale' => 'fr', 'slug' => 'climatisation',
                'content' => 'Mastechnics installe, entretient et répare les systèmes de climatisation pour habitations, bureaux et entreprises. Nous travaillons avec des unités split, multi-split et des systèmes commerciaux de toutes les marques courantes. Une prise en charge technique approfondie nous aide à établir un devis précis.',
                'meta_title' => 'Climatisation — Mastechnics | Installation, entretien et réparation',
                'meta_description' => 'Installation, entretien et réparation de climatisation pour particuliers et entreprises. Split-unit, multi-split ou système commercial. Demandez un devis rapide.',
            ],
            ['locale' => 'en', 'slug' => 'air-conditioning',
                'content' => 'Mastechnics installs, maintains and repairs air conditioning systems for homes, offices and businesses. We work with split units, multi-split and commercial systems from all common brands. A thorough technical intake helps us prepare an accurate and competitive quote.',
                'meta_title' => 'Air conditioning — Mastechnics | Installation, maintenance and repair',
                'meta_description' => 'Air conditioning installation, maintenance and repair for homes and businesses. Split unit, multi-split or commercial system. Request a fast quote.',
            ],

            // ── Sanitair ──────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'sanitair',
                'content' => 'Mastechnics biedt professionele hulp bij sanitaire installaties, herstellingen en loodgieterswerk. Van waterlek tot volledige badkamerrenovatie: wij reageren snel en werken degelijk, voor zowel particulieren als professionele klanten.',
                'meta_title' => 'Sanitair — Mastechnics | Installatie, herstelling en loodgieterswerk',
                'meta_description' => 'Professioneel sanitair en loodgieterswerk: waterlek, verstopte afvoer, badkamer of toilet. Snelle interventie voor particulieren en bedrijven in België.',
            ],
            ['locale' => 'fr', 'slug' => 'plomberie',
                'content' => 'Mastechnics propose une aide professionnelle pour les installations et réparations sanitaires et de plomberie. De la fuite d\'eau à la rénovation complète de salle de bain : nous intervenons rapidement et efficacement, pour les particuliers comme pour les professionnels.',
                'meta_title' => 'Plomberie — Mastechnics | Installation, réparation et sanitaire',
                'meta_description' => 'Plomberie et sanitaire professionnels : fuite d\'eau, évacuation bouchée, salle de bain ou toilette. Intervention rapide pour particuliers et entreprises.',
            ],
            ['locale' => 'en', 'slug' => 'plumbing',
                'content' => 'Mastechnics provides professional help with plumbing installations and repairs. From water leaks to complete bathroom renovations: we respond quickly and work reliably for both homeowners and businesses.',
                'meta_title' => 'Plumbing — Mastechnics | Installation, repair and sanitary work',
                'meta_description' => 'Professional plumbing and sanitary work: water leak, blocked drain, bathroom or toilet. Fast response for homeowners and businesses in Belgium.',
            ],

            // ── Ventilatie ────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'ventilatie',
                'content' => 'Mastechnics plaatst, onderhoudt en herstelt ventilatiesystemen voor woningen, appartementen en bedrijven. Systeem C, systeem D of mechanische ventilatie op maat: wij zorgen voor een correcte installatie en EPB-conforme oplevering.',
                'meta_title' => 'Ventilatie — Mastechnics | Plaatsing en onderhoud van ventilatiesystemen',
                'meta_description' => 'Ventilatiesystemen voor woning en bedrijf: plaatsing en onderhoud van systeem C, D en mechanische ventilatie. EPB-conform en energiezuinig. Vraag een offerte aan.',
            ],
            ['locale' => 'fr', 'slug' => 'ventilation',
                'content' => 'Mastechnics installe, entretient et répare les systèmes de ventilation pour habitations, appartements et entreprises. Système C, système D ou ventilation mécanique sur mesure : nous garantissons une installation correcte et une réception conforme aux normes EPB.',
                'meta_title' => 'Ventilation — Mastechnics | Installation et entretien de systèmes de ventilation',
                'meta_description' => 'Systèmes de ventilation pour habitations et entreprises : installation et entretien système C, D et ventilation mécanique. Conforme aux normes EPB.',
            ],
            ['locale' => 'en', 'slug' => 'ventilation',
                'content' => 'Mastechnics installs, maintains and repairs ventilation systems for homes, apartments and businesses. System C, system D or custom mechanical ventilation: we ensure correct installation and EPB-compliant delivery.',
                'meta_title' => 'Ventilation — Mastechnics | Installation and maintenance of ventilation systems',
                'meta_description' => 'Ventilation systems for homes and businesses: installation and maintenance of system C, D and mechanical ventilation. EPB-compliant and energy-efficient.',
            ],

            // ── Waterverzachters ──────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'waterverzachters',
                'content' => 'Mastechnics adviseert, installeert en onderhoudt waterverzachters voor zones met hard water in België. Wij werken met kwalitatieve toestellen van erkende merken en zorgen voor een correcte installatie, aansluiting en inregeling.',
                'meta_title' => 'Waterverzachters — Mastechnics | Advies, installatie en onderhoud',
                'meta_description' => 'Waterverzachter plaatsen of onderhouden in België? Advies bij kalkproblemen, professionele installatie en periodiek onderhoud. Vraag vrijblijvend een offerte aan.',
            ],
            ['locale' => 'fr', 'slug' => 'adoucisseurs-eau',
                'content' => 'Mastechnics conseille, installe et entretient des adoucisseurs d\'eau pour les zones à eau dure en Belgique. Nous travaillons avec des appareils de qualité de marques reconnues et garantissons une installation correcte, un raccordement et un réglage soignés.',
                'meta_title' => 'Adoucisseurs d\'eau — Mastechnics | Conseil, installation et entretien',
                'meta_description' => 'Installer ou entretenir un adoucisseur d\'eau en Belgique ? Conseil pour problèmes de calcaire, installation professionnelle et entretien périodique.',
            ],
            ['locale' => 'en', 'slug' => 'water-softeners',
                'content' => 'Mastechnics advises, installs and maintains water softeners for hard water areas in Belgium. We work with quality appliances from recognised brands and ensure correct installation, connection and calibration.',
                'meta_title' => 'Water softeners — Mastechnics | Advice, installation and maintenance',
                'meta_description' => 'Install or maintain a water softener in Belgium? Expert advice for hard water problems, professional installation and periodic servicing. Request a free quote.',
            ],

            // ── Koelcellen ────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'koelcellen',
                'content' => 'Mastechnics ontwerpt, installeert en onderhoudt koelinstallaties en koelcellen voor de horeca, voedingsindustrie en commerciële sector. Wij werken met erkende F-gas technici en leveren betrouwbare koeloplossingen op maat van uw activiteit.',
                'meta_title' => 'Koelcellen — Mastechnics | Installatie en onderhoud van koelinstallaties',
                'meta_description' => 'Koelcel of koelinstallatie voor horeca of industrie in België? Plaatsing, onderhoud en herstelling door erkende F-gas installateur. Vraag een offerte aan.',
            ],
            ['locale' => 'fr', 'slug' => 'chambres-froides',
                'content' => 'Mastechnics conçoit, installe et entretient des installations frigorifiques et chambres froides pour l\'horeca, l\'industrie alimentaire et le secteur commercial. Nous faisons appel à des techniciens F-gaz agréés et proposons des solutions de réfrigération sur mesure.',
                'meta_title' => 'Chambres froides — Mastechnics | Installation et entretien de réfrigération',
                'meta_description' => 'Chambre froide ou installation frigorifique pour l\'horeca ou l\'industrie en Belgique ? Installation, entretien et réparation par technicien F-gaz agréé.',
            ],
            ['locale' => 'en', 'slug' => 'cold-rooms',
                'content' => 'Mastechnics designs, installs and maintains refrigeration installations and cold rooms for the catering industry, food sector and commercial clients. We use certified F-gas technicians and deliver reliable cooling solutions tailored to your business.',
                'meta_title' => 'Cold rooms — Mastechnics | Installation and maintenance of refrigeration',
                'meta_description' => 'Cold rooms or refrigeration for catering or industry in Belgium? Installation, maintenance and repair by certified F-gas technician. Request a quote.',
            ],
        ];

        foreach ($updates as $data) {
            $slug = $data['slug'];
            unset($data['slug']);
            PageTranslation::where('locale', $data['locale'])
                ->where('slug', $slug)
                ->update(array_filter($data, fn($v) => $v !== null));
        }
    }
}
