# Sprint 2: Smart Request Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two-step service+type selection with a single unified "Waarvoor wenst u een aanvraag te doen?" flow that shows conditional questions per service category, stores new workflow fields, and makes urgent requests visually distinct in the admin.

**Architecture:** The new Step 1 is a `service_category_selection` that replaces the old service_slug + request_type radio groups. The controller maps the selected `service_category` to a `service_slug` and `request_type` for backward compatibility. Four conditional step blocks handle per-service questions. All existing general steps (customer_context, description, technical_details, location, contact, summary) are kept unchanged except for renumbered CSS breakpoints.

**Tech Stack:** Laravel 12, Blade, vanilla JS in `request-form.js` and inline in `request-page.blade.php`, plain CSS (no build step beyond Vite).

---

## File Map

| File | Action | Responsibility |
|------|--------|----------------|
| `database/migrations/2026_05_29_225828_add_workflow_fields_to_customer_requests_table.php` | Modify | Remove duplicate `request_type` column that already exists |
| `app/Models/CustomerRequest.php` | Modify | Remove duplicate fillable, add `ai_detected_missing_fields` cast |
| `config/request-flow.php` | Rewrite | New step 1, service_categories map, 4 conditional flow steps |
| `resources/views/pages/partials/request-page.blade.php` | Modify | Handle new step types, urgent_warning, render_upload flag, updated JS |
| `app/Http/Controllers/CustomerRequestController.php` | Modify | Validate service_category, map to slug/type, store new fields, fix conditional field validation |
| `resources/views/admin/requests/index.blade.php` | Modify | Show service_category label and urgency_level badge |
| `resources/views/admin/requests/show.blade.php` | Modify | Show service_category, urgency_level, preferred_time, customer_message |
| `resources/css/pages/request.css` | Modify | Add `.urgent-warning-box` styles, fix step-index CSS selectors |
| `resources/css/pages/admin.css` | Modify | Add CSS for new urgency_level values |

### New step indices (data-step attribute)

Old step → New index:
- `service_category_selection` → **0** (new)
- `cv_onderhoud_details` → **1** (new, conditional)
- `lek_dringend_details` → **2** (new, conditional)
- `airco_offerte_details` → **3** (new, conditional)
- `airco_onderhoud_details` → **4** (new, conditional)
- `customer_context` → **5** (was 2)
- `description` → **6** (was 3)
- `technical_details` → **7** (was 5)
- `location_availability` → **8** (was 6)
- `contact_details` → **9** (was 7)
- `summary` → **10** (was 8)

---

## Task 1: Fix the migration — remove duplicate `request_type` column

**Files:**
- Modify: `database/migrations/2026_05_29_225828_add_workflow_fields_to_customer_requests_table.php`

The existing migration tries to add `request_type` which already exists in `create_customer_requests_table`. This will fail with a duplicate column error.

- [ ] **Step 1: Read current migration to confirm the problem**

Open `database/migrations/2026_05_29_225828_add_workflow_fields_to_customer_requests_table.php`. Confirm that it calls `$table->string('request_type')` in `up()` and includes `'request_type'` in the `dropColumn` array in `down()`.

- [ ] **Step 2: Remove `request_type` from both up() and down()**

Replace the full file with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_requests', function (Blueprint $table) {
            $table->string('source')->default('website')->after('id');
            $table->string('service_category')->nullable()->after('source');
            $table->string('urgency_level')->nullable()->after('service_category');
            $table->string('preferred_time')->nullable()->after('urgency_level');
            $table->text('customer_message')->nullable()->after('preferred_time');
            $table->text('ai_summary')->nullable()->after('customer_message');
            $table->json('ai_detected_missing_fields')->nullable()->after('ai_summary');
        });
    }

    public function down(): void
    {
        Schema::table('customer_requests', function (Blueprint $table) {
            $table->dropColumn([
                'source',
                'service_category',
                'urgency_level',
                'preferred_time',
                'customer_message',
                'ai_summary',
                'ai_detected_missing_fields',
            ]);
        });
    }
};
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_29_225828_add_workflow_fields_to_customer_requests_table.php
git commit -m "fix: remove duplicate request_type from workflow fields migration"
```

---

## Task 2: Fix the CustomerRequest model

**Files:**
- Modify: `app/Models/CustomerRequest.php`

Two issues: `request_type` is listed twice in `$fillable`, and `ai_detected_missing_fields` is not in `$casts`.

- [ ] **Step 1: Update the model**

Replace the full file with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerRequest extends Model
{
    protected $fillable = [
        'locale',
        'service_slug',
        'request_type',
        'customer_name',
        'customer_email',
        'customer_phone',
        'description',
        'brand',
        'device_model',
        'serial_number',
        'unknown_device_details',
        'metadata',
        'status',
        'source',
        'service_category',
        'urgency_level',
        'preferred_time',
        'customer_message',
        'ai_summary',
        'ai_detected_missing_fields',
    ];

    protected $casts = [
        'metadata' => 'array',
        'unknown_device_details' => 'boolean',
        'ai_detected_missing_fields' => 'array',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(CustomerRequestAttachment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerRequestNote::class)->latest();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/CustomerRequest.php
git commit -m "fix: remove duplicate fillable field and add ai_detected_missing_fields cast"
```

---

## Task 3: Rework config/request-flow.php

**Files:**
- Rewrite: `config/request-flow.php`

The first step changes from `service_selection` to `service_category_selection`. The old `request_type_selection` step is removed. Four new conditional flow steps are added. A new `service_categories` top-level key maps each category value to a `service_key` (matching keys in `config/services.php`) and a `request_type` for backward compat storage. The existing `airco_project_details` step is replaced by the new `airco_offerte_details` conditional step.

- [ ] **Step 1: Write the new config**

Replace the entire file:

```php
<?php

return [

    /*
     * Maps service_category values to the service key in config/services.php
     * and a request_type for backward-compatible storage.
     */
    'service_categories' => [
        ['value' => 'airco_offerte',  'service_key' => 'airco',          'request_type' => 'installation'],
        ['value' => 'airco_onderhoud','service_key' => 'airco',          'request_type' => 'maintenance'],
        ['value' => 'onderhoud_cv',   'service_key' => 'heating',        'request_type' => 'maintenance'],
        ['value' => 'herstelling_cv', 'service_key' => 'heating',        'request_type' => 'repair'],
        ['value' => 'dringend_lek',   'service_key' => 'plumbing',       'request_type' => 'repair'],
        ['value' => 'sanitair',       'service_key' => 'plumbing',       'request_type' => 'repair'],
        ['value' => 'ventilatie',     'service_key' => 'ventilation',    'request_type' => 'new_project'],
        ['value' => 'waterverzachter','service_key' => 'water-softeners','request_type' => 'installation'],
        ['value' => 'koeling',        'service_key' => 'cold-rooms',     'request_type' => 'installation'],
        ['value' => 'andere',         'service_key' => 'heating',        'request_type' => 'repair'],
    ],

    'steps' => [

        // Step 0 — Unified service+type selection (replaces old steps 1 & 2)
        [
            'code' => 'service_category',
            'labels' => [
                'nl' => '1. Waarvoor wenst u een aanvraag te doen?',
                'fr' => '1. Pour quoi souhaitez-vous une demande ?',
                'en' => '1. What would you like to request?',
            ],
            'type' => 'service_category_selection',
            'options' => [
                ['value' => 'airco_offerte',   'labels' => ['nl' => 'Airco offerte',                  'fr' => 'Offre climatisation',         'en' => 'Air conditioning quote']],
                ['value' => 'airco_onderhoud', 'labels' => ['nl' => 'Airco onderhoud',                'fr' => 'Entretien climatisation',     'en' => 'Air conditioning maintenance']],
                ['value' => 'onderhoud_cv',    'labels' => ['nl' => 'Onderhoud centrale verwarming',  'fr' => 'Entretien chauffage central', 'en' => 'Central heating maintenance']],
                ['value' => 'herstelling_cv',  'labels' => ['nl' => 'Herstelling centrale verwarming','fr' => 'Réparation chauffage central','en' => 'Central heating repair']],
                ['value' => 'dringend_lek',    'labels' => ['nl' => 'Lek of dringend probleem',       'fr' => 'Fuite ou problème urgent',    'en' => 'Leak or urgent issue']],
                ['value' => 'sanitair',        'labels' => ['nl' => 'Sanitair probleem',              'fr' => 'Problème sanitaire',          'en' => 'Plumbing issue']],
                ['value' => 'ventilatie',      'labels' => ['nl' => 'Ventilatie',                     'fr' => 'Ventilation',                 'en' => 'Ventilation']],
                ['value' => 'waterverzachter', 'labels' => ['nl' => 'Waterverzachter',                'fr' => 'Adoucisseur d\'eau',          'en' => 'Water softener']],
                ['value' => 'koeling',         'labels' => ['nl' => 'Koeling / koelcel',              'fr' => 'Réfrigération / chambre froide','en' => 'Cooling / cold room']],
                ['value' => 'andere',          'labels' => ['nl' => 'Andere',                         'fr' => 'Autre',                       'en' => 'Other']],
            ],
        ],

        // Step 1 — Onderhoud centrale verwarming (conditional)
        [
            'code' => 'cv_onderhoud_details',
            'labels' => [
                'nl' => '2. Centrale verwarming onderhoud',
                'fr' => '2. Entretien chauffage central',
                'en' => '2. Central heating maintenance',
            ],
            'type' => 'fields',
            'condition' => ['service_categories' => ['onderhoud_cv']],
            'fields' => [
                [
                    'name' => 'cv_installation_type',
                    'type' => 'select',
                    'required' => false,
                    'labels' => ['nl' => 'Type installatie', 'fr' => 'Type d\'installation', 'en' => 'Installation type'],
                    'options' => [
                        ['value' => 'gasketel',             'labels' => ['nl' => 'Gasketel',                  'fr' => 'Chaudière gaz',              'en' => 'Gas boiler']],
                        ['value' => 'gascondensatieketel',  'labels' => ['nl' => 'Gascondensatieketel',       'fr' => 'Chaudière à condensation gaz','en' => 'Gas condensing boiler']],
                        ['value' => 'stookolieketel',       'labels' => ['nl' => 'Stookolieketel',            'fr' => 'Chaudière mazout',           'en' => 'Oil boiler']],
                        ['value' => 'warmtepomp',           'labels' => ['nl' => 'Warmtepomp',                'fr' => 'Pompe à chaleur',            'en' => 'Heat pump']],
                        ['value' => 'unknown',              'labels' => ['nl' => 'Ik weet het niet',          'fr' => 'Je ne sais pas',             'en' => 'I don\'t know']],
                    ],
                ],
                [
                    'name' => 'brand',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Merk ketel', 'fr' => 'Marque chaudière', 'en' => 'Boiler brand'],
                    'placeholder' => ['nl' => 'Vaillant, Buderus, Viessmann...', 'fr' => 'Vaillant, Buderus, Viessmann...', 'en' => 'Vaillant, Buderus, Viessmann...'],
                ],
                [
                    'name' => 'device_model',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Model ketel', 'fr' => 'Modèle chaudière', 'en' => 'Boiler model'],
                    'placeholder' => ['nl' => 'ecoTEC plus, Logamax...', 'fr' => 'ecoTEC plus, Logamax...', 'en' => 'ecoTEC plus, Logamax...'],
                ],
                [
                    'name' => 'cv_last_maintenance',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Laatste onderhoud (indien gekend)', 'fr' => 'Dernier entretien (si connu)', 'en' => 'Last maintenance (if known)'],
                    'placeholder' => ['nl' => 'Bijv. 2022, 3 jaar geleden...', 'fr' => 'Ex. 2022, il y a 3 ans...', 'en' => 'E.g. 2022, 3 years ago...'],
                ],
                [
                    'name' => 'preferred_time',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Gewenst moment', 'fr' => 'Moment préféré', 'en' => 'Preferred timing'],
                    'placeholder' => ['nl' => 'Bijv. liefst voormiddag, niet op maandag...', 'fr' => 'Ex. de préférence le matin, pas le lundi...', 'en' => 'E.g. preferably morning, not on Monday...'],
                ],
            ],
            'helper_box' => [
                'render_upload' => false,
                'title' => [
                    'nl' => 'Foto typeplaatje uploaden',
                    'fr' => 'Uploader la plaque signalétique',
                    'en' => 'Upload nameplate photo',
                ],
                'text' => [
                    'nl' => 'U vindt dit meestal op of in de ketel, vaak achter het klepje of aan de zijkant. Zorg dat merk, model en serienummer leesbaar zijn. Upload de foto in de volgende stap.',
                    'fr' => 'Vous la trouvez généralement sur ou dans la chaudière, souvent derrière le volet ou sur le côté. Assurez-vous que la marque, le modèle et le numéro de série sont lisibles. Chargez la photo à l\'étape suivante.',
                    'en' => 'You usually find this on or in the boiler, often behind the flap or on the side. Make sure the brand, model and serial number are readable. Upload the photo in the next step.',
                ],
            ],
        ],

        // Step 2 — Lek of dringend probleem (conditional)
        [
            'code' => 'lek_dringend_details',
            'labels' => [
                'nl' => '2. Lek of dringend probleem',
                'fr' => '2. Fuite ou problème urgent',
                'en' => '2. Leak or urgent issue',
            ],
            'type' => 'fields',
            'condition' => ['service_categories' => ['dringend_lek']],
            'urgent_warning' => [
                'nl' => 'Bij ernstige wateroverlast of gevaar: bel ons direct. Dit formulier is voor niet-levensbedreigende situaties.',
                'fr' => 'En cas de dégâts des eaux graves ou de danger : appelez-nous directement. Ce formulaire est pour les situations non dangereuses.',
                'en' => 'In case of serious water damage or danger: call us directly. This form is for non-life-threatening situations.',
            ],
            'fields' => [
                [
                    'name' => 'lek_location',
                    'type' => 'select',
                    'required' => false,
                    'labels' => ['nl' => 'Waar zit het probleem?', 'fr' => 'Où est le problème ?', 'en' => 'Where is the problem?'],
                    'options' => [
                        ['value' => 'centrale_verwarming', 'labels' => ['nl' => 'Centrale verwarming', 'fr' => 'Chauffage central', 'en' => 'Central heating']],
                        ['value' => 'sanitair',            'labels' => ['nl' => 'Sanitair',            'fr' => 'Sanitaire',        'en' => 'Plumbing']],
                        ['value' => 'boiler',              'labels' => ['nl' => 'Boiler',              'fr' => 'Boiler',           'en' => 'Boiler']],
                        ['value' => 'leidingen',           'labels' => ['nl' => 'Leidingen',           'fr' => 'Canalisations',    'en' => 'Pipes']],
                        ['value' => 'unknown',             'labels' => ['nl' => 'Ik weet het niet',    'fr' => 'Je ne sais pas',   'en' => 'I don\'t know']],
                    ],
                ],
                [
                    'name' => 'urgency_level',
                    'type' => 'select',
                    'required' => false,
                    'labels' => ['nl' => 'Urgentie', 'fr' => 'Urgence', 'en' => 'Urgency'],
                    'options' => [
                        ['value' => 'water_leaking', 'labels' => ['nl' => 'Er staat water / ernstig lek', 'fr' => 'Il y a de l\'eau / fuite grave', 'en' => 'Water present / serious leak']],
                        ['value' => 'small_leak',    'labels' => ['nl' => 'Klein lek',                   'fr' => 'Petite fuite',                  'en' => 'Small leak']],
                        ['value' => 'no_heating',    'labels' => ['nl' => 'Geen verwarming',             'fr' => 'Pas de chauffage',              'en' => 'No heating']],
                        ['value' => 'no_hot_water',  'labels' => ['nl' => 'Geen warm water',             'fr' => 'Pas d\'eau chaude',             'en' => 'No hot water']],
                        ['value' => 'other',         'labels' => ['nl' => 'Andere',                      'fr' => 'Autre',                         'en' => 'Other']],
                    ],
                ],
                [
                    'name' => 'preferred_time',
                    'type' => 'textarea',
                    'required' => false,
                    'labels' => ['nl' => 'Wanneer bereikbaar?', 'fr' => 'Quand disponible ?', 'en' => 'When available?'],
                    'placeholder' => ['nl' => 'Bijv. elke dag na 17u, of op zaterdag...', 'fr' => 'Ex. chaque jour après 17h, ou le samedi...', 'en' => 'E.g. every day after 5pm, or Saturday...'],
                ],
            ],
            'helper_box' => [
                'render_upload' => false,
                'title' => [
                    'nl' => 'Foto of video toevoegen',
                    'fr' => 'Ajouter une photo ou vidéo',
                    'en' => 'Add photo or video',
                ],
                'text' => [
                    'nl' => 'Voeg indien mogelijk een foto of video toe van het lek of het probleem. Dit helpt ons de ernst sneller inschatten. Upload in de stap "Probleem of project" hieronder.',
                    'fr' => 'Ajoutez si possible une photo ou vidéo de la fuite ou du problème. Cela nous aide à évaluer la gravité plus rapidement. Chargez-la à l\'étape "Problème ou projet" ci-dessous.',
                    'en' => 'If possible, add a photo or video of the leak or problem. This helps us assess the severity faster. Upload in the "Issue or project" step below.',
                ],
            ],
        ],

        // Step 3 — Airco offerte (conditional)
        [
            'code' => 'airco_offerte_details',
            'labels' => [
                'nl' => '2. Airco offerte details',
                'fr' => '2. Détails offre climatisation',
                'en' => '2. Air conditioning quote details',
            ],
            'type' => 'fields',
            'condition' => ['service_categories' => ['airco_offerte']],
            'fields' => [
                [
                    'name' => 'airco_rooms_count',
                    'type' => 'number',
                    'required' => false,
                    'labels' => ['nl' => 'Aantal ruimtes', 'fr' => 'Nombre de pièces', 'en' => 'Number of rooms'],
                    'placeholder' => ['nl' => 'Bijv. 2', 'fr' => 'Ex. 2', 'en' => 'E.g. 2'],
                ],
                [
                    'name' => 'airco_room_types',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Type ruimtes', 'fr' => 'Type de pièces', 'en' => 'Room types'],
                    'placeholder' => ['nl' => 'Slaapkamer, living, bureau...', 'fr' => 'Chambre, salon, bureau...', 'en' => 'Bedroom, living room, office...'],
                ],
                [
                    'name' => 'airco_has_outdoor_unit',
                    'type' => 'select',
                    'required' => false,
                    'labels' => ['nl' => 'Is er al een buitenunit?', 'fr' => 'Y a-t-il déjà une unité extérieure ?', 'en' => 'Is there already an outdoor unit?'],
                    'options' => [
                        ['value' => 'yes',     'labels' => ['nl' => 'Ja',              'fr' => 'Oui',         'en' => 'Yes']],
                        ['value' => 'no',      'labels' => ['nl' => 'Nee',             'fr' => 'Non',         'en' => 'No']],
                        ['value' => 'unknown', 'labels' => ['nl' => 'Ik weet het niet','fr' => 'Je ne sais pas','en' => 'I don\'t know']],
                    ],
                ],
                [
                    'name' => 'preferred_time',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Gewenste timing', 'fr' => 'Timing souhaité', 'en' => 'Desired timing'],
                    'placeholder' => ['nl' => 'Bijv. voor de zomer, zo snel mogelijk...', 'fr' => 'Ex. avant l\'été, dès que possible...', 'en' => 'E.g. before summer, as soon as possible...'],
                ],
            ],
            'helper_box' => [
                'render_upload' => false,
                'title' => [
                    'nl' => 'Foto\'s toevoegen (optioneel)',
                    'fr' => 'Ajouter des photos (facultatif)',
                    'en' => 'Add photos (optional)',
                ],
                'text' => [
                    'nl' => 'Voeg indien nuttig foto\'s toe van de ruimtes of de geplande locatie voor de buitenunit. Upload via de stap "Probleem of project" hieronder.',
                    'fr' => 'Ajoutez si utile des photos des pièces ou de l\'emplacement prévu pour l\'unité extérieure. Chargez via l\'étape "Problème ou projet" ci-dessous.',
                    'en' => 'If useful, add photos of the rooms or the planned outdoor unit location. Upload via the "Issue or project" step below.',
                ],
            ],
        ],

        // Step 4 — Airco onderhoud (conditional)
        [
            'code' => 'airco_onderhoud_details',
            'labels' => [
                'nl' => '2. Airco onderhoud details',
                'fr' => '2. Détails entretien climatisation',
                'en' => '2. Air conditioning maintenance details',
            ],
            'type' => 'fields',
            'condition' => ['service_categories' => ['airco_onderhoud']],
            'fields' => [
                [
                    'name' => 'brand',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Merk airco', 'fr' => 'Marque climatisation', 'en' => 'Air conditioning brand'],
                    'placeholder' => ['nl' => 'Daikin, Mitsubishi, Samsung...', 'fr' => 'Daikin, Mitsubishi, Samsung...', 'en' => 'Daikin, Mitsubishi, Samsung...'],
                ],
                [
                    'name' => 'airco_indoor_units_count',
                    'type' => 'number',
                    'required' => false,
                    'labels' => ['nl' => 'Aantal binnenunits', 'fr' => 'Nombre d\'unités intérieures', 'en' => 'Number of indoor units'],
                    'placeholder' => ['nl' => 'Bijv. 1', 'fr' => 'Ex. 1', 'en' => 'E.g. 1'],
                ],
                [
                    'name' => 'airco_last_maintenance',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Laatste onderhoud (indien gekend)', 'fr' => 'Dernier entretien (si connu)', 'en' => 'Last maintenance (if known)'],
                    'placeholder' => ['nl' => 'Bijv. 2022, nooit gedaan...', 'fr' => 'Ex. 2022, jamais fait...', 'en' => 'E.g. 2022, never done...'],
                ],
                [
                    'name' => 'preferred_time',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Gewenst afspraakmoment', 'fr' => 'Moment de rendez-vous souhaité', 'en' => 'Preferred appointment time'],
                    'placeholder' => ['nl' => 'Bijv. liefst voormiddag, niet op woensdag...', 'fr' => 'Ex. de préférence le matin, pas le mercredi...', 'en' => 'E.g. preferably morning, not on Wednesday...'],
                ],
            ],
        ],

        // Step 5 — Klant en urgentie (unchanged)
        [
            'code' => 'customer_context',
            'labels' => [
                'nl' => '3. Klant en urgentie',
                'fr' => '3. Client et urgence',
                'en' => '3. Customer and urgency',
            ],
            'type' => 'fields',
            'fields' => [
                [
                    'name' => 'customer_type',
                    'type' => 'select',
                    'required' => true,
                    'labels' => ['nl' => 'Klanttype', 'fr' => 'Type de client', 'en' => 'Customer type'],
                    'options' => [
                        ['value' => 'residential', 'labels' => ['nl' => 'Particulier', 'fr' => 'Particulier', 'en' => 'Residential']],
                        ['value' => 'business',    'labels' => ['nl' => 'Bedrijf',      'fr' => 'Entreprise',  'en' => 'Business']],
                    ],
                ],
                [
                    'name' => 'urgency',
                    'type' => 'select',
                    'required' => true,
                    'labels' => ['nl' => 'Urgentie', 'fr' => 'Urgence', 'en' => 'Urgency'],
                    'options' => [
                        ['value' => 'urgent',      'labels' => ['nl' => 'Dringend',            'fr' => 'Urgent',           'en' => 'Urgent']],
                        ['value' => 'within_days', 'labels' => ['nl' => 'Binnen enkele dagen', 'fr' => 'Dans quelques jours','en' => 'Within a few days']],
                        ['value' => 'not_urgent',  'labels' => ['nl' => 'Niet dringend',       'fr' => 'Pas urgent',        'en' => 'Not urgent']],
                    ],
                ],
            ],
        ],

        // Step 6 — Beschrijving + upload (unchanged except render_upload default stays true)
        [
            'code' => 'description',
            'labels' => [
                'nl' => '4. Probleem of project',
                'fr' => '4. Problème ou projet',
                'en' => '4. Issue or project',
            ],
            'type' => 'fields',
            'fields' => [
                [
                    'name' => 'description',
                    'type' => 'textarea',
                    'required' => true,
                    'labels' => [
                        'nl' => 'Beschrijf kort je probleem of project',
                        'fr' => 'Décrivez brièvement votre problème ou projet',
                        'en' => 'Briefly describe your issue or project',
                    ],
                    'placeholder' => [
                        'nl' => 'Beschrijf wat er aan de hand is...',
                        'fr' => 'Décrivez la situation...',
                        'en' => 'Describe what is going on...',
                    ],
                ],
            ],
            'helper_box' => [
                'title' => [
                    'nl' => 'Foto\'s toevoegen',
                    'fr' => 'Ajouter des photos',
                    'en' => 'Add photos',
                ],
                'text' => [
                    'nl' => 'Voeg indien mogelijk foto\'s toe van het toestel, typeplaatje, foutcode of probleemzone.',
                    'fr' => 'Ajoutez si possible des photos de l\'appareil, de la plaque signalétique, du code erreur ou de la zone du problème.',
                    'en' => 'If possible, add photos of the unit, nameplate, error code or problem area.',
                ],
            ],
        ],

        // Step 7 — Technische gegevens (unchanged)
        [
            'code' => 'technical_details',
            'labels' => [
                'nl' => '5. Technische gegevens',
                'fr' => '5. Informations techniques',
                'en' => '5. Technical details',
            ],
            'type' => 'fields',
            'fields' => [
                [
                    'name' => 'brand',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Merk', 'fr' => 'Marque', 'en' => 'Brand'],
                    'placeholder' => ['nl' => 'Vaillant, Daikin, Bosch...', 'fr' => 'Vaillant, Daikin, Bosch...', 'en' => 'Vaillant, Daikin, Bosch...'],
                ],
                [
                    'name' => 'device_model',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Model', 'fr' => 'Modèle', 'en' => 'Model'],
                    'placeholder' => ['nl' => 'ecoTEC plus, Altherma...', 'fr' => 'ecoTEC plus, Altherma...', 'en' => 'ecoTEC plus, Altherma...'],
                ],
                [
                    'name' => 'serial_number',
                    'type' => 'text',
                    'required' => false,
                    'labels' => ['nl' => 'Serienummer', 'fr' => 'Numéro de série', 'en' => 'Serial number'],
                    'placeholder' => ['nl' => 'SN / serial...', 'fr' => 'SN / serial...', 'en' => 'SN / serial...'],
                ],
                [
                    'name' => 'unknown_device_details',
                    'type' => 'checkbox',
                    'required' => false,
                    'labels' => [
                        'nl' => 'Ik weet merk/model/serienummer niet',
                        'fr' => 'Je ne connais pas la marque/le modèle/le numéro de série',
                        'en' => 'I don\'t know the brand/model/serial number',
                    ],
                ],
            ],
        ],

        // Step 8 — Locatie en beschikbaarheid (unchanged)
        [
            'code' => 'location_availability',
            'labels' => [
                'nl' => '6. Locatie en beschikbaarheid',
                'fr' => '6. Lieu et disponibilité',
                'en' => '6. Location and availability',
            ],
            'type' => 'fields',
            'fields' => [
                [
                    'name' => 'street',
                    'type' => 'text',
                    'required' => true,
                    'labels' => ['nl' => 'Straat en nummer', 'fr' => 'Rue et numéro', 'en' => 'Street and number'],
                    'placeholder' => ['nl' => 'Voorbeeldstraat 12', 'fr' => 'Rue exemple 12', 'en' => 'Example street 12'],
                ],
                [
                    'name' => 'postal_code',
                    'type' => 'text',
                    'required' => true,
                    'labels' => ['nl' => 'Postcode', 'fr' => 'Code postal', 'en' => 'Postal code'],
                    'placeholder' => ['nl' => '1000', 'fr' => '1000', 'en' => '1000'],
                ],
                [
                    'name' => 'city',
                    'type' => 'text',
                    'required' => true,
                    'labels' => ['nl' => 'Gemeente', 'fr' => 'Commune', 'en' => 'City'],
                    'placeholder' => ['nl' => 'Brussel', 'fr' => 'Bruxelles', 'en' => 'Brussels'],
                ],
                [
                    'name' => 'availability',
                    'type' => 'textarea',
                    'required' => false,
                    'labels' => ['nl' => 'Beschikbaarheid of voorkeurmoment', 'fr' => 'Disponibilité ou moment préféré', 'en' => 'Availability or preferred moment'],
                    'placeholder' => ['nl' => 'Bijv. liefst in de voormiddag, niet op woensdag...', 'fr' => 'Par exemple : de préférence le matin, pas le mercredi...', 'en' => 'For example: preferably in the morning, not on Wednesday...'],
                ],
            ],
        ],

        // Step 9 — Contactgegevens (unchanged)
        [
            'code' => 'contact_details',
            'labels' => [
                'nl' => '7. Contactgegevens',
                'fr' => '7. Coordonnées',
                'en' => '7. Contact details',
            ],
            'type' => 'fields',
            'fields' => [
                [
                    'name' => 'customer_name',
                    'type' => 'text',
                    'required' => true,
                    'labels' => ['nl' => 'Naam', 'fr' => 'Nom', 'en' => 'Name'],
                ],
                [
                    'name' => 'customer_email',
                    'type' => 'email',
                    'required' => true,
                    'labels' => ['nl' => 'E-mailadres', 'fr' => 'Adresse e-mail', 'en' => 'Email address'],
                ],
                [
                    'name' => 'customer_phone',
                    'type' => 'tel',
                    'required' => false,
                    'labels' => ['nl' => 'Telefoonnummer', 'fr' => 'Numéro de téléphone', 'en' => 'Phone number'],
                ],
            ],
        ],

        // Step 10 — Samenvatting (unchanged)
        [
            'code' => 'summary',
            'labels' => [
                'nl' => '8. Samenvatting',
                'fr' => '8. Résumé',
                'en' => '8. Summary',
            ],
            'type' => 'summary',
        ],
    ],

    // Kept for the admin filter dropdown (still meaningful for stored data)
    'request_types' => [
        ['value' => 'repair',       'labels' => ['nl' => 'Herstelling', 'fr' => 'Réparation', 'en' => 'Repair']],
        ['value' => 'maintenance',  'labels' => ['nl' => 'Onderhoud',   'fr' => 'Entretien',   'en' => 'Maintenance']],
        ['value' => 'installation', 'labels' => ['nl' => 'Installatie', 'fr' => 'Installation','en' => 'Installation']],
        ['value' => 'new_project',  'labels' => ['nl' => 'Nieuw project','fr' => 'Nouveau projet','en' => 'New project']],
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add config/request-flow.php
git commit -m "feat: rework request-flow config with service_category_selection and 4 conditional flows"
```

---

## Task 4: Update request-page.blade.php

**Files:**
- Modify: `resources/views/pages/partials/request-page.blade.php`

Changes needed:
1. Update `$stepMatchesOldInput` to check `old('service_category')` against `service_categories` condition.
2. Add `$getConditionCategories` helper.
3. Update `data-condition-*` attributes on conditional elements (use `data-condition-service-categories`).
4. Render `service_category_selection` step type (option-grid with `name="service_category"`).
5. Render `urgent_warning` box before fields when step has one.
6. Handle `render_upload` flag in `helper_box` (default true, set false to skip file input).
7. Replace the inline JS `initConditionalRequestSteps` to watch `service_category` instead of `service_slug`/`request_type`.

- [ ] **Step 1: Replace the @php block at the top**

Replace lines 1–120 (the `@php` block and `$text` array) with:

```blade
@php
    $siteName = config('site.name');

    $steps = config('request-flow.steps', []);

    $getLabel = function (array $item) use ($locale): string {
        return $item['labels'][$locale]
            ?? $item['labels']['nl']
            ?? $item['label'][$locale]
            ?? $item['label']['nl']
            ?? $item['title']
            ?? $item['value']
            ?? '';
    };

    $getPlaceholder = function (array $field) use ($locale): string {
        return $field['placeholder'][$locale]
            ?? $field['placeholder']['nl']
            ?? '';
    };

    $isRequiredField = function (array $field): bool {
        return $field['required'] ?? false;
    };

    $getConditionCategories = function (array $step): array {
        return $step['condition']['service_categories'] ?? [];
    };

    $stepMatchesOldInput = function (array $step) use ($getConditionCategories): bool {
        $allowedCategories = $getConditionCategories($step);

        if (empty($allowedCategories)) {
            return true;
        }

        $selectedCategory = old('service_category', '');

        return in_array($selectedCategory, $allowedCategories, true);
    };

    $labels = [
        'nl' => [
            'hero_badge'     => 'Slimme aanvraag',
            'hero_title'     => 'Start je aanvraag',
            'hero_intro'     => 'Vul de belangrijkste informatie in. Zo kan je aanvraag sneller en duidelijker opgevolgd worden.',
            'submit'         => 'Aanvraag verzenden',
            'success_title'  => 'Je aanvraag werd verzonden.',
            'success_text'   => 'We hebben je aanvraag goed ontvangen en nemen zo snel mogelijk contact op.',
            'error_title'    => 'Controleer de ingevulde informatie.',
            'estimate_title' => 'Richtprijs mogelijk na volledige info',
            'estimate_text'  => 'Op basis van de gekozen dienst, technische gegevens en foto\'s kan ' . $siteName . ' sneller inschatten wat nodig is en indien mogelijk een richtprijs of duidelijke vervolgstap voorstellen.',
            'summary_title'  => 'Samenvatting',
            'summary_text'   => 'De aanvraag wordt opgeslagen zodat ze later opgevolgd kan worden in het admin panel.',
            'choose_option'  => 'Kies een optie',
        ],
        'fr' => [
            'hero_badge'     => 'Demande intelligente',
            'hero_title'     => 'Démarrer votre demande',
            'hero_intro'     => 'Remplissez les informations les plus importantes afin que votre demande puisse être suivie plus rapidement et plus clairement.',
            'submit'         => 'Envoyer la demande',
            'success_title'  => 'Votre demande a été envoyée.',
            'success_text'   => 'Nous avons bien reçu votre demande et nous vous contacterons dès que possible.',
            'error_title'    => 'Veuillez vérifier les informations saisies.',
            'estimate_title' => 'Estimation possible après informations complètes',
            'estimate_text'  => 'Sur la base du service choisi, des informations techniques et des photos, ' . $siteName . ' peut évaluer plus rapidement la situation et proposer une estimation ou une prochaine étape claire si possible.',
            'summary_title'  => 'Résumé',
            'summary_text'   => 'La demande sera enregistrée afin de pouvoir être suivie plus tard dans le panneau d\'administration.',
            'choose_option'  => 'Choisissez une option',
        ],
        'en' => [
            'hero_badge'     => 'Smart request',
            'hero_title'     => 'Start your request',
            'hero_intro'     => 'Fill in the most important information so your request can be followed up faster and more clearly.',
            'submit'         => 'Send request',
            'success_title'  => 'Your request has been sent.',
            'success_text'   => 'We have received your request and will contact you as soon as possible.',
            'error_title'    => 'Please check the entered information.',
            'estimate_title' => 'Estimate possible after complete information',
            'estimate_text'  => 'Based on the selected service, technical details and photos, ' . $siteName . ' can estimate what is needed faster and provide an estimate or clear next step when possible.',
            'summary_title'  => 'Summary',
            'summary_text'   => 'The request will be stored so it can later be followed up in an admin panel.',
            'choose_option'  => 'Choose an option',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];
@endphp
```

- [ ] **Step 2: Replace the sidebar nav loop**

Replace the `<aside class="request-steps">` block (the loop over `$steps` in the sidebar) with:

```blade
<aside class="request-steps">
    @foreach ($steps as $index => $step)
        @php
            $conditionCategories = $getConditionCategories($step);
            $isVisibleByDefault  = $stepMatchesOldInput($step);
        @endphp

        <div
            class="request-step {{ $index === 0 ? 'is-active' : '' }} {{ $isVisibleByDefault ? '' : 'is-condition-hidden' }}"
            data-step-nav="{{ $index }}"
            @if (!empty($conditionCategories))
                data-condition-service-categories="{{ implode(',', $conditionCategories) }}"
            @endif
        >
            {{ $getLabel($step) }}
        </div>
    @endforeach
</aside>
```

- [ ] **Step 3: Replace the main form sections loop**

Replace the `@foreach ($steps as $stepIndex => $step)` block inside `.request-form-card` with:

```blade
@foreach ($steps as $stepIndex => $step)
    @php
        $conditionCategories = $getConditionCategories($step);
        $isVisibleByDefault  = $stepMatchesOldInput($step);
    @endphp

    <section
        class="form-section {{ $isVisibleByDefault ? '' : 'is-condition-hidden' }}"
        data-step="{{ $stepIndex }}"
        @if (!empty($conditionCategories))
            data-condition-service-categories="{{ implode(',', $conditionCategories) }}"
        @endif
    >
        @if (($step['type'] ?? '') !== 'summary')
            <h2>{{ $getLabel($step) }}</h2>
        @endif

        @if (($step['type'] ?? '') === 'service_category_selection')
            <div class="option-grid">
                @foreach ($step['options'] ?? [] as $option)
                    <label class="option-card {{ old('service_category', '') === $option['value'] ? 'is-selected' : '' }}">
                        <input
                            type="radio"
                            name="service_category"
                            value="{{ $option['value'] }}"
                            {{ old('service_category', '') === $option['value'] ? 'checked' : '' }}
                        >
                        <span>{{ $getLabel($option) }}</span>
                    </label>
                @endforeach
            </div>

            @error('service_category')
                <p class="field-error-text">{{ $message }}</p>
            @enderror

        @elseif (($step['type'] ?? '') === 'fields')
            @if (isset($step['urgent_warning']))
                <div class="urgent-warning-box">
                    {{ $step['urgent_warning'][$locale] ?? $step['urgent_warning']['nl'] }}
                </div>
            @endif

            <div class="form-grid">
                @foreach ($step['fields'] ?? [] as $field)
                    @if (($field['type'] ?? '') === 'checkbox')
                        <label class="checkbox-field {{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                            <input
                                type="checkbox"
                                name="{{ $field['name'] }}"
                                value="1"
                                {{ old($field['name']) ? 'checked' : '' }}
                            >
                            <span>{{ $getLabel($field) }}</span>
                            @error($field['name'])
                                <p class="field-error-text">{{ $message }}</p>
                            @enderror
                        </label>
                    @elseif (($field['type'] ?? '') === 'textarea')
                        <label class="{{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                            <span>
                                {{ $getLabel($field) }}
                                @if ($isRequiredField($field))
                                    <span class="required-star">*</span>
                                @endif
                            </span>
                            <textarea
                                name="{{ $field['name'] }}"
                                placeholder="{{ $getPlaceholder($field) }}"
                            >{{ old($field['name']) }}</textarea>
                            @error($field['name'])
                                <p class="field-error-text">{{ $message }}</p>
                            @enderror
                        </label>
                    @elseif (($field['type'] ?? '') === 'select')
                        <label class="{{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                            <span>
                                {{ $getLabel($field) }}
                                @if ($isRequiredField($field))
                                    <span class="required-star">*</span>
                                @endif
                            </span>
                            <select name="{{ $field['name'] }}">
                                <option value="">{{ $text['choose_option'] }}</option>
                                @foreach ($field['options'] ?? [] as $option)
                                    <option value="{{ $option['value'] }}" {{ old($field['name']) === $option['value'] ? 'selected' : '' }}>
                                        {{ $getLabel($option) }}
                                    </option>
                                @endforeach
                            </select>
                            @error($field['name'])
                                <p class="field-error-text">{{ $message }}</p>
                            @enderror
                        </label>
                    @else
                        <label class="{{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                            <span>
                                {{ $getLabel($field) }}
                                @if ($isRequiredField($field))
                                    <span class="required-star">*</span>
                                @endif
                            </span>
                            <input
                                type="{{ $field['type'] ?? 'text' }}"
                                name="{{ $field['name'] }}"
                                value="{{ old($field['name']) }}"
                                placeholder="{{ $getPlaceholder($field) }}"
                            >
                            @error($field['name'])
                                <p class="field-error-text">{{ $message }}</p>
                            @enderror
                        </label>
                    @endif
                @endforeach
            </div>

            @if (isset($step['helper_box']))
                <div class="upload-box {{ $errors->has('attachments') || $errors->has('attachments.*') ? 'field-has-error' : '' }}">
                    <strong>
                        {{ $step['helper_box']['title'][$locale] ?? $step['helper_box']['title']['nl'] }}
                    </strong>
                    <p>
                        {{ $step['helper_box']['text'][$locale] ?? $step['helper_box']['text']['nl'] }}
                    </p>
                    @if ($step['helper_box']['render_upload'] ?? true)
                        <label class="upload-file-control">
                            <span>
                                {{ $locale === 'fr' ? 'Choisir des fichiers' : ($locale === 'en' ? 'Choose files' : 'Bestanden kiezen') }}
                            </span>
                            <input
                                id="attachmentsInput"
                                type="file"
                                name="attachments[]"
                                multiple
                                accept=".jpg,.jpeg,.png,.webp,.pdf"
                            >
                        </label>
                        <div id="selectedAttachments" class="selected-attachments"></div>
                        @error('attachments')
                            <p class="field-error-text">{{ $message }}</p>
                        @enderror
                        @error('attachments.*')
                            <p class="field-error-text">{{ $message }}</p>
                        @enderror
                    @endif
                </div>
            @endif

        @elseif (($step['type'] ?? '') === 'summary')
            <div class="request-summary-box">
                <h2>{{ $text['estimate_title'] }}</h2>
                <p>{{ $text['estimate_text'] }}</p>
            </div>
            <div class="summary-card">
                <h3>{{ $text['summary_title'] }}</h3>
                <p>{{ $text['summary_text'] }}</p>
            </div>
        @endif
    </section>
@endforeach
```

- [ ] **Step 4: Replace the inline `<script>` block at the bottom**

Replace the entire `<script>` tag at the bottom of the file with:

```html
<script>
    (function () {
        function initConditionalRequestSteps() {
            const conditionalElements = document.querySelectorAll('[data-condition-service-categories]');
            const categoryInputs = document.querySelectorAll('input[name="service_category"]');

            function getCheckedValue(inputs) {
                const checked = Array.from(inputs).find((i) => i.checked);
                return checked ? checked.value : '';
            }

            function updateConditionalSteps() {
                const selectedCategory = getCheckedValue(categoryInputs);

                conditionalElements.forEach((element) => {
                    const allowed = (element.dataset.conditionServiceCategories || '')
                        .split(',')
                        .map((v) => v.trim())
                        .filter(Boolean);

                    const matches = allowed.length === 0 || allowed.includes(selectedCategory);
                    element.classList.toggle('is-condition-hidden', !matches);
                });
            }

            categoryInputs.forEach((input) => {
                const card = input.closest('.option-card');

                if (card) {
                    card.addEventListener('click', updateConditionalSteps);
                }

                input.addEventListener('change', updateConditionalSteps);
            });

            updateConditionalSteps();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initConditionalRequestSteps);
        } else {
            initConditionalRequestSteps();
        }
    })();
</script>
```

- [ ] **Step 5: Commit**

```bash
git add resources/views/pages/partials/request-page.blade.php
git commit -m "feat: update request form blade for service_category_selection and conditional steps"
```

---

## Task 5: Update CustomerRequestController

**Files:**
- Modify: `app/Http/Controllers/CustomerRequestController.php`

Key changes:
1. Validate `service_category` (replaces `service_slug` + `request_type` user inputs).
2. Derive `service_slug` and `request_type` from `service_category` via config mapping.
3. Fix `getDynamicFields()` to skip conditional steps that don't match submitted `service_category`.
4. Store `source`, `service_category`, `urgency_level`, `preferred_time`, `customer_message` in the model.

- [ ] **Step 1: Replace the full controller file**

```php
<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Mail\NewCustomerRequestMail;
use Illuminate\Support\Facades\Mail;
use App\Mail\CustomerRequestConfirmationMail;

class CustomerRequestController extends Controller
{
    public function store(Request $request, string $locale): RedirectResponse
    {
        $serviceCategories = collect(config('request-flow.service_categories', []));
        $allowedCategoryValues = $serviceCategories->pluck('value')->toArray();

        $dynamicFields = $this->getDynamicFields();

        $rules = [
            'service_category' => [
                'required',
                'string',
                Rule::in($allowedCategoryValues),
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:8',
            ],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
        ];

        foreach ($dynamicFields as $field) {
            $rules[$field['name']] = $this->buildRulesForField($field);
        }

        $validatedData = $request->validate($rules);

        // Derive service_slug and request_type from service_category
        $selectedCategory = $serviceCategories->firstWhere('value', $validatedData['service_category']);
        $serviceKey = $selectedCategory['service_key'] ?? 'heating';
        $derivedRequestType = $selectedCategory['request_type'] ?? 'repair';

        $allServices = config('services');
        $serviceConfig = $allServices[$serviceKey] ?? null;
        $serviceSlug = $serviceConfig['translations'][$locale]['slug']
            ?? $serviceConfig['translations']['nl']['slug']
            ?? $serviceKey;
        $serviceTitle = $serviceConfig['translations'][$locale]['title']
            ?? $serviceConfig['translations']['nl']['title']
            ?? $serviceKey;

        $answers = [
            'service_category' => $validatedData['service_category'],
            'service_slug'     => $serviceSlug,
            'service_title'    => $serviceTitle,
            'request_type'     => $derivedRequestType,
        ];

        foreach ($dynamicFields as $field) {
            $fieldName = $field['name'];

            if (($field['type'] ?? '') === 'checkbox') {
                $answers[$fieldName] = $request->boolean($fieldName);
                continue;
            }

            $answers[$fieldName] = $validatedData[$fieldName] ?? null;
        }

        $customerRequest = CustomerRequest::create([
            'locale'       => $locale,
            'service_slug' => $serviceSlug,
            'request_type' => $derivedRequestType,
            'source'       => 'website',

            'service_category' => $validatedData['service_category'],
            'urgency_level'    => $answers['urgency_level'] ?? null,
            'preferred_time'   => $answers['preferred_time'] ?? $answers['availability'] ?? null,
            'customer_message' => $answers['description'] ?? null,
            'ai_summary'       => null,
            'ai_detected_missing_fields' => null,

            'customer_name'  => $answers['customer_name'] ?? '',
            'customer_email' => $answers['customer_email'] ?? '',
            'customer_phone' => $answers['customer_phone'] ?? null,

            'brand'                  => $answers['brand'] ?? null,
            'device_model'           => $answers['device_model'] ?? null,
            'serial_number'          => $answers['serial_number'] ?? null,
            'unknown_device_details' => $answers['unknown_device_details'] ?? false,

            'description' => $answers['description'] ?? '',
            'status'      => 'new',

            'metadata' => [
                'source'       => 'smart_request_form',
                'service'      => ['slug' => $serviceSlug, 'title' => $serviceTitle],
                'request_type' => ['value' => $derivedRequestType],
                'answers'      => $answers,
            ],
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $uploadedFile) {
                $path = $uploadedFile->store('customer-requests', 'public');

                $customerRequest->attachments()->create([
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'path'          => $path,
                    'mime_type'     => $uploadedFile->getMimeType(),
                    'size'          => $uploadedFile->getSize(),
                ]);
            }
        }

        $customerRequest->load(['attachments', 'notes']);

        $notificationEmails = config('admin.notification_emails', []);

        foreach ($notificationEmails as $email) {
            Mail::to($email)->send(new NewCustomerRequestMail($customerRequest));
        }

        Mail::to($customerRequest->customer_email)->send(
            new CustomerRequestConfirmationMail($customerRequest)
        );

        return back()->with('success', 'request_created');
    }

    private function getDynamicFields(): array
    {
        $steps = config('request-flow.steps', []);
        $fields = [];
        $selectedCategory = request()->input('service_category');

        foreach ($steps as $step) {
            if (($step['type'] ?? '') !== 'fields') {
                continue;
            }

            $condition = $step['condition'] ?? null;

            if ($condition) {
                $allowedCategories = $condition['service_categories'] ?? [];

                if (!empty($allowedCategories) && !in_array($selectedCategory, $allowedCategories, true)) {
                    continue;
                }
            }

            foreach (($step['fields'] ?? []) as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function buildRulesForField(array $field): array
    {
        $rules = [];

        if ($field['required'] ?? false) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $type = $field['type'] ?? 'text';

        if (in_array($field['name'], ['brand', 'device_model'], true)) {
            $rules = ['nullable', 'string', 'max:255'];

            if (!request()->boolean('unknown_device_details')) {
                $rules[0] = 'required';
            }

            return $rules;
        }

        if ($type === 'select') {
            $rules[] = 'string';

            $allowedValues = collect($field['options'] ?? [])->pluck('value')->toArray();

            if (!empty($allowedValues)) {
                $rules[] = Rule::in($allowedValues);
            }

            return $rules;
        }

        if ($type === 'email') {
            $rules[] = 'email';
            $rules[] = 'max:255';

            return $rules;
        }

        if ($type === 'tel') {
            $rules[] = 'string';
            $rules[] = 'max:50';
            $rules[] = 'regex:/^[0-9+\s().-]+$/';

            return $rules;
        }

        if ($type === 'checkbox') {
            $rules[] = 'boolean';

            return $rules;
        }

        if ($type === 'textarea') {
            $rules[] = 'string';
            $rules[] = 'max:5000';

            return $rules;
        }

        if ($type === 'number') {
            $rules[] = 'integer';
            $rules[] = 'min:0';
            $rules[] = 'max:999';

            return $rules;
        }

        $rules[] = 'string';
        $rules[] = 'max:255';

        return $rules;
    }

    private function getTranslatedLabel(array $item, string $locale): string
    {
        return $item['labels'][$locale]
            ?? $item['labels']['nl']
            ?? $item['value']
            ?? '';
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/CustomerRequestController.php
git commit -m "feat: validate service_category, derive service_slug/request_type, store workflow fields"
```

---

## Task 6: Update admin views

**Files:**
- Modify: `resources/views/admin/requests/index.blade.php`
- Modify: `resources/views/admin/requests/show.blade.php`

### 6a — Index view

Show `service_category` (human-readable from config) in place of the old service title column. Show the `urgency_level` direct DB column for urgent badge. Pass `serviceCategoryLabels` from the controller.

- [ ] **Step 1: Update Admin\RequestController::index() to pass service category labels**

In `app/Http/Controllers/Admin/RequestController.php`, add to the `index()` method before `return view(...)`:

```php
$serviceCategoryLabels = collect(config('request-flow.service_categories', []))
    ->mapWithKeys(function (array $cat): array {
        return [$cat['value'] => $cat['labels']['nl'] ?? $cat['value']];
    })
    ->toArray();
```

Add `'serviceCategoryLabels' => $serviceCategoryLabels` to the view data array.

- [ ] **Step 2: Update the admin table in index.blade.php**

Replace the table header row (`<tr>`) with:

```blade
<tr>
    <th>Datum</th>
    <th>Naam</th>
    <th>E-mail</th>
    <th>Telefoon</th>
    <th>Aanvraag</th>
    <th>Urgentie</th>
    <th>Status</th>
    <th></th>
</tr>
```

Replace the table body `@foreach` row with:

```blade
@foreach ($customerRequests as $request)
    @php
        $categoryLabel = $serviceCategoryLabels[$request->service_category] ?? ($request->service_category ?: $request->service_slug);

        $urgencyLevelLabels = [
            'water_leaking' => 'Water aanwezig',
            'small_leak'    => 'Klein lek',
            'no_heating'    => 'Geen verwarming',
            'no_hot_water'  => 'Geen warm water',
            'other'         => 'Andere',
            'urgent'        => 'Dringend',
            'within_days'   => 'Enkele dagen',
            'not_urgent'    => 'Niet dringend',
        ];

        $urgencyLevel = $request->urgency_level ?? ($request->metadata['answers']['urgency'] ?? null);
        $urgencyLabel = $urgencyLevelLabels[$urgencyLevel] ?? '-';
    @endphp

    <tr>
        <td data-label="Datum">{{ $request->created_at->format('d/m/Y H:i') }}</td>
        <td data-label="Naam">{{ $request->customer_name }}</td>
        <td data-label="E-mail">{{ $request->customer_email }}</td>
        <td data-label="Telefoon">{{ $request->customer_phone ?: '-' }}</td>
        <td data-label="Aanvraag">{{ $categoryLabel }}</td>
        <td data-label="Urgentie">
            <span class="admin-urgency admin-urgency-{{ $urgencyLevel ?: 'none' }}">
                {{ $urgencyLabel }}
            </span>
        </td>
        <td data-label="Status">
            <span class="admin-status admin-status-{{ $request->status }}">
                {{ $statuses[$request->status] ?? $request->status }}
            </span>
        </td>
        <td data-label="">
            <a class="admin-link" href="{{ route('admin.requests.show', $request) }}">
                Bekijken
            </a>
        </td>
    </tr>
@endforeach
```

### 6b — Show view

- [ ] **Step 3: Add new fields to the show view**

In the "Aanvraag" card (`<div class="admin-detail-card">`), add new `<div>` rows after the existing "Type aanvraag" row:

```blade
@if ($customerRequest->service_category)
    <div>
        <dt>Aanvraag categorie</dt>
        <dd>{{ $serviceCategoryLabels[$customerRequest->service_category] ?? $customerRequest->service_category }}</dd>
    </div>
@endif

@if ($customerRequest->urgency_level)
    @php
        $urgencyLevelLabels = [
            'water_leaking' => 'Water aanwezig / ernstig lek',
            'small_leak'    => 'Klein lek',
            'no_heating'    => 'Geen verwarming',
            'no_hot_water'  => 'Geen warm water',
            'other'         => 'Andere urgentie',
        ];
    @endphp
    <div>
        <dt>Urgentieniveau</dt>
        <dd>
            <span class="admin-urgency admin-urgency-{{ $customerRequest->urgency_level }}">
                {{ $urgencyLevelLabels[$customerRequest->urgency_level] ?? $customerRequest->urgency_level }}
            </span>
        </dd>
    </div>
@endif

@if ($customerRequest->preferred_time)
    <div>
        <dt>Gewenst moment</dt>
        <dd>{{ $customerRequest->preferred_time }}</dd>
    </div>
@endif

@if ($customerRequest->customer_message)
    <div>
        <dt>Klantbericht</dt>
        <dd>{{ $customerRequest->customer_message }}</dd>
    </div>
@endif
```

Also pass `$serviceCategoryLabels` from `RequestController::show()`: add the same `$serviceCategoryLabels` array to the view data returned from the `show()` method.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Admin/RequestController.php
git add resources/views/admin/requests/index.blade.php
git add resources/views/admin/requests/show.blade.php
git commit -m "feat: show service_category and urgency_level in admin views"
```

---

## Task 7: CSS updates

**Files:**
- Modify: `resources/css/pages/request.css`
- Modify: `resources/css/pages/admin.css`

### 7a — request.css

- [ ] **Step 1: Add urgent-warning-box and fix step-index selectors**

In `resources/css/pages/request.css`:

1. Add after the `.is-condition-hidden` rule:

```css
/* ================================
   Urgent warning box
================================ */

