# Homepage & Service Pages Polish — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade all public-facing pages (homepage + 6 service pages) with stronger copy, service-specific content sections, better visual hierarchy, and improved SEO metadata — all in NL/FR/EN.

**Architecture:** Three layers of change: (1) DB page_translations records updated with real copy via a one-shot seeder, (2) Blade partials enriched with service-specific PHP arrays and new section blocks, (3) CSS additions for new sections. No DB schema changes. Service-specific content lives in the Blade PHP blocks (version-controlled), keyed by service key derived from `config('services')`.

**Tech Stack:** Laravel 12 Blade, PHP arrays for i18n labels, CSS custom properties. All content in NL/FR/EN. Windows PowerShell for shell commands.

---

## File Map

| File | What changes |
|------|-------------|
| `database/seeders/PageContentSeeder.php` | **Create** — updates `page_translations` with real copy and SEO meta for all 6 service pages + home in NL/FR/EN |
| `resources/views/pages/partials/home-page.blade.php` | Add "Waarom Mastechnics" benefits section; strengthen hero panel and CTA copy |
| `resources/views/pages/partials/service-page.blade.php` | Add service key detection; add service-specific use-cases and highlights arrays; add two new sections |
| `resources/css/pages/home.css` | Add `.why-grid`, `.why-card`, `.hero-chips` styles |
| `resources/css/pages/service.css` | Add `.use-cases-list`, `.highlights-grid`, `.service-cta-block` styles |
| `config/services.php` | Update `description` field per service in all three locales (richer one-liner shown in service cards) |

---

## Task 1: Update service descriptions in config/services.php

**Files:** Modify `config/services.php`

Service card descriptions shown on the homepage come from `config('services')[key]['translations'][locale]['description']`. The current ones are generic. Replace with clearer, more useful one-liners.

- [ ] **Step 1: Update config/services.php descriptions**

Open `config/services.php` and replace each `description` value with the following:

```php
'heating' => [
    'translations' => [
        'nl' => ['description' => 'Onderhoud, herstelling en installatie van gasketel, condensatieketel of warmtepomp.'],
        'fr' => ['description' => 'Entretien, réparation et installation de chaudière gaz, condensation ou pompe à chaleur.'],
        'en' => ['description' => 'Maintenance, repair and installation of gas boiler, condensing boiler or heat pump.'],
    ],
],
'airco' => [
    'translations' => [
        'nl' => ['description' => 'Plaatsing, onderhoud en herstelling van split-unit, multi-split en commerciële airco.'],
        'fr' => ['description' => 'Installation, entretien et réparation de split-unit, multi-split et climatisation commerciale.'],
        'en' => ['description' => 'Installation, maintenance and repair of split-unit, multi-split and commercial air conditioning.'],
    ],
],
'plumbing' => [
    'translations' => [
        'nl' => ['description' => 'Herstelling en installatie van sanitair, loodgieterswerk en badkamerprojecten.'],
        'fr' => ['description' => 'Réparation et installation sanitaire, plomberie et projets de salle de bain.'],
        'en' => ['description' => 'Repair and installation of plumbing, sanitary fixtures and bathroom projects.'],
    ],
],
'ventilation' => [
    'translations' => [
        'nl' => ['description' => 'Plaatsing en onderhoud van ventilatiesystemen type C en D voor woning en bedrijf.'],
        'fr' => ['description' => 'Installation et entretien de systèmes de ventilation type C et D pour habitations et entreprises.'],
        'en' => ['description' => 'Installation and maintenance of ventilation systems type C and D for homes and businesses.'],
    ],
],
'water-softeners' => [
    'translations' => [
        'nl' => ['description' => 'Advies, plaatsing en onderhoud van waterverzachters bij kalkproblemen.'],
        'fr' => ['description' => 'Conseil, installation et entretien d\'adoucisseurs d\'eau en cas de problèmes de calcaire.'],
        'en' => ['description' => 'Advice, installation and maintenance of water softeners for hard water issues.'],
    ],
],
'cold-rooms' => [
    'translations' => [
        'nl' => ['description' => 'Ontwerp, installatie en onderhoud van koelcellen en koelinstallaties voor horeca en industrie.'],
        'fr' => ['description' => 'Conception, installation et entretien de chambres froides pour l\'horeca et l\'industrie.'],
        'en' => ['description' => 'Design, installation and maintenance of cold rooms and refrigeration for catering and industry.'],
    ],
],
```

**Important:** Only change the `description` value in each translation block. Keep all other keys (`title`, `slug`, `is_active`) exactly as they are.

- [ ] **Step 2: Verify PHP lint**

```
php -l config/services.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```
git add config/services.php
git commit -m "feat: improve service card descriptions in config"
```

---

## Task 2: Create and run PageContentSeeder

**Files:** Create `database/seeders/PageContentSeeder.php`

This seeder updates `page_translations` records with real copy for all 6 service pages + home in NL/FR/EN:
- `content` — 2–3 sentence service description shown in the "Wat kunnen we doen?" section
- `meta_title` — SEO title with keyword + brand
- `meta_description` — 150–160 char description for search snippets

- [ ] **Step 1: Create the seeder file**

Create `database/seeders/PageContentSeeder.php`:

```php
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
```

- [ ] **Step 2: Run the seeder**

```
php artisan db:seed --class=PageContentSeeder
```

Expected: no errors, command exits cleanly.

- [ ] **Step 3: Verify a few records updated**

```
php artisan tinker --execute="echo App\Models\PageTranslation::where('slug','verwarming')->value('meta_title');"
```

Expected output: `Verwarming — Mastechnics | Onderhoud, herstelling en installatie`

- [ ] **Step 4: Commit**

```
git add database/seeders/PageContentSeeder.php
git commit -m "feat: add PageContentSeeder with real copy and SEO meta for all service pages"
```

---

## Task 3: Improve home-page.blade.php

**Files:** Modify `resources/views/pages/partials/home-page.blade.php`

**Changes:**
1. Add service icon indicators (Unicode symbols stored in the service data lookup)
2. Improve hero badge + panel copy
3. Add new "Waarom Mastechnics" section with 4 concrete benefits
4. Improve CTA copy

- [ ] **Step 1: Add `why_us` section labels and strengthen hero labels**

Replace the `$labels` PHP array in `home-page.blade.php`. Add `hero_intro`, `why_label`, `why_title`, `why_items`, and improve existing copy. Replace the full `$labels` block (lines 12–170) with:

```php
    $labels = [
        'nl' => [
            'primary_cta'    => 'Vraag een offerte aan',
            'secondary_cta'  => 'Bekijk onze diensten',
            'hero_badge'     => 'Technische service — particulieren & bedrijven',

            'panel_label'  => 'Slimme intake',
            'panel_title'  => 'Beschrijf uw situatie eenmalig duidelijk.',
            'panel_points' => [
                'Kies de juiste dienst: verwarming, airco, sanitair…',
                'Vul technische gegevens in over uw installatie of probleem',
                'Voeg desgewenst foto\'s toe voor snellere inschatting',
                'Ontvang sneller een richtprijs of concreet voorstel',
            ],

            'services_label' => 'Diensten',
            'services_title' => 'Alle technische diensten onder één dak',
            'services_intro' =>
                'Van verwarmingsonderhoud en airco-installatie tot sanitaire herstellingen en koelcellen: ' .
                $siteName . ' helpt zowel particulieren als professionele klanten snel verder.',
            'more_info'      => 'Meer info →',

            'why_label' => 'Waarom Mastechnics',
            'why_title' => 'Snelle, duidelijke en vakkundige service.',
            'why_items' => [
                [
                    'title'       => 'Erkende technici',
                    'description' => 'Gecertificeerde installateurs voor gas, F-gas en verwante disciplines. Correcte uitvoering, conform de geldende normen.',
                ],
                [
                    'title'       => 'Snelle opvolging',
                    'description' => 'Via de online aanvraagflow komt alle informatie gestructureerd binnen, zodat er sneller een inschatting of afspraak gemaakt kan worden.',
                ],
                [
                    'title'       => 'Voor particulieren én bedrijven',
                    'description' => 'Van éénmalige interventie bij een panne tot periodiek onderhoud voor vaste klanten — zowel residentieel als commercieel.',
                ],
                [
                    'title'       => 'Eerlijk advies',
                    'description' => 'Geen onnodige ingrepen. Wij geven een correcte inschatting op basis van de feiten en de technische situatie.',
                ],
            ],

            'process_label' => 'Hoe werkt het?',
            'process_title' => 'Van aanvraag tot oplossing — in vier stappen.',
            'process_intro' =>
                'De aanvraagflow verzamelt de juiste technische informatie meteen bij de eerste contactopname. ' .
                'Dat bespaart heen-en-weer bellen en mailen, en versnelt de opvolging.',
            'process_steps' => [
                [
                    'title'       => '1. Kies je dienst',
                    'description' => 'Verwarming, airco, sanitair, ventilatie, waterverzachter of koeling — selecteer wat van toepassing is.',
                ],
                [
                    'title'       => '2. Beschrijf je situatie',
                    'description' => 'Gaat het om een storing, onderhoud, nieuwe installatie of een project? Geef de context mee.',
                ],
                [
                    'title'       => '3. Voeg technische info toe',
                    'description' => 'Type toestel, merk, model, serienummer of foto\'s van het typeplaatje helpen voor een snellere inschatting.',
                ],
                [
                    'title'       => '4. Snellere inschatting',
                    'description' => 'Met volledige info kan er sneller een richtprijs, advies of concrete afspraak voorgesteld worden.',
                ],
            ],

            'cta_label'  => 'Direct starten',
            'cta_title'  => 'Snel een offerte of interventie aanvragen?',
            'cta_text'   =>
                'Vul de slimme aanvraagflow in en beschrijf uw situatie zo concreet mogelijk. ' .
                'Wij nemen zo snel mogelijk contact op met een voorstel of vervolgstap.',
            'cta_button' => 'Start aanvraag',
        ],

        'fr' => [
            'primary_cta'    => 'Demander un devis',
            'secondary_cta'  => 'Voir nos services',
            'hero_badge'     => 'Service technique — particuliers et entreprises',

            'panel_label'  => 'Prise en charge intelligente',
            'panel_title'  => 'Décrivez votre situation une seule fois, clairement.',
            'panel_points' => [
                'Choisissez le bon service : chauffage, climatisation, plomberie…',
                'Ajoutez les données techniques de votre installation ou problème',
                'Joignez des photos pour une estimation plus rapide',
                'Recevez plus vite une estimation ou une proposition concrète',
            ],

            'services_label' => 'Services',
            'services_title' => 'Tous les services techniques sous un même toit',
            'services_intro' =>
                'De l\'entretien de chauffage à l\'installation de climatisation, en passant par les réparations sanitaires et les chambres froides : ' .
                $siteName . ' aide aussi bien les particuliers que les clients professionnels.',
            'more_info'      => 'Plus d\'infos →',

            'why_label' => 'Pourquoi Mastechnics',
            'why_title' => 'Un service rapide, clair et professionnel.',
            'why_items' => [
                [
                    'title'       => 'Techniciens certifiés',
                    'description' => 'Installateurs certifiés pour le gaz, les fluides frigorigènes (F-gaz) et les disciplines connexes. Exécution correcte, conforme aux normes en vigueur.',
                ],
                [
                    'title'       => 'Suivi rapide',
                    'description' => 'Via le flux de demande en ligne, toutes les informations arrivent de manière structurée, ce qui permet une estimation ou une prise de rendez-vous plus rapide.',
                ],
                [
                    'title'       => 'Pour particuliers et entreprises',
                    'description' => 'D\'une intervention ponctuelle en cas de panne à un entretien périodique pour clients réguliers — résidentiel comme commercial.',
                ],
                [
                    'title'       => 'Conseil honnête',
                    'description' => 'Pas d\'interventions inutiles. Nous donnons une estimation correcte basée sur les faits et la situation technique.',
                ],
            ],

            'process_label' => 'Comment ça fonctionne ?',
            'process_title' => 'De la demande à la solution — en quatre étapes.',
            'process_intro' =>
                'Le flux de demande recueille les bonnes informations techniques dès le premier contact. ' .
                'Cela évite les allers-retours par téléphone ou e-mail et accélère le suivi.',
            'process_steps' => [
                [
                    'title'       => '1. Choisissez votre service',
                    'description' => 'Chauffage, climatisation, plomberie, ventilation, adoucisseur ou réfrigération — sélectionnez ce qui s\'applique.',
                ],
                [
                    'title'       => '2. Décrivez votre situation',
                    'description' => 'S\'agit-il d\'une panne, d\'un entretien, d\'une nouvelle installation ou d\'un projet ? Donnez le contexte.',
                ],
                [
                    'title'       => '3. Ajoutez les infos techniques',
                    'description' => 'Type d\'appareil, marque, modèle, numéro de série ou photos de la plaque signalétique pour une estimation plus rapide.',
                ],
                [
                    'title'       => '4. Estimation plus rapide',
                    'description' => 'Avec des informations complètes, il est plus facile de proposer une estimation, un conseil ou un rendez-vous concret.',
                ],
            ],

            'cta_label'  => 'Commencer maintenant',
            'cta_title'  => 'Besoin d\'un devis ou d\'une intervention rapide ?',
            'cta_text'   =>
                'Remplissez le formulaire de demande intelligent et décrivez votre situation aussi concrètement que possible. ' .
                'Nous vous contacterons dès que possible avec une proposition ou une prochaine étape.',
            'cta_button' => 'Démarrer ma demande',
        ],

        'en' => [
            'primary_cta'    => 'Request a quote',
            'secondary_cta'  => 'View our services',
            'hero_badge'     => 'Technical service — homes and businesses',

            'panel_label'  => 'Smart intake',
            'panel_title'  => 'Describe your situation once, clearly.',
            'panel_points' => [
                'Choose the right service: heating, air conditioning, plumbing…',
                'Add technical details about your installation or issue',
                'Attach photos for a faster assessment',
                'Receive a faster estimate or concrete proposal',
            ],

            'services_label' => 'Services',
            'services_title' => 'All technical services under one roof',
            'services_intro' =>
                'From heating maintenance and air conditioning installation to plumbing repairs and cold rooms: ' .
                $siteName . ' helps both homeowners and professional clients quickly.',
            'more_info'      => 'More info →',

            'why_label' => 'Why Mastechnics',
            'why_title' => 'Fast, clear and professional service.',
            'why_items' => [
                [
                    'title'       => 'Certified technicians',
                    'description' => 'Certified installers for gas, F-gas refrigerants and related disciplines. Correct execution, in line with applicable standards.',
                ],
                [
                    'title'       => 'Fast follow-up',
                    'description' => 'The online request flow collects all information in a structured way, enabling a faster estimate or appointment.',
                ],
                [
                    'title'       => 'For homes and businesses',
                    'description' => 'From a one-off emergency call-out to periodic maintenance contracts — both residential and commercial.',
                ],
                [
                    'title'       => 'Honest advice',
                    'description' => 'No unnecessary work. We give a correct assessment based on the facts and the technical situation on site.',
                ],
            ],

            'process_label' => 'How it works',
            'process_title' => 'From request to solution — in four steps.',
            'process_intro' =>
                'The request flow collects the right technical information at first contact. ' .
                'This eliminates unnecessary back-and-forth and speeds up follow-up.',
            'process_steps' => [
                [
                    'title'       => '1. Choose your service',
                    'description' => 'Heating, air conditioning, plumbing, ventilation, water softener or refrigeration — select what applies.',
                ],
                [
                    'title'       => '2. Describe your situation',
                    'description' => 'Is it a breakdown, maintenance, new installation or a project? Provide the context.',
                ],
                [
                    'title'       => '3. Add technical details',
                    'description' => 'Device type, brand, model, serial number or photos of the nameplate help for a faster assessment.',
                ],
                [
                    'title'       => '4. Faster estimate',
                    'description' => 'With complete information it is easier to propose an estimate, advice or a concrete next step.',
                ],
            ],

            'cta_label'  => 'Get started',
            'cta_title'  => 'Need a quote or fast call-out?',
            'cta_text'   =>
                'Complete the smart request form and describe your situation as concretely as possible. ' .
                'We will contact you as soon as possible with a proposal or next step.',
            'cta_button' => 'Start request',
        ],
    ];
