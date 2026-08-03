<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $this->createHomePage();
        $this->createRequestPage();
        $this->createContactPage();
        $this->createServicePages();
    }

    private function createHomePage(): void
    {
        $home = Page::create([
            'code' => 'home',
            'type' => 'home',
            'is_active' => true,
        ]);

        $home->translations()->createMany([
            [
                'locale' => 'nl',
                'slug' => 'home',
                'title' => 'Technische service voor verwarming, airco, sanitair en koeling',
                'intro' => 'Start een slimme aanvraag voor herstelling, onderhoud of installatie. Vul meteen de juiste informatie in en ontvang sneller een richtprijs of duidelijke vervolgstap.',
                'content' => null,
                'meta_title' => 'Verwarming, airco & sanitair – Druivenstreek | Mastechnics',
                'meta_description' => 'Erkende service voor verwarming, airco, sanitair, ventilatie en koeling in Tervuren, Overijse en de Druivenstreek. Vraag online een offerte aan.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'accueil',
                'title' => 'Service technique pour chauffage, climatisation, plomberie et réfrigération',
                'intro' => 'Démarrez une demande intelligente pour une réparation, un entretien ou une installation. Ajoutez directement les bonnes informations et recevez plus rapidement une estimation ou une prochaine étape claire.',
                'content' => null,
                'meta_title' => 'Chauffage, clim & sanitaire – Druivenstreek | Mastechnics',
                'meta_description' => 'Service technique agréé : chauffage, climatisation, sanitaire, ventilation et froid à Tervuren, Overijse et dans le Druivenstreek. Devis en ligne.',
            ],
            [
                'locale' => 'en',
                'slug' => 'home',
                'title' => 'Technical service for heating, air conditioning, plumbing and refrigeration',
                'intro' => 'Start a smart request for repair, maintenance or installation. Add the right information from the start and receive a faster estimate or clear next step.',
                'content' => null,
                'meta_title' => 'Heating, aircon & plumbing – Druivenstreek | Mastechnics',
                'meta_description' => 'Certified service for heating, air conditioning, plumbing, ventilation and refrigeration in Tervuren, Overijse and the Druivenstreek. Quote online.',
            ],
        ]);
    }

    private function createRequestPage(): void
    {
        $request = Page::create([
            'code' => 'request',
            'type' => 'request',
            'is_active' => true,
        ]);

        $request->translations()->createMany([
            [
                'locale' => 'nl',
                'slug' => 'aanvraag',
                'title' => 'Start je technische aanvraag',
                'intro' => 'Vul stap voor stap de juiste informatie in over je installatie, probleem of project. Zo kan Mastechnics sneller inschatten wat nodig is en indien mogelijk een richtprijs of duidelijke vervolgstap voorstellen.',
                'content' => null,
                'meta_title' => 'Offerte of interventie aanvragen | Mastechnics',
                'meta_description' => 'Dien stap voor stap uw technische aanvraag in voor verwarming, airco, sanitair, ventilatie of koeling. Met de juiste info krijgt u sneller een antwoord.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'demande',
                'title' => 'Démarrer une demande technique',
                'intro' => 'Remplissez étape par étape les bonnes informations concernant votre installation, problème ou projet. Mastechnics peut ainsi estimer plus rapidement ce qui est nécessaire et proposer une estimation ou une prochaine étape claire.',
                'content' => null,
                'meta_title' => 'Demander un devis ou une intervention | Mastechnics',
                'meta_description' => 'Introduisez votre demande technique étape par étape : chauffage, climatisation, sanitaire, ventilation ou froid. Les bonnes infos accélèrent la réponse.',
            ],
            [
                'locale' => 'en',
                'slug' => 'request',
                'title' => 'Start your technical request',
                'intro' => 'Fill in the right information step by step about your installation, issue or project. This helps Mastechnics estimate what is needed faster and provide an estimate or clear next step when possible.',
                'content' => null,
                'meta_title' => 'Request a quote or call-out | Mastechnics',
                'meta_description' => 'Submit your technical request step by step for heating, air conditioning, plumbing, ventilation or refrigeration. Good information means a faster reply.',
            ],
        ]);
    }

    private function createContactPage(): void
    {
        $contact = Page::create([
            'code' => 'contact',
            'type' => 'contact',
            'is_active' => true,
        ]);

        $contact->translations()->createMany([
            [
                'locale' => 'nl',
                'slug' => 'contact',
                'title' => 'Contacteer Mastechnics',
                'intro' => 'Heb je een algemene vraag of wil je snel contact opnemen? Gebruik het contactformulier of bereik ons rechtstreeks via telefoon, e-mail of WhatsApp.',
                'content' => null,
                'meta_title' => 'Contact | Telefoon, e-mail & WhatsApp – Mastechnics',
                'meta_description' => 'Contacteer Mastechnics via telefoon, e-mail, WhatsApp of het contactformulier. Werkgebied: Tervuren, Overijse, Hoeilaart, Huldenberg en omgeving.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'contact',
                'title' => 'Contacter Mastechnics',
                'intro' => 'Vous avez une question générale ou souhaitez nous contacter rapidement ? Utilisez le formulaire de contact ou contactez-nous directement par téléphone, e-mail ou WhatsApp.',
                'content' => null,
                'meta_title' => 'Contact | Téléphone, e-mail & WhatsApp – Mastechnics',
                'meta_description' => 'Contactez Mastechnics par téléphone, e-mail, WhatsApp ou via le formulaire. Zone : Tervuren, Overijse, Hoeilaart, Huldenberg et environs.',
            ],
            [
                'locale' => 'en',
                'slug' => 'contact',
                'title' => 'Contact Mastechnics',
                'intro' => 'Do you have a general question or want to get in touch quickly? Use the contact form or contact us directly by phone, email or WhatsApp.',
                'content' => null,
                'meta_title' => 'Contact | Phone, email & WhatsApp – Mastechnics',
                'meta_description' => 'Contact Mastechnics by phone, email, WhatsApp or the contact form. Service area: Tervuren, Overijse, Hoeilaart, Huldenberg and surroundings.',
            ],
        ]);
    }

    private function createServicePages(): void
    {
        $services = config('services');

        foreach ($services as $serviceCode => $service) {
            if (!($service['is_active'] ?? false)) {
                continue;
            }

            $page = Page::create([
                'code' => $serviceCode,
                'type' => 'service',
                'is_active' => true,
            ]);

            $translations = [];

            foreach ($service['translations'] as $locale => $translation) {
                $meta = $this->serviceMeta()[$serviceCode][$locale];

                $translations[] = [
                    'locale' => $locale,
                    'slug' => $translation['slug'],
                    'title' => $translation['title'],
                    'intro' => $translation['description'],
                    'content' => $this->getServiceContent($locale, $translation['title']),
                    'meta_title' => $meta['meta_title'],
                    'meta_description' => $meta['meta_description'],
                ];
            }

            $page->translations()->createMany($translations);
        }
    }

    /**
     * Canonical service-page meta.
     *
     * This used to be derived as "{$title} | mastechnics", which produced
     * identical titles for the French and English ventilation pages (both are
     * called "Ventilation") and gave every page the same generic, region-less
     * description. Keeping the real values here means a freshly seeded install
     * matches the live site instead of drifting back to the old strings.
     *
     * @return array<string, array<string, array{meta_title: string, meta_description: string}>>
     */
    private function serviceMeta(): array
    {
        return [
            'heating' => [
                'nl' => ['meta_title' => 'Verwarmingsservice Tervuren & Druivenstreek | Mastechnics', 'meta_description' => 'Onderhoud, herstelling en installatie van gasketel, condensatieketel of warmtepomp in Tervuren, Overijse en omgeving. Erkende gastechnicus.'],
                'fr' => ['meta_title' => 'Service chauffage Tervuren & Druivenstreek | Mastechnics', 'meta_description' => 'Entretien, réparation et installation de chaudière gaz, condensation ou pompe à chaleur à Tervuren, Overijse et environs. Technicien gaz agréé.'],
                'en' => ['meta_title' => 'Heating service Tervuren & Druivenstreek | Mastechnics', 'meta_description' => 'Maintenance, repair and installation of gas boilers, condensing boilers and heat pumps in Tervuren, Overijse and nearby. Certified gas technician.'],
            ],
            'airco' => [
                'nl' => ['meta_title' => 'Airco plaatsen & onderhoud Druivenstreek | Mastechnics', 'meta_description' => 'Plaatsing, onderhoud en herstelling van split-unit en multi-split airco in Tervuren, Overijse en Hoeilaart. Erkend F-gas installateur.'],
                'fr' => ['meta_title' => 'Climatisation : pose & entretien | Mastechnics', 'meta_description' => 'Installation, entretien et réparation de climatisation split et multi-split à Tervuren, Overijse et Hoeilaart. Installateur F-gaz certifié.'],
                'en' => ['meta_title' => 'Aircon install & service, Druivenstreek | Mastechnics', 'meta_description' => 'Installation, maintenance and repair of split and multi-split air conditioning in Tervuren, Overijse and Hoeilaart. Certified F-gas installer.'],
            ],
            'plumbing' => [
                'nl' => ['meta_title' => 'Loodgieter & sanitair Druivenstreek | Mastechnics', 'meta_description' => 'Waterlek, verstopping, kraan of volledige badkamer in Tervuren, Overijse, Hoeilaart en Huldenberg. Snelle interventie bij waterschade.'],
                'fr' => ['meta_title' => 'Plombier & sanitaire Druivenstreek | Mastechnics', 'meta_description' => 'Fuite, bouchon, robinetterie ou salle de bain complète à Tervuren, Overijse, Hoeilaart et Huldenberg. Intervention rapide en cas de dégâts des eaux.'],
                'en' => ['meta_title' => 'Plumber & bathrooms, Druivenstreek | Mastechnics', 'meta_description' => 'Leaks, blockages, taps or a complete bathroom in Tervuren, Overijse, Hoeilaart and Huldenberg. Fast response to water damage.'],
            ],
            'ventilation' => [
                'nl' => ['meta_title' => 'Ventilatie type C & D Druivenstreek | Mastechnics', 'meta_description' => 'Plaatsing, onderhoud en debietmeting van ventilatie type C en D voor woning en bedrijf in Tervuren, Bertem en omgeving. EPB-conform.'],
                'fr' => ['meta_title' => 'Ventilation type C & D Druivenstreek | Mastechnics', 'meta_description' => 'Installation, entretien et mesure de débit de ventilation type C et D pour habitations et entreprises à Tervuren, Bertem et environs. Conforme PEB.'],
                'en' => ['meta_title' => 'Ventilation type C & D, Druivenstreek | Mastechnics', 'meta_description' => 'Installation, servicing and airflow measurement of type C and D ventilation for homes and businesses in Tervuren, Bertem and nearby. EPB compliant.'],
            ],
            'water-softeners' => [
                'nl' => ['meta_title' => 'Waterverzachter plaatsen Druivenstreek | Mastechnics', 'meta_description' => 'Advies, plaatsing en onderhoud van waterverzachters in Tervuren, Overijse en Huldenberg. Het leidingwater is hier hard — kalk aanpakken bij de bron.'],
                'fr' => ['meta_title' => 'Adoucisseur d\'eau Druivenstreek | Mastechnics', 'meta_description' => 'Conseil, installation et entretien d\'adoucisseurs à Tervuren, Overijse et Huldenberg. L\'eau y est dure : traitez le calcaire à la source.'],
                'en' => ['meta_title' => 'Water softener installation | Mastechnics', 'meta_description' => 'Advice, installation and servicing of water softeners in Tervuren, Overijse and Huldenberg. Tap water here is hard — tackle limescale at the source.'],
            ],
            'cold-rooms' => [
                'nl' => ['meta_title' => 'Koelcellen: installatie & onderhoud | Mastechnics', 'meta_description' => 'Koelcellen en koelinstallaties voor horeca, voeding en industrie in Vlaams-Brabant. Installatie, onderhoud, F-gas lekcontrole en herstelling.'],
                'fr' => ['meta_title' => 'Chambres froides : pose & entretien | Mastechnics', 'meta_description' => 'Chambres froides et installations frigorifiques pour horeca, alimentation et industrie en Brabant flamand. Pose, entretien et contrôle F-gaz.'],
                'en' => ['meta_title' => 'Cold rooms: installation & service | Mastechnics', 'meta_description' => 'Cold rooms and refrigeration for catering, food and industry in Flemish Brabant. Installation, maintenance, F-gas leak checks and repair.'],
            ],
        ];
    }

    private function getServiceContent(string $locale, string $serviceTitle): string
    {
        return match ($locale) {
            'fr' => "Nous vous aidons avec les demandes liées à {$serviceTitle}. Grâce à une prise d’informations structurée, nous pouvons mieux comprendre votre situation et proposer une prochaine étape claire.",
            'en' => "We help with requests related to {$serviceTitle}. With a structured intake, we can better understand your situation and suggest a clear next step.",
            default => "Wij helpen met aanvragen rond {$serviceTitle}. Door de informatie gestructureerd te verzamelen, kunnen we de situatie sneller begrijpen en een duidelijke volgende stap voorstellen.",
        };
    }
}