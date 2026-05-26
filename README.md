# mastechnics Website Platform

A multilingual Laravel web platform for a technical service company offering heating, air conditioning, plumbing, ventilation, water softeners and refrigeration services.

The goal of this project is not just to build a basic company website, but to create a structured and scalable foundation for a professional service intake system.

## Project Goal

mastechnics is being developed as a multilingual service website with a strong focus on:

- clear service presentation
- multilingual content structure
- SEO-friendly URLs
- scalable page management
- future quote request flows
- future CRM and automation integration

The long-term idea is to support the full client journey: from first website visit and quote request to structured follow-up of customer inquiries.

## Current Features

- Laravel 12 project setup
- Multilingual routing structure
- Supported languages:
  - Dutch
  - French
  - English
- Dynamic page system using database-driven content
- Separate page translations per language
- SEO fields per language
- Reusable Blade layout structure
- Different page types, such as:
  - homepage
  - service pages
  - default content pages
- Language switcher linked to the correct translated page
- Initial homepage and heating service page

## Technical Approach

The project uses a database-driven multilingual page structure.

Instead of creating separate hardcoded pages for every language, the system uses a central `pages` table and a related `page_translations` table.

This allows one page to have multiple language versions while still using the same reusable Laravel views.

Example structure:

```text
/nl/verwarming
/fr/chauffage
/en/heating