```

- [ ] **Step 2: Add `why_us` section to the HTML**

After the `</section>` closing tag of the services section (after the `.service-grid` div, before the process section), add this new section:

```blade
<section class="section section-alt">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['why_label'] }}</span>
            <h2>{{ $text['why_title'] }}</h2>
        </div>

        <div class="why-grid">
            @foreach ($text['why_items'] as $item)
                <article class="why-card">
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
```

- [ ] **Step 3: Commit**

```
git add resources/views/pages/partials/home-page.blade.php
git commit -m "feat: strengthen homepage copy and add why-us section"
```

---

## Task 4: Rewrite service-page.blade.php with service-specific sections

**Files:** Modify `resources/views/pages/partials/service-page.blade.php`

Replace the entire file with a richer version that:
- Detects the service key from `config('services')`
- Has service-specific use-cases and highlights in PHP arrays (NL/FR/EN)
- Adds two new sections: "Typische situaties" and "Waarom Mastechnics voor dit"
- Has a better CTA block

- [ ] **Step 1: Replace the full service-page.blade.php**

```php
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

    // ── Shared labels ──────────────────────────────────────────────────────────
    $labels = [
        'nl' => [
            'type'           => 'Service',
            'quote'          => 'Vraag een offerte of interventie aan',
            'what'           => 'Wat kunnen we voor u doen?',
            'situations'     => 'Typische situaties',
            'why'            => 'Waarom ' . $siteName . ' voor dit?',
            'cta_badge'      => 'Direct starten',
            'cta_title'      => 'Klaar om een aanvraag in te dienen?',
            'cta_text'       => 'Beschrijf uw probleem of project via de slimme aanvraagflow en wij nemen zo snel mogelijk contact op.',
            'cta_button'     => 'Start aanvraag',
        ],
        'fr' => [
            'type'           => 'Service',
            'quote'          => 'Demander un devis ou une intervention',
            'what'           => 'Que pouvons-nous faire pour vous ?',
            'situations'     => 'Situations typiques',
            'why'            => 'Pourquoi ' . $siteName . ' pour cela ?',
            'cta_badge'      => 'Commencer maintenant',
            'cta_title'      => 'Prêt à soumettre une demande ?',
            'cta_text'       => 'Décrivez votre problème ou projet via le flux de demande intelligent et nous vous contacterons dès que possible.',
            'cta_button'     => 'Démarrer ma demande',
        ],
        'en' => [
            'type'           => 'Service',
            'quote'          => 'Request a quote or call-out',
            'what'           => 'How can we help?',
            'situations'     => 'Typical situations',
            'why'            => 'Why ' . $siteName . ' for this?',
            'cta_badge'      => 'Get started',
            'cta_title'      => 'Ready to submit a request?',
            'cta_text'       => 'Describe your issue or project via the smart request flow and we will get back to you as soon as possible.',
            'cta_button'     => 'Start request',
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
        ?? $serviceContent[$currentServiceKey]['nl']
        ?? null;
@endphp

<section class="service-hero">
    <div class="container">
        <span class="eyebrow">{{ $text['type'] }}</span>

        <h1>{{ $translation->title }}</h1>

        @if ($translation->intro)
            <p class="service-intro">{{ $translation->intro }}</p>
        @endif
    </div>
</section>

<section class="section section-white">
    <div class="container">
        <div class="section-header">
            <h2>{{ $text['what'] }}</h2>

            @if ($translation->content)
                <p>{{ $translation->content }}</p>
            @endif

            <div class="button-row service-content-button">
                <a class="button button-primary button-large"
                    href="{{ route('pages.show', [
                        'locale' => $locale,
                        'slug' => $requestSlug,
                    ]) }}">
                    {{ $text['quote'] }}
                </a>
            </div>
        </div>
    </div>
</section>

@if ($currentContent)
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2>{{ $text['situations'] }}</h2>
            </div>

            <ul class="use-cases-list">
                @foreach ($currentContent['situations'] as $situation)
                    <li>{{ $situation }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            <div class="section-header">
                <h2>{{ $text['why'] }}</h2>
            </div>

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

<section class="service-cta-section">
    <div class="container">
        <div class="home-cta">
            <div>
                <span class="eyebrow eyebrow-dark">{{ $text['cta_badge'] }}</span>
                <h2>{{ $text['cta_title'] }}</h2>
                <p>{{ $text['cta_text'] }}</p>
            </div>

            <a class="button button-light button-large"
               href="{{ route('pages.show', [
                   'locale' => $locale,
                   'slug' => $requestSlug,
               ]) }}">
                {{ $text['cta_button'] }}
            </a>
        </div>
    </div>
</section>
```

- [ ] **Step 2: Verify PHP lint**

```
php -l resources/views/pages/partials/service-page.blade.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```
git add resources/views/pages/partials/service-page.blade.php
git commit -m "feat: add service-specific use cases and highlights to service pages"
```

---

## Task 5: Add CSS for new sections

**Files:** Modify `resources/css/pages/home.css`, `resources/css/pages/service.css`

- [ ] **Step 1: Add to home.css — why-grid section and section-alt background**

Append to `resources/css/pages/home.css`:

```css
/* ================================
   Section alt (light gray background)
================================ */

.section-alt {
    background: var(--color-background);
    padding: 64px 0;
}

/* ================================
   Why Mastechnics grid
================================ */

.why-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-top: 32px;
}

.why-card {
    padding: 26px 28px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-medium);
    background: var(--color-white);
    box-shadow: var(--shadow-soft);
}

.why-card h3 {
    margin-bottom: 8px;
    font-size: 1.05rem;
    font-weight: 900;
    color: var(--color-primary-dark);
}

.why-card p {
    margin: 0;
    color: var(--color-muted);
    line-height: 1.6;
}

@media (max-width: 1000px) {
    .why-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 680px) {
    .section-alt {
        padding: 40px 0;
    }
}
```

- [ ] **Step 2: Add to service.css — use cases list, highlights grid, CTA section**

Append to `resources/css/pages/service.css`:

```css
/* ================================
   Use cases list
================================ */

.use-cases-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px 32px;
}

.use-cases-list li {
    position: relative;
    padding-left: 20px;
    color: var(--color-text);
    line-height: 1.55;
}

.use-cases-list li::before {
    content: "✓";
    position: absolute;
    left: 0;
    color: var(--color-primary);
    font-weight: 900;
}

/* ================================
   Service highlights grid
================================ */

.service-highlights-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

/* ================================
   Service CTA section (dark)
================================ */

.service-cta-section {
    background:
        radial-gradient(circle at top right, rgba(255,255,255,0.08), transparent 40%),
        var(--color-primary-dark);
    padding: 52px 0;
}

.service-cta-section .home-cta {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 36px;
    align-items: center;
}

.service-cta-section h2,
.service-cta-section p {
    color: var(--color-white);
}

@media (max-width: 1000px) {
    .use-cases-list {
        grid-template-columns: 1fr;
    }

    .service-highlights-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 680px) {
    .service-cta-section .home-cta {
        grid-template-columns: 1fr;
    }

    .service-cta-section .home-cta .button {
        width: 100%;
        text-align: center;
        justify-content: center;
    }
}
```

