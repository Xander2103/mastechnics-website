# Reusable Service Website & Smart Request Platform

A reusable multilingual Laravel platform for service-based businesses.

This project started with **mastechnics** as the first real-world implementation, but the goal is to build a reusable system that can be adapted for other companies such as HVAC businesses, plumbers, electricians, roofers, garden services, repair companies and other technical service providers.

The focus is not just on building a standard website, but on creating a structured platform for service presentation, smart customer intake, contact handling and request management.

---

## Project Concept

Many service companies receive incomplete requests through phone calls, emails, Messenger, WhatsApp or basic contact forms.

This platform aims to improve that flow by combining:

- a multilingual website
- clear service pages
- a smart request flow
- structured technical intake
- file/photo uploads
- contact options
- admin request management
- status tracking
- email notifications
- future CRM and automation integration

Instead of only asking for a name and message, the system guides the visitor through a more useful request process.

Example request flow:

1. Choose a service
2. Choose the type of request
3. Describe the issue or project
4. Add technical details
5. Add photos or documents if needed
6. Submit the request for faster follow-up or estimate

This makes the request more useful for the company and reduces unnecessary back-and-forth communication.

---

## First Implementation: mastechnics

The first implementation is built for **mastechnics**, a technical service company focused on:

- heating
- air conditioning
- plumbing
- ventilation
- water softeners
- cold rooms and refrigeration

The current version includes a branded implementation for mastechnics, while keeping the long-term goal of making the system reusable for other service businesses.

---

## Current Features

### Website

- Laravel 12 project setup
- Multilingual routing structure
- Supported languages:
  - Dutch
  - French
  - English
- Database-driven page system
- Separate page translations per language
- SEO fields per language
- Reusable Blade layout structure
- Language switcher linked to the correct translated page
- Responsive CSS structure split by base, layout, components and pages

Page types:

- homepage
- service page
- smart request page
- contact page
- default page fallback

---

### Reusable Configuration

The project is structured to be reusable for other businesses through configuration files.

Important configuration files:

- `config/site.php`
- `config/services.php`
- `config/request-flow.php`
- `config/admin.php`

This makes it possible to adapt the platform to another business without rewriting the full application.

For example, the current mastechnics request flow uses fields such as:

- brand
- model
- serial number
- issue description
- photos

For another business, such as a custom furniture company, the request flow could be changed to fields such as:

- width
- height
- depth
- material
- number of doors
- number of drawers
- finish

The goal is to keep the core platform reusable while allowing each business to have a custom website, custom services and a custom intake flow.

---

### Smart Request Form

The smart request form is built from `config/request-flow.php`.

Current request form features:

- dynamic steps from configuration
- service selection
- request type selection
- issue or project description
- technical details
- contact details
- required field validation
- conditional validation
- multiple file uploads
- removable selected files before submitting
- support for images and PDF files
- customer-friendly error messages
- structured request storage in the database
- metadata storage for flexible future request types

Current upload rules:

- max 8 files
- max 5 MB per file
- allowed types:
  - jpg
  - jpeg
  - png
  - webp
  - pdf

---

### Admin Panel

The project includes a protected admin panel for managing incoming requests.

Admin features:

- admin login
- admin logout
- protected admin routes
- multiple admin accounts through configuration
- request overview
- request detail page
- request status management
- filters on the request overview
- attachment preview/download
- full answer overview per request

Request statuses:

- new
- contacted
- planned
- done
- cancelled

Available filters:

- search by name, email or phone
- status
- service
- request type
- date from
- date to

---

### Email Notifications

When a new request is submitted, the system can send an email notification to one or more admin email addresses.

Notification recipients are configured in:

```php
config/admin.php