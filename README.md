# Reusable Service Website & Smart Request Platform

A reusable multilingual Laravel platform for service-based businesses.

This project started with **mastechnics** as the first real-world implementation, but the goal is to build a reusable system that can be adapted for other companies such as HVAC businesses, plumbers, electricians, roofers, garden services, repair companies and other technical service providers.

The focus is not just on building a standard website, but on creating a structured platform for service presentation, smart customer intake, contact handling and future request management.

## Project Concept

Many service companies receive incomplete requests through phone calls, emails, Messenger, WhatsApp or basic contact forms.

This platform aims to improve that flow by combining:

- a multilingual website
- clear service pages
- a smart request flow
- structured technical intake
- contact options
- future request management
- future CRM and automation integration

Instead of only asking for a name and message, the system guides the visitor through a more useful request process.

Example:

1. Choose a service
2. Choose the type of request
3. Add technical details
4. Describe the issue or project
5. Add photos if needed
6. Prepare the request for faster follow-up or estimate

## First Implementation: mastechnics

The first implementation is built for **mastechnics**, a technical service company focused on:

- heating
- air conditioning
- plumbing
- ventilation
- water softeners
- cold rooms and refrigeration

The current version includes a first branded mock-up for mastechnics, while keeping the long-term goal of making the system reusable for other service businesses.

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
- Page types:
  - homepage
  - service page
  - smart request page
  - contact page
  - default page fallback
- Language switcher linked to the correct translated page
- Styled homepage focused on a smart request flow
- First service page implementation
- Smart request form mock-up
- Contact page mock-up
- Responsive CSS structure split by base, layout, components and pages
- Git-based version control and GitHub backup

## Technical Approach

The project uses a database-driven multilingual page structure.

Instead of creating separate hardcoded files for every language, the system uses:

```text
pages
page_translations