.urgent-warning-box {
    margin-bottom: 20px;
    padding: 16px 20px;
    border: 1.5px solid rgba(220, 38, 38, 0.4);
    border-radius: 16px;
    background: #fff1f2;
    color: #991b1b;
    font-weight: 800;
    line-height: 1.5;
}
```

2. Update the mobile step-index CSS selectors (they used old data-step values — update to new indices):

Replace:
```css
/* Step 3: customer type + urgency onder elkaar */
.form-section[data-step="2"] .form-grid,
.form-section[data-step="2"] .field-grid {
```
With:
```css
/* Step 5: customer type + urgency onder elkaar */
.form-section[data-step="5"] .form-grid,
.form-section[data-step="5"] .field-grid {
```

Replace:
```css
.form-section[data-step="2"] .form-grid label,
.form-section[data-step="2"] .field-grid label {
```
With:
```css
.form-section[data-step="5"] .form-grid label,
.form-section[data-step="5"] .field-grid label {
```

Replace:
```css
/* Step 5: checkbox volle breedte */
.form-section[data-step="4"] .checkbox-field {
```
With:
```css
/* Step 7: checkbox volle breedte */
.form-section[data-step="7"] .checkbox-field {
```

Replace:
```css
/* Step 6: locatie */
.form-section[data-step="5"] .form-grid,
.form-section[data-step="5"] .field-grid {
```
With:
```css
/* Step 8: locatie */
.form-section[data-step="8"] .form-grid,
.form-section[data-step="8"] .field-grid {
```

Replace:
```css
.form-section[data-step="5"] .form-grid label:nth-child(1),
.form-section[data-step="5"] .form-grid label:nth-child(4),
.form-section[data-step="5"] .field-grid label:nth-child(1),
.form-section[data-step="5"] .field-grid label:nth-child(4) {
```
With:
```css
.form-section[data-step="8"] .form-grid label:nth-child(1),
.form-section[data-step="8"] .form-grid label:nth-child(4),
.form-section[data-step="8"] .field-grid label:nth-child(1),
.form-section[data-step="8"] .field-grid label:nth-child(4) {
```

Replace:
```css
/* Step 7: contact */
.form-section[data-step="6"] .form-grid,
.form-section[data-step="6"] .field-grid {
```
With:
```css
/* Step 9: contact */
.form-section[data-step="9"] .form-grid,
.form-section[data-step="9"] .field-grid {
```

Replace all three occurrences of `[data-step="6"]` in the contact section with `[data-step="9"]`.

Also update the bottom `@media (max-width: 330px)` block:

Replace:
```css
.form-section[data-step="5"] .form-grid,
.form-section[data-step="5"] .field-grid,
.form-section[data-step="6"] .form-grid,
.form-section[data-step="6"] .field-grid {
    grid-template-columns: 1fr !important;
}

.form-section[data-step="5"] .form-grid label,
.form-section[data-step="5"] .field-grid label,
.form-section[data-step="6"] .form-grid label,
.form-section[data-step="6"] .field-grid label {
    grid-column: 1 / -1;
}
```
With:
```css
.form-section[data-step="8"] .form-grid,
.form-section[data-step="8"] .field-grid,
.form-section[data-step="9"] .form-grid,
.form-section[data-step="9"] .field-grid {
    grid-template-columns: 1fr !important;
}

.form-section[data-step="8"] .form-grid label,
.form-section[data-step="8"] .field-grid label,
.form-section[data-step="9"] .form-grid label,
.form-section[data-step="9"] .field-grid label {
    grid-column: 1 / -1;
}
```

### 7b — admin.css

- [ ] **Step 2: Add urgency badge styles for new urgency_level values**

In `resources/css/pages/admin.css`, after the existing `.admin-urgency-none` rule, add:

```css
.admin-urgency-water_leaking {
    background: #fee2e2;
    color: #7f1d1d;
    font-weight: 900;
}

.admin-urgency-small_leak {
    background: #fef3c7;
    color: #78350f;
}

.admin-urgency-no_heating {
    background: #fef3c7;
    color: #78350f;
}

.admin-urgency-no_hot_water {
    background: #fef3c7;
    color: #78350f;
}

.admin-urgency-other {
    background: #e5e7eb;
    color: #374151;
}
```

- [ ] **Step 3: Commit**

```bash
git add resources/css/pages/request.css resources/css/pages/admin.css
git commit -m "feat: add urgent-warning-box styles and new urgency badge colors, fix step-index CSS"
```

---

## Task 8: Run migration and smoke-test

- [ ] **Step 1: Run the migration**

```bash
php artisan migrate
```

Expected output: `Migrating: 2026_05_29_225828_add_workflow_fields_to_customer_requests_table` followed by `Migrated`.

If it fails with "column already exists", check that Task 1 was completed correctly and `request_type` is no longer in the `up()` method.

- [ ] **Step 2: Build assets**

```bash
npm run build
```

Or for local dev:

```bash
npm run dev
```

- [ ] **Step 3: Test each flow manually**

Open the request page (e.g., `http://localhost/nl/aanvraag`).

**Flow A — Onderhoud centrale verwarming:**
1. Select "Onderhoud centrale verwarming" in step 1 → sidebar step 2 "Centrale verwarming onderhoud" becomes visible.
2. Fill in: installation type = Gasketel, brand = Vaillant, model = ecoTEC, last maintenance = 2021, preferred moment.
3. Observe the info box about uploading the typeplaatje.
4. Fill remaining steps (customer context, description + upload a photo, location, contact).
5. Submit → confirm redirect with success message.
6. Check database: `service_category = 'onderhoud_cv'`, `service_slug = 'verwarming'`, `request_type = 'maintenance'`, `preferred_time` is set, `urgency_level = null`.
7. Open admin → request shows "Onderhoud centrale verwarming" in the category column.

**Flow B — Lek of dringend probleem:**
1. Select "Lek of dringend probleem" → step 2 "Lek of dringend probleem" appears with orange/red urgent warning box at top.
2. Fill urgency = "Er staat water / ernstig lek".
3. Submit.
4. Check admin: urgency badge is dark red, `urgency_level = 'water_leaking'`.

**Flow C — Airco offerte:**
1. Select "Airco offerte" → step 2 "Airco offerte details" appears.
2. Fill in rooms count = 2, room types = "slaapkamer, living", no outdoor unit.
3. Submit → `service_slug = 'airco'`, `request_type = 'installation'`.

**Flow D — Airco onderhoud:**
1. Select "Airco onderhoud" → step 2 "Airco onderhoud details" appears.
2. Fill brand, indoor units count.
3. Submit → `service_slug = 'airco'`, `request_type = 'maintenance'`.

**Flow E — Andere (generic):**
1. Select "Andere" → no conditional step appears; flow goes straight to customer context.
2. Submit → `service_slug = 'verwarming'`, `request_type = 'repair'`.

**Admin check:**
- Visit `/admin/requests` → table shows category label and urgency badge for each request.
- Open a "lek" request → detail view shows urgency_level badge in the Aanvraag card.
- Open a CV onderhoud request → detail view shows `preferred_time` if set.

- [ ] **Step 4: Commit any final fixes**

```bash
git add -A
git commit -m "chore: final fixes after smoke test"
```

---

## Summary for handoff

### Changed files
1. `database/migrations/2026_05_29_225828_add_workflow_fields_to_customer_requests_table.php`
2. `app/Models/CustomerRequest.php`
3. `config/request-flow.php`
4. `resources/views/pages/partials/request-page.blade.php`
5. `app/Http/Controllers/CustomerRequestController.php`
6. `app/Http/Controllers/Admin/RequestController.php`
7. `resources/views/admin/requests/index.blade.php`
8. `resources/views/admin/requests/show.blade.php`
9. `resources/css/pages/request.css`
10. `resources/css/pages/admin.css`

### Migration name
`2026_05_29_225828_add_workflow_fields_to_customer_requests_table`

### Commands to run
```bash
php artisan migrate
npm run build
```

### How to test
See Task 8 Step 3. Test all 5 flows (A–E) + the admin panel display. The critical assertions are:
- Each `service_category` correctly maps to `service_slug` and `request_type` in the database.
- Conditional steps appear/disappear when clicking option cards.
- `urgency_level = 'water_leaking'` shows a dark-red badge in admin.
- `preferred_time` is stored from whichever conditional step provides it.
- Uploads still work (attached files appear in the admin detail view).
- All 3 locales (nl/fr/en) still show correct labels.