- [ ] **Step 3: Commit**

```
git add resources/css/pages/home.css resources/css/pages/service.css
git commit -m "feat: add why-grid, use-cases-list, service-highlights, service-cta CSS"
```

---

## Task 6: Build, test, commit

- [ ] **Step 1: Run npm build**

```
npm run build
```

Expected: exits 0, no errors.

- [ ] **Step 2: Run tests**

```
php artisan test
```

Expected: all tests pass.

- [ ] **Step 3: Run route list (spot check)**

```
php artisan route:list --path=nl
```

Expected: all public routes present.

- [ ] **Step 4: Final commit if any remaining changes**

```
git add -A
git commit -m "feat: improve homepage and service page content and visuals"
```

---

## Self-Review: Spec Coverage

| Spec requirement | Task covering it |
|-----------------|-----------------|
| Stronger hero copy | Task 3 (labels rewrite) |
| Better service overview | Task 1 (config descriptions) |
| "Why choose us" section | Task 3 (why_items) + Task 5 (CSS) |
| Better CTA flow | Task 3 (cta_label/title/text/button) |
| Service-page better intro | Task 2 (DB content field) |
| Service-page use cases | Task 4 (situations array) |
| Service-page highlights | Task 4 (highlights array) |
| Service-page CTA block | Task 4 (service-cta-section) |
| SEO meta titles | Task 2 (meta_title in DB) |
| SEO meta descriptions | Task 2 (meta_description in DB) |
| NL/FR/EN throughout | All tasks — every label has 3 locales |
| No DB schema change | Confirmed — only data updates |
| No request flow touched | Confirmed — no request-page files changed |
| No admin touched | Confirmed |
| `npm run build` passes | Task 6 |
| `php artisan test` passes | Task 6 |
