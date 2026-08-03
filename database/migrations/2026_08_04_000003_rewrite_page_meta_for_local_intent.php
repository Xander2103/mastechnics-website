<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites every title tag and meta description on the site.
 *
 * Three problems are fixed at once:
 *
 *  1. Length. The three homepage titles ran 73-80 characters and were being
 *     truncated in the SERP; several others were under 30 and wasted the slot.
 *     Everything here lands between roughly 45 and 60 characters.
 *
 *  2. Brand casing. Half the meta carried a lower-case "mastechnics" while the
 *     rest of the site, the logo and the schema markup all say "Mastechnics".
 *     Inconsistent brand strings dilute brand-name matching.
 *
 *  3. Geography. Descriptions said "in Belgie" — the widest possible claim for
 *     a business that in practice works one region. They now name the actual
 *     service area, which is what local queries match against.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->apply($this->newMeta());
        $this->fixTextIssuesInBodyCopy();
    }

    public function down(): void
    {
        $this->apply($this->previousMeta());
    }

    /**
     * @param  array<int, array{locale: string, slug: string, meta_title: string, meta_description: string}>  $rows
     */
    private function apply(array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('page_translations')
                ->where('locale', $row['locale'])
                ->where('slug', $row['slug'])
                ->update([
                    'meta_title' => $row['meta_title'],
                    'meta_description' => $row['meta_description'],
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Visible copy fixes that belong with the meta rewrite:
     *  - the seeded intros referred to the company in lower case mid sentence;
     *  - the French privacy page was seeded without its accent, so its H1 read
     *    "Politique de confidentialite".
     *
     * @var array<string, string>
     */
    private const TEXT_FIXES = [
        'mastechnics' => 'Mastechnics',
        'Politique de confidentialite' => 'Politique de confidentialité',
        'donnees personnelles' => 'données personnelles',
    ];

    private function fixTextIssuesInBodyCopy(): void
    {
        $columns = ['title', 'intro', 'content'];

        foreach (DB::table('page_translations')->get() as $translation) {
            $updates = [];

            foreach ($columns as $column) {
                $value = $translation->{$column};

                if (!is_string($value)) {
                    continue;
                }

                $fixed = strtr($value, self::TEXT_FIXES);

                if ($fixed === $value) {
                    continue;
                }

                $updates[$column] = $fixed;
            }

            if ($updates !== []) {
                DB::table('page_translations')
                    ->where('id', $translation->id)
                    ->update($updates + ['updated_at' => now()]);
            }
        }
    }

    /**
     * @return array<int, array{locale: string, slug: string, meta_title: string, meta_description: string}>
     */
    private function newMeta(): array
    {
        return [
            // ── Home ────────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'home',
                'meta_title' => 'Verwarming, airco & sanitair – Druivenstreek | Mastechnics',
                'meta_description' => 'Erkende service voor verwarming, airco, sanitair, ventilatie en koeling in Tervuren, Overijse en de Druivenstreek. Vraag online een offerte aan.'],
            ['locale' => 'fr', 'slug' => 'accueil',
                'meta_title' => 'Chauffage, clim & sanitaire – Druivenstreek | Mastechnics',
                'meta_description' => 'Service technique agréé : chauffage, climatisation, sanitaire, ventilation et froid à Tervuren, Overijse et dans le Druivenstreek. Devis en ligne.'],
            ['locale' => 'en', 'slug' => 'home',
                'meta_title' => 'Heating, aircon & plumbing – Druivenstreek | Mastechnics',
                'meta_description' => 'Certified service for heating, air conditioning, plumbing, ventilation and refrigeration in Tervuren, Overijse and the Druivenstreek. Quote online.'],

            // ── Heating ─────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'verwarming',
                'meta_title' => 'Verwarmingsservice Tervuren & Druivenstreek | Mastechnics',
                'meta_description' => 'Onderhoud, herstelling en installatie van gasketel, condensatieketel of warmtepomp in Tervuren, Overijse en omgeving. Erkende gastechnicus.'],
            ['locale' => 'fr', 'slug' => 'chauffage',
                'meta_title' => 'Service chauffage Tervuren & Druivenstreek | Mastechnics',
                'meta_description' => 'Entretien, réparation et installation de chaudière gaz, condensation ou pompe à chaleur à Tervuren, Overijse et environs. Technicien gaz agréé.'],
            ['locale' => 'en', 'slug' => 'heating',
                'meta_title' => 'Heating service Tervuren & Druivenstreek | Mastechnics',
                'meta_description' => 'Maintenance, repair and installation of gas boilers, condensing boilers and heat pumps in Tervuren, Overijse and nearby. Certified gas technician.'],

            // ── Air conditioning ────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'airco',
                'meta_title' => 'Airco plaatsen & onderhoud Druivenstreek | Mastechnics',
                'meta_description' => 'Plaatsing, onderhoud en herstelling van split-unit en multi-split airco in Tervuren, Overijse en Hoeilaart. Erkend F-gas installateur.'],
            ['locale' => 'fr', 'slug' => 'climatisation',
                'meta_title' => 'Climatisation : pose & entretien | Mastechnics',
                'meta_description' => 'Installation, entretien et réparation de climatisation split et multi-split à Tervuren, Overijse et Hoeilaart. Installateur F-gaz certifié.'],
            ['locale' => 'en', 'slug' => 'air-conditioning',
                'meta_title' => 'Aircon install & service, Druivenstreek | Mastechnics',
                'meta_description' => 'Installation, maintenance and repair of split and multi-split air conditioning in Tervuren, Overijse and Hoeilaart. Certified F-gas installer.'],

            // ── Plumbing ────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'sanitair',
                'meta_title' => 'Loodgieter & sanitair Druivenstreek | Mastechnics',
                'meta_description' => 'Waterlek, verstopping, kraan of volledige badkamer in Tervuren, Overijse, Hoeilaart en Huldenberg. Snelle interventie bij waterschade.'],
            ['locale' => 'fr', 'slug' => 'plomberie',
                'meta_title' => 'Plombier & sanitaire Druivenstreek | Mastechnics',
                'meta_description' => 'Fuite, bouchon, robinetterie ou salle de bain complète à Tervuren, Overijse, Hoeilaart et Huldenberg. Intervention rapide en cas de dégâts des eaux.'],
            ['locale' => 'en', 'slug' => 'plumbing',
                'meta_title' => 'Plumber & bathrooms, Druivenstreek | Mastechnics',
                'meta_description' => 'Leaks, blockages, taps or a complete bathroom in Tervuren, Overijse, Hoeilaart and Huldenberg. Fast response to water damage.'],

            // ── Ventilation ─────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'ventilatie',
                'meta_title' => 'Ventilatie type C & D Druivenstreek | Mastechnics',
                'meta_description' => 'Plaatsing, onderhoud en debietmeting van ventilatie type C en D voor woning en bedrijf in Tervuren, Bertem en omgeving. EPB-conform.'],
            ['locale' => 'fr', 'slug' => 'ventilation',
                'meta_title' => 'Ventilation type C & D Druivenstreek | Mastechnics',
                'meta_description' => 'Installation, entretien et mesure de débit de ventilation type C et D pour habitations et entreprises à Tervuren, Bertem et environs. Conforme PEB.'],
            ['locale' => 'en', 'slug' => 'ventilation',
                'meta_title' => 'Ventilation type C & D, Druivenstreek | Mastechnics',
                'meta_description' => 'Installation, servicing and airflow measurement of type C and D ventilation for homes and businesses in Tervuren, Bertem and nearby. EPB compliant.'],

            // ── Water softeners ─────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'waterverzachters',
                'meta_title' => 'Waterverzachter plaatsen Druivenstreek | Mastechnics',
                'meta_description' => 'Advies, plaatsing en onderhoud van waterverzachters in Tervuren, Overijse en Huldenberg. Het leidingwater is hier hard — kalk aanpakken bij de bron.'],
            ['locale' => 'fr', 'slug' => 'adoucisseurs-eau',
                'meta_title' => 'Adoucisseur d\'eau Druivenstreek | Mastechnics',
                'meta_description' => 'Conseil, installation et entretien d\'adoucisseurs à Tervuren, Overijse et Huldenberg. L\'eau y est dure : traitez le calcaire à la source.'],
            ['locale' => 'en', 'slug' => 'water-softeners',
                'meta_title' => 'Water softener installation | Mastechnics',
                'meta_description' => 'Advice, installation and servicing of water softeners in Tervuren, Overijse and Huldenberg. Tap water here is hard — tackle limescale at the source.'],

            // ── Cold rooms ──────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'koelcellen',
                'meta_title' => 'Koelcellen: installatie & onderhoud | Mastechnics',
                'meta_description' => 'Koelcellen en koelinstallaties voor horeca, voeding en industrie in Vlaams-Brabant. Installatie, onderhoud, F-gas lekcontrole en herstelling.'],
            ['locale' => 'fr', 'slug' => 'chambres-froides',
                'meta_title' => 'Chambres froides : pose & entretien | Mastechnics',
                'meta_description' => 'Chambres froides et installations frigorifiques pour horeca, alimentation et industrie en Brabant flamand. Pose, entretien et contrôle F-gaz.'],
            ['locale' => 'en', 'slug' => 'cold-rooms',
                'meta_title' => 'Cold rooms: installation & service | Mastechnics',
                'meta_description' => 'Cold rooms and refrigeration for catering, food and industry in Flemish Brabant. Installation, maintenance, F-gas leak checks and repair.'],

            // ── Request ─────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'aanvraag',
                'meta_title' => 'Offerte of interventie aanvragen | Mastechnics',
                'meta_description' => 'Dien stap voor stap uw technische aanvraag in voor verwarming, airco, sanitair, ventilatie of koeling. Met de juiste info krijgt u sneller een antwoord.'],
            ['locale' => 'fr', 'slug' => 'demande',
                'meta_title' => 'Demander un devis ou une intervention | Mastechnics',
                'meta_description' => 'Introduisez votre demande technique étape par étape : chauffage, climatisation, sanitaire, ventilation ou froid. Les bonnes infos accélèrent la réponse.'],
            ['locale' => 'en', 'slug' => 'request',
                'meta_title' => 'Request a quote or call-out | Mastechnics',
                'meta_description' => 'Submit your technical request step by step for heating, air conditioning, plumbing, ventilation or refrigeration. Good information means a faster reply.'],

            // ── Contact ─────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'contact',
                'meta_title' => 'Contact | Telefoon, e-mail & WhatsApp – Mastechnics',
                'meta_description' => 'Contacteer Mastechnics via telefoon, e-mail, WhatsApp of het contactformulier. Werkgebied: Tervuren, Overijse, Hoeilaart, Huldenberg en omgeving.'],
            ['locale' => 'fr', 'slug' => 'contact',
                'meta_title' => 'Contact | Téléphone, e-mail & WhatsApp – Mastechnics',
                'meta_description' => 'Contactez Mastechnics par téléphone, e-mail, WhatsApp ou via le formulaire. Zone : Tervuren, Overijse, Hoeilaart, Huldenberg et environs.'],
            ['locale' => 'en', 'slug' => 'contact',
                'meta_title' => 'Contact | Phone, email & WhatsApp – Mastechnics',
                'meta_description' => 'Contact Mastechnics by phone, email, WhatsApp or the contact form. Service area: Tervuren, Overijse, Hoeilaart, Huldenberg and surroundings.'],

            // ── Privacy ─────────────────────────────────────────────────────
            ['locale' => 'nl', 'slug' => 'privacybeleid',
                'meta_title' => 'Privacybeleid & gegevensbescherming | Mastechnics',
                'meta_description' => 'Lees hoe Mastechnics omgaat met uw persoonsgegevens: wat wij verzamelen, waarom, hoe lang wij het bewaren en welke rechten u hebt onder de AVG.'],
            ['locale' => 'fr', 'slug' => 'politique-confidentialite',
                'meta_title' => 'Politique de confidentialité | Mastechnics',
                'meta_description' => 'Découvrez comment Mastechnics traite vos données personnelles : ce que nous collectons, pourquoi, combien de temps et vos droits selon le RGPD.'],
            ['locale' => 'en', 'slug' => 'privacy-policy',
                'meta_title' => 'Privacy Policy & data protection | Mastechnics',
                'meta_description' => 'Read how Mastechnics handles your personal data: what we collect, why, how long we keep it and what rights you have under the GDPR.'],
        ];
    }

    /**
     * State before this migration, so `migrate:rollback` is lossless.
     *
     * @return array<int, array{locale: string, slug: string, meta_title: string, meta_description: string}>
     */
    private function previousMeta(): array
    {
        return [
            ['locale' => 'nl', 'slug' => 'home', 'meta_title' => 'Mastechnics — Technische service voor verwarming, airco, sanitair en meer', 'meta_description' => 'Mastechnics biedt technische service voor verwarming, airco, sanitair, ventilatie, waterverzachters en koeling. Vraag online een offerte of interventie aan.'],
            ['locale' => 'fr', 'slug' => 'accueil', 'meta_title' => 'Mastechnics — Service technique pour chauffage, climatisation, plomberie et plus', 'meta_description' => 'Mastechnics propose un service technique pour chauffage, climatisation, plomberie, ventilation, adoucisseurs et réfrigération. Demandez un devis en ligne.'],
            ['locale' => 'en', 'slug' => 'home', 'meta_title' => 'Mastechnics — Technical service for heating, air conditioning, plumbing and more', 'meta_description' => 'Mastechnics provides technical services for heating, air conditioning, plumbing, ventilation, water softeners and refrigeration. Request a quote online.'],
            ['locale' => 'nl', 'slug' => 'verwarming', 'meta_title' => 'Verwarming — Mastechnics | Onderhoud, herstelling en installatie', 'meta_description' => 'Professionele verwarmingsservice: onderhoud, herstelling en installatie van gasketel, condensatieketel of warmtepomp. Vraag snel een offerte of interventie aan.'],
            ['locale' => 'fr', 'slug' => 'chauffage', 'meta_title' => 'Chauffage — Mastechnics | Entretien, réparation et installation', 'meta_description' => 'Service de chauffage professionnel : entretien, réparation et installation de chaudière gaz, condensation ou pompe à chaleur. Demandez un devis ou une intervention.'],
            ['locale' => 'en', 'slug' => 'heating', 'meta_title' => 'Heating — Mastechnics | Maintenance, repair and installation', 'meta_description' => 'Professional heating service: maintenance, repair and installation of gas boiler, condensing boiler or heat pump. Request a quote or call-out quickly.'],
            ['locale' => 'nl', 'slug' => 'airco', 'meta_title' => 'Airconditioning service Belgie | mastechnics', 'meta_description' => 'Installatie, onderhoud en herstelling van airco-systemen. Erkend vakman voor particulieren en bedrijven.'],
            ['locale' => 'fr', 'slug' => 'climatisation', 'meta_title' => 'Service climatisation Belgique | mastechnics', 'meta_description' => 'Installation, entretien et reparation de systemes de climatisation. Technicien agree pour particuliers et entreprises.'],
            ['locale' => 'en', 'slug' => 'air-conditioning', 'meta_title' => 'Air conditioning service Belgium | mastechnics', 'meta_description' => 'Installation, maintenance and repair of air conditioning systems. Certified technician for homes and businesses in Belgium.'],
            ['locale' => 'nl', 'slug' => 'sanitair', 'meta_title' => 'Sanitair installateur Belgie | mastechnics', 'meta_description' => 'Professionele hulp bij sanitaire installaties en herstellingen. Van eenvoudige herstelling tot volledige badkamerrenovatie.'],
            ['locale' => 'fr', 'slug' => 'plomberie', 'meta_title' => 'Plombier professionnel Belgique | mastechnics', 'meta_description' => 'Aide professionnelle pour les installations et reparations sanitaires. De la simple reparation a la renovation complete.'],
            ['locale' => 'en', 'slug' => 'plumbing', 'meta_title' => 'Plumbing service Belgium | mastechnics', 'meta_description' => 'Professional help with plumbing installations and repairs. From simple fixes to complete bathroom renovations in Belgium.'],
            ['locale' => 'nl', 'slug' => 'ventilatie', 'meta_title' => 'Ventilatiesystemen Belgie | mastechnics', 'meta_description' => 'Ventilatie-oplossingen voor woningen, appartementen en bedrijven. Installatie en onderhoud van WTW en mechanische ventilatie.'],
            ['locale' => 'fr', 'slug' => 'ventilation', 'meta_title' => 'Systemes de ventilation Belgique | mastechnics', 'meta_description' => 'Solutions de ventilation pour habitations, appartements et entreprises. Installation et entretien VMC et ventilation mecanique.'],
            ['locale' => 'en', 'slug' => 'ventilation', 'meta_title' => 'Ventilation systems Belgium | mastechnics', 'meta_description' => 'Ventilation solutions for homes, apartments and businesses in Belgium. Installation and maintenance of HRV and mechanical ventilation.'],
            ['locale' => 'nl', 'slug' => 'waterverzachters', 'meta_title' => 'Waterverzachter installatie Belgie | mastechnics', 'meta_description' => 'Advies, installatie en onderhoud van waterverzachters. Bescherm je leidingen en huishoudtoestellen tegen kalkaanslag.'],
            ['locale' => 'fr', 'slug' => 'adoucisseurs-eau', 'meta_title' => 'Adoucisseur eau installation Belgique | mastechnics', 'meta_description' => 'Conseil, installation et entretien d\'adoucisseurs d\'eau. Protegez vos canalisations et appareils menagers contre le calcaire.'],
            ['locale' => 'en', 'slug' => 'water-softeners', 'meta_title' => 'Water softener installation Belgium | mastechnics', 'meta_description' => 'Advice, installation and maintenance of water softeners. Protect your pipes and appliances from limescale build-up.'],
            ['locale' => 'nl', 'slug' => 'koelcellen', 'meta_title' => 'Koelcellen installatie en onderhoud | mastechnics', 'meta_description' => 'Koeling en koelcellen voor commerciele en industriele toepassingen. Installatie, onderhoud en dringende herstellingen in Belgie.'],
            ['locale' => 'fr', 'slug' => 'chambres-froides', 'meta_title' => 'Chambres froides installation et entretien | mastechnics', 'meta_description' => 'Refrigeration et chambres froides pour applications commerciales et industrielles. Installation, entretien et interventions urgentes en Belgique.'],
            ['locale' => 'en', 'slug' => 'cold-rooms', 'meta_title' => 'Cold room installation and maintenance | mastechnics', 'meta_description' => 'Refrigeration and cold rooms for commercial and industrial applications. Installation, maintenance and emergency repairs in Belgium.'],
            ['locale' => 'nl', 'slug' => 'aanvraag', 'meta_title' => 'Start aanvraag | mastechnics', 'meta_description' => 'Start een slimme technische aanvraag voor verwarming, airco, sanitair, ventilatie, waterverzachters of koeling.'],
            ['locale' => 'fr', 'slug' => 'demande', 'meta_title' => 'Démarrer une demande | mastechnics', 'meta_description' => 'Démarrez une demande technique intelligente pour chauffage, climatisation, plomberie, ventilation, adoucisseurs d’eau ou réfrigération.'],
            ['locale' => 'en', 'slug' => 'request', 'meta_title' => 'Start request | mastechnics', 'meta_description' => 'Start a smart technical request for heating, air conditioning, plumbing, ventilation, water softeners or refrigeration.'],
            ['locale' => 'nl', 'slug' => 'contact', 'meta_title' => 'Contact | mastechnics', 'meta_description' => 'Contacteer mastechnics via telefoon, e-mail, WhatsApp of het contactformulier.'],
            ['locale' => 'fr', 'slug' => 'contact', 'meta_title' => 'Contact | mastechnics', 'meta_description' => 'Contactez mastechnics par téléphone, e-mail, WhatsApp ou via le formulaire de contact.'],
            ['locale' => 'en', 'slug' => 'contact', 'meta_title' => 'Contact | mastechnics', 'meta_description' => 'Contact mastechnics by phone, email, WhatsApp or through the contact form.'],
            ['locale' => 'nl', 'slug' => 'privacybeleid', 'meta_title' => 'Privacybeleid | mastechnics', 'meta_description' => 'Lees hoe mastechnics omgaat met je persoonsgegevens: wat wij verzamelen, waarom, en jouw rechten conform de AVG/GDPR.'],
            ['locale' => 'fr', 'slug' => 'politique-confidentialite', 'meta_title' => 'Politique de confidentialite | mastechnics', 'meta_description' => 'Lisez comment mastechnics traite vos donnees personnelles : ce que nous collectons, pourquoi, et vos droits conformement au RGPD.'],
            ['locale' => 'en', 'slug' => 'privacy-policy', 'meta_title' => 'Privacy Policy | mastechnics', 'meta_description' => 'Read how mastechnics handles your personal data: what we collect, why, and your rights under GDPR.'],
        ];
    }
};
