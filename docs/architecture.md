# Dinner Decider application architecture

Status: Stages 0–5 implemented; MVP release candidate with open release gates; Post-MVP Epics 9–23 architected but not implemented

Last reviewed: 2026-07-24

Sources of functional scope: Dinner Decider MVP Product Specification.docx and Dinner Decider Post-MVP Development Plan.docx<br>
Resolved product decisions: Dinner Decider — Remaining MVP Product Decisions<br>
Target runtime: PHP 8.5, Laravel 13, Livewire 4, Flux UI 2, MySQL 8.4

## Reading this document

This document distinguishes four kinds of statements:

- **Confirmed repository fact** describes code or configuration that exists now.
- **MVP decision** is the architecture adopted for the eight defined epics.
- **Post-MVP decision** is the target architecture for Epics 9–23. It is approved roadmap direction, not a claim that the code exists.
- **Future option** is a deliberate extension point, not work required now.

The architecture is a development reference, not an instruction to implement every directory or class immediately. New classes should be introduced only with the milestone that needs them. Sections 1–24 establish the implemented MVP baseline, enduring rules, cross-cutting decisions, and staged roadmap; Section 25 describes the detailed post-MVP deltas. When the post-MVP plan says to add something that already exists, the confirmed repository fact and the delta in Section 25 take precedence over creating a duplicate concept.

## 1. Purpose and architectural goals

Dinner Decider must support one connected and reliable flow:

> Add pantry stock → receive pantry-aware recommendations → select dinners → reserve ingredients → update availability → generate groceries → cook dinner → consume reserved stock.

The architecture therefore prioritizes:

1. Exact and explainable quantity calculations.
2. Transactional reservation and consumption behavior.
3. Clear ownership of user data and business rules.
4. Thin delivery code in Livewire pages and HTTP controllers.
5. Focused application actions for business operations.
6. Eloquent and Laravel conventions instead of a parallel framework.
7. Deterministic, independently testable recommendation, allocation, scaling, and grocery algorithms.
8. A modular monolith that can grow from the MVP through the post-MVP roadmap without being split prematurely.
9. Complete household isolation once shared ownership is introduced.
10. Reviewable, failure-tolerant external data ingestion that never bypasses deterministic domain rules.

The design must make invalid states difficult to create: incompatible units must not match, stock must not become negative, reservations must not be applied twice, and a cooked dinner must not be consumed twice.

## 2. Project context and constraints

### 2.1 Confirmed repository facts

- The application includes the Stage 1 measurement/catalogue domains, Stage 2 pantry/recommendations, Stage 3 rolling dinner plan and reservations, and the Stage 4 persisted grocery projection.
- The runtime is PHP 8.5.8, Laravel 13.20.0, Livewire 4.3.3, Flux UI 2.15.0, and MySQL 8.4.
- composer.json declares PHP ^8.3. CI supports PHP 8.3/Node 22.12 as the minimum pair and PHP 8.5/Node 24 as the preferred Docker runtime; this architecture does not require PHP 8.5-only syntax.
- Laravel Sail provides the Docker development environment.
- The UI convention is Livewire 4 page single-file components under resources/views/pages, using the pages namespace and the lightning-bolt filename prefix.
- Authentication is provided by Fortify with registration, password reset, enforced email verification, two-factor authentication, passkeys, and login throttling.
- The application uses database-backed sessions and cache. Queues execute synchronously for the MVP; database queue tables are retained, but no application job, worker service, or scheduled task exists.
- PHPUnit 12, Larastan level 7, and Pint are configured.
- The existing application layer contains focused Fortify actions and an invokable Logout action.
- The database includes Stage 1–4 ingredient, recipe, pantry, rolling dinner-plan, reservation, grocery-list, grocery-item, and grocery-contribution schemas in addition to the authentication infrastructure.
- Authenticated, verified product routes cover all six product areas: ingredients, recipes, pantry, recommendations, dinner plan, and groceries.
- Stage 5 adds release hardening, a realistic action-derived demo fixture, shared recipe-image storage security, bounded read-path tests, and operational documentation without redesigning the schema or queue model.
- Laravel Sanctum is not installed and routes/api.php does not exist; Epic 23 must treat API authentication/routing as an approved dependency and delivery change, not a current capability.

### 2.2 Functional scope

The MVP specification defines eight epics:

1. Ingredient and Measurement System
2. Recipe Catalogue
3. Pantry Management
4. Recipe Serving Scaling
5. Pantry-Based Recommendations
6. Dinner Plan Management
7. Ingredient Reservation Lifecycle
8. Grocery List Generation

Explicitly excluded from the MVP are AI recipe generation, recipe imports, substitutions, dietary profiles, shared households, expiry tracking, package rounding, prices, barcode scanning, imperial units, approximate conversions, and automatic multi-dinner plan generation. The architecture must not smuggle these features back in as speculative abstractions.

The resolved decisions add basic Cooked/Cancelled dinner history inside the rolling plan because it is needed for snapshots, duplication, and restore. This is the baseline that Epic 11 extends with a fuller chronological history query, filters, and lifecycle evidence; Epic 11 must not create a second dinner-history aggregate.

The post-MVP plan defines fifteen additional epics:

| Phase | Epics |
| --- | --- |
| MVP Stabilisation | 9. MVP Quality and Architecture Review |
| Everyday Usability | 10. Recipe Favourites; 11. Cooking and Dinner History; 12. Decision Mode |
| Recipe Discovery and Flexibility | 13. Recipe Tags and Dietary Filters; 14. Ingredient Substitutions |
| Household Support | 15. Shared Households |
| Smarter Pantry Management | 16. Pantry Expiry Dates; 17. Package Sizes and Supermarket Rounding; 18. Grocery Prices and Budgeting |
| Data Entry Improvements | 19. Recipe Import; 20. AI-Assisted Recipe Processing |
| Product Expansion | 21. Barcode Scanning; 22. Progressive Web App; 23. Public API |

Several roadmap epics deliberately extend current concepts:

- Recipe favourites are new and remain personal even after household migration.
- Cooked/cancelled status, timestamps, snapshots, restoration, and history-based planning already exist; Epic 11 adds history completeness and usability.
- Free-form Tag and recipe-tag persistence already exist; Epic 13 adds structured dietary facts and shared filtering rules rather than another tag system.
- IngredientPackage already represents ingredient-specific package contents; Epic 17 adds retail purchase options and rounding without replacing exact requirement quantities.
- The current per-user ownership model remains authoritative until Epic 15 performs an explicit, tested household migration.

### 2.3 Technical constraints

- MySQL is the production-shaped persistence target.
- All calculation code must avoid binary floating point.
- The supported measurement system is metric plus semantic count units and explicitly defined packages.
- Existing Laravel conventions and the App root namespace must remain intact.
- Product code remains compatible with the declared PHP ^8.3 constraint until the runtime/support policy is changed and tested.
- The primary frontend remains server-driven Livewire and Blade. Epic 23 later adds an API delivery adapter; it does not require replacing the web UI with a separate SPA.
- User-facing writes must be authorized and validated server-side even when the interface already constrains input.
- Core correctness cannot depend on a queue worker because the current Compose stack does not run one.
- Post-MVP external providers are optional edges. Recipe creation, pantry entry, shopping, and planning must retain a manual path when a provider is unavailable.
- Shared-household work must use an expand/backfill/enforce migration and tenant-isolation tests; a request-scoped active household is context, never authorization by itself.
- Exact recipe need, pantry truth, reservations, and grocery shortfalls remain deterministic even when package optimization, price estimates, imports, AI extraction, barcodes, an API, or a PWA are added.
- New Composer or npm dependencies, external providers, and production queue/worker services require an explicit implementation-time dependency and operations decision.

### 2.4 Baseline hardening status and remaining incomplete patterns

Stage 0 resolved the original runtime and configuration inconsistencies. Remaining findings are deliberately postponed until their related feature is in scope:

| Finding or former gap | Current status | Follow-up |
| --- | --- | --- |
| Fortify email verification was enabled without App\Models\User implementing Illuminate\Contracts\Auth\MustVerifyEmail. | Resolved: the contract is implemented and an unverified-route test proves enforcement. | Apply auth and verified middleware to MVP product routes. |
| CI and Docker used different undocumented PHP/Node pairs. | Resolved: CI tests PHP 8.3/Node 22 and PHP 8.5/Node 24, and the support policy is documented. | Raise minimums only through an explicit compatibility decision. |
| CI had no MySQL service despite the MySQL-oriented environment. | Resolved: both CI matrix jobs use a health-checked MySQL 8.4 service and run migrations before tests. | Add targeted locking and decimal integration tests with their domain features. |
| Composer's post-create hook created an SQLite file. | Resolved: the stale hook was removed and setup uses MySQL. | Keep pure unit tests database-free rather than introducing a second database engine. |
| The database queue was selected without a worker. | Resolved for the MVP: the default is sync and database queue infrastructure is retained but inactive. | Add a supervised worker and after-commit behavior before the first queued feature. |
| boost.json has Sail disabled and Codex starts Boost through host PHP while DB_HOST is mysql. | Documentation tools work, but database-aware Boost tools may not reach the Docker network from host PHP. | Reconfigure the MCP command through Sail/Docker when Boost database tools are needed. |
| The dashboard and product navigation began as starter placeholders. | Resolved for authenticated product surfaces: the dashboard is a query-free launchpad and starter repository/documentation links are removed. The public welcome/logo remain cosmetic follow-up work. | Do not couple cosmetic public-page work to domain architecture. |
| The generated welcome view contains a large inline style block and some starter components contain substantial inline Alpine behavior. | This differs from the repository guideline that new JS and CSS should live in dedicated assets or component files. | Do not copy this pattern into product features; migrate only when those views are touched. |
| Existing tests use RefreshDatabase while repository guidance prefers LazilyRefreshDatabase. | Current tests are valid but may become slower as the schema grows. | Adopt LazilyRefreshDatabase for new domain feature tests and migrate existing tests opportunistically. |

## 3. Architectural principles

### 3.1 Laravel first

Use routes, middleware, Livewire pages, Form Requests, policies, Eloquent, model factories, events, jobs, notifications, the service container, and database transactions as Laravel intends. Do not create custom replacements for the container, ORM, event dispatcher, or validation system.

### 3.2 Business operations have one entry point

Each state-changing use case has a focused action such as PlanDinner, ChangePlannedDinnerServings, CancelPlannedDinner, or MarkDinnerCooked. Delivery code calls the action; it does not reproduce the transaction or allocation algorithm.

### 3.3 Models own local behavior; actions own orchestration

An Eloquent model owns relationships, casts, scopes, and state rules local to that model. For example, PlannedDinner can assert that only a planned dinner may be cooked. An action owns a workflow spanning multiple models, such as locking pantry entries, consuming reservations, changing dinner status, and regenerating groceries.

### 3.4 Exact quantities have one canonical meaning

Every exact quantity is normalized to a compatible base unit for calculations while retaining the entered amount and unit for editing and display. Binary floats are prohibited in domain calculations and persisted quantity columns.

### 3.5 Derived data has an explicit source of truth

- Pantry total is persisted on PantryEntry.
- Reserved pantry quantity is the sum of active IngredientReservation rows.
- Available pantry quantity is total minus reserved.
- A planned requirement is a snapshot persisted for a PlannedDinner.
- Grocery generated quantities are rebuilt from planned requirements and reservations.
- Recommendation results are calculated on demand for the MVP and are not a second mutable source of truth.

### 3.6 Correctness is synchronous; side effects may be asynchronous

Reservation, consumption, requirement snapshots, and grocery regeneration complete synchronously within the user-facing use case. Email, analytics, image processing, or cache warming may later use after-commit events and queues. A missing queue worker must never make inventory incorrect.

### 3.7 Ownership and authorization are explicit

MVP catalogue and planning data belong to one User. Every aggregate-root query is user-scoped, and every mutation is policy-authorized. Epic 15 deliberately migrates shared catalogue, pantry, planning, reservation, and grocery aggregates to Household while favourites and other personal preferences remain user-owned. The transition uses concrete foreign keys and explicit policies/query scopes, not a polymorphic owner abstraction or an unreviewed global tenant scope.

### 3.8 Optimize only where the epics create pressure

Use eager loading, indexes, aggregate queries, pagination, and deterministic algorithms from the beginning. Do not add Redis, Horizon, a search engine, microservices, CQRS, event sourcing, or generic repositories before measurements demonstrate a need.

## 4. Chosen architectural style

### 4.1 Style

**MVP and post-MVP decision: Laravel-first modular monolith with feature-oriented namespaces, an action-oriented application layer, and a light domain model.**

The code stays in one Laravel application and one MySQL database. Conventional top-level directories remain recognizable. Within those directories, classes are grouped by feature where that improves navigation:

- App\Actions\Pantry\AddPantryStock
- App\Services\Measurements\UnitConverter
- App\Services\Recommendations\RecommendationEngine
- App\Policies\RecipePolicy
- resources/views/pages/recipes/⚡index.blade.php

This is not a strict layered or hexagonal implementation. Eloquent models remain both persistence models and domain entities where that is practical. Boundaries are enforced through dependency direction and focused public APIs, not through an interface around every class.

### 4.2 Why it fits

- The product is one cohesive workflow with strong transactional coupling.
- Laravel and Livewire already solve delivery, persistence, validation, authorization, and dependency injection.
- A small team can navigate conventional directories quickly.
- Pure calculation services and value objects isolate the risky parts without isolating the entire application from Laravel.
- The architecture supports all eight epics without paying the operational cost of distributed systems.

### 4.3 Alternatives considered

| Alternative | Why it was not selected | Reconsider when |
| --- | --- | --- |
| Full Clean Architecture with separate Domain, Application, Infrastructure, and adapter models | Duplicates models and mapping code before the domain is proven; fights Eloquent and slows the MVP. | Multiple delivery mechanisms or persistence technologies genuinely require isolation. |
| Package-per-module or nwidart-style modules | Adds service providers, package boundaries, and discovery overhead for tightly related modules. | Teams deploy modules independently or the monolith becomes difficult to navigate despite feature namespaces. |
| Microservices | Reservations, pantry stock, and grocery generation need one transaction today. Distributed consistency would be worse. | Independent scaling and team ownership outweigh transactional simplicity. |
| Transaction scripts directly in Livewire components | Fast initially, but duplicates rules across UI paths and makes race conditions difficult to test. | Not recommended; focused actions are the minimum useful boundary. |
| Rich domain model with no application actions | Cross-model transactions and Laravel delivery concerns would leak into entities. | Not recommended for the orchestration-heavy workflows here. |

### 4.4 Trade-off

The modular boundaries are conventions rather than physically enforced packages. Developers must review dependencies and keep feature code from reaching across modules arbitrarily. Larastan and focused tests provide feedback, but discipline remains necessary.

## 5. High-level system overview

~~~mermaid
flowchart TB
    Browser["Browser"]

    subgraph Presentation["Presentation"]
        Livewire["Livewire 4 pages and forms"]
        Controllers["HTTP controllers"]
        BladeFlux["Blade components and Flux UI"]
    end

    subgraph Application["Application orchestration"]
        Actions["Feature actions"]
        Queries["Focused query objects"]
        Policies["Policies and authorization"]
    end

    subgraph Domain["Domain capabilities"]
        Measurements["Ingredients and measurements"]
        Recipes["Recipe catalogue and scaling"]
        Pantry["Pantry balances and allocation"]
        Planning["Dinner plans and reservations"]
        Recommendations["Recommendation engine"]
        Groceries["Grocery calculator"]
    end

    subgraph Infrastructure["Laravel infrastructure"]
        Eloquent["Eloquent models"]
        Storage["Laravel filesystem"]
        Events["Events, listeners, jobs"]
        MySQL[("MySQL 8.4")]
    end

    Browser --> Livewire
    Browser --> Controllers
    Livewire --> BladeFlux
    Livewire --> Policies
    Controllers --> Policies
    Livewire --> Actions
    Controllers --> Actions
    Livewire --> Queries
    Controllers --> Queries

    Actions --> Measurements
    Actions --> Recipes
    Actions --> Pantry
    Actions --> Planning
    Actions --> Groceries
    Queries --> Recommendations

    Recipes --> Measurements
    Pantry --> Measurements
    Planning --> Recipes
    Planning --> Pantry
    Recommendations --> Recipes
    Recommendations --> Pantry
    Groceries --> Planning
    Groceries --> Measurements

    Actions --> Eloquent
    Queries --> Eloquent
    Eloquent --> MySQL
    Actions --> Storage
    Actions --> Events
~~~

The system is deployed as a web application plus MySQL. In development, Sail runs both containers. The browser uses regular HTTP and Livewire requests; there is no separate frontend API boundary.

## 6. Domain or module boundaries

### 6.1 Identity and Access

**Owns:** User authentication, email verification, password resets, passkeys, two-factor authentication, session security, and user-level authorization.

**Existing implementation:** Fortify, App\Models\User, App\Actions\Fortify, App\Providers\FortifyServiceProvider, account settings Livewire pages.

**Boundary:** Other modules may reference User as owner but must not reimplement authentication. Product policies determine whether a user may act on domain records.

### 6.2 Ingredients and Measurements

**Owns:** Ingredient catalogue entries, aliases, categories, staple and current-availability state, units, compatibility rules, package definitions, quantity parsing, normalization, arithmetic, and display formatting.

**Depends on:** Identity only for ownership.

**Used by:** Recipes, Pantry, Planning, Recommendations, and Groceries.

This is the lowest-level product module. It must not depend on recipes, pantry stock, or dinner plans.

### 6.3 Recipes

**Owns:** Recipes, ingredient lines, ordered steps, tags/categories, recipe images, archive state, default servings, and serving scaling.

**Depends on:** Ingredients and Measurements.

**Does not own:** Pantry matches, reservations, planned servings, or grocery requirements.

### 6.4 Pantry

**Owns:** User pantry entries, total stock, compatibility-aware balance queries, stock merging, and stock-allocation rules.

**Depends on:** Ingredients and Measurements.

**Exposes:** Available balances calculated as total stock minus active reservations. It does not decide which recipe to recommend.

### 6.5 Dinner Planning and Reservations

**Owns:** One rolling DinnerPlan per user, active PlannedDinner ordering, cooked/cancelled history, duplication/restoration, selected servings, optional date, recipe/requirement snapshots, priority-based allocation of pantry entries, shortfalls, reservation release, and cooking consumption.

**Depends on:** Recipes, Pantry, and Measurements.

This is the transactional center of the application. It coordinates rather than duplicating recipe and pantry rules.

### 6.6 Recommendations

**Owns:** Deterministic recipe matching, score calculation, match details, and sorting.

**Depends on:** Read-only recipe requirements, serving scaling, and current pantry availability.

**Does not own:** Pantry changes, reservations, dinner selections, or persisted recommendation rows.

### 6.7 Groceries

**Owns:** One grocery list for the rolling dinner plan, generated items, temporary generated-quantity overrides, manual items, check/change state, categories, source contributions, completed-item clearing, and regeneration.

**Depends on:** PlannedDinnerRequirement, IngredientReservation, Ingredients, and Measurements.

It consumes planning facts; it does not create reservations or change pantry stock.

### 6.8 Dependency direction

Dependencies point toward stable concepts:

~~~text
Identity
   └─ Ingredients and Measurements
        ├─ Recipes ───────────────┐
        ├─ Pantry ────────────────┼─ Dinner Planning and Reservations ─ Groceries
        └─ Recipes + Pantry ─ Recommendations
~~~

Recommendations and Groceries must not call each other. Cross-module mutations are coordinated by an application action rather than by circular model callbacks.

### 6.9 Epic coverage

| Epic | Primary module | Supporting components |
| --- | --- | --- |
| 1. Ingredient and Measurement System | Ingredients and Measurements | Quantity value object, QuantityInputParser, UnitConverter, QuantityFormatter, ingredient policies |
| 2. Recipe Catalogue | Recipes | Minimum fields, nullable metadata, RecipeForm, image placeholder/storage, archive/history snapshots |
| 3. Pantry Management | Pantry | Stock/availability actions, package-aware balances, full-priority allocator, reservations aggregate |
| 4. Recipe Serving Scaling | Recipes and Measurements | RecipeScaler using original amounts and fixed-precision arithmetic |
| 5. Pantry-Based Recommendations | Recommendations | RecommendationQuery, configurable Q/F/P/M/I engine, detailed explanations |
| 6. Dinner Plan Management | Dinner Planning | Rolling DinnerPlan, active/history actions, duplicate/restore, complete snapshots |
| 7. Ingredient Reservation Lifecycle | Dinner Planning and Pantry | Priority reconciliation, transactions/locks, confirmation, IngredientReservation |
| 8. Grocery List Generation | Groceries | GroceryCalculator, temporary overrides, increase-sensitive checks, stable identities/contributions |

Post-MVP coverage extends those modules rather than creating a parallel application:

| Epic | Primary module | Supporting components and baseline to reuse |
| --- | --- | --- |
| 9. MVP Quality and Architecture Review | Cross-cutting quality gate | Existing action/service boundaries, MySQL constraints, Stage 5 journey/concurrency/query tests, docs and runbooks |
| 10. Recipe Favourites | Personal Preferences and Recipes | User/Recipe pivot, focused favourite actions, recipe queries, recommendation tie-break |
| 11. Cooking and Dinner History | Dinner Planning | Existing PlannedDinner statuses/timestamps/snapshots and planning-from-history actions; add lifecycle evidence and filtered history query |
| 12. Decision Mode | Recommendations and Dinner Planning | Existing recommendation results/explanations, deterministic DecisionEngine, PlanDinner |
| 13. Recipe Tags and Dietary Filters | Recipes and Recommendations | Existing Tag pivot; add explicit structured dietary facts and one reusable eligibility/filter query |
| 14. Ingredient Substitutions | Recipes, Planning, Pantry, and Groceries | Explicit substitution rules, occurrence-level snapshots, full reservation and grocery reconciliation |
| 15. Shared Households | Identity and Access plus every shared aggregate | Household/membership/invitation policies, active context, explicit tenant scopes, expand/backfill/enforce migration |
| 16. Pantry Expiry Dates | Pantry and Dinner Planning | PantryStockBatch source of stock truth, batch reservations, earliest-expiry allocation, recommendation expiry factor |
| 17. Package Sizes and Supermarket Rounding | Ingredients, Measurements, and Groceries | Extend IngredientPackage; pure PackagePurchaseOptimizer; exact need remains separate |
| 18. Grocery Prices and Budgeting | Groceries and Recommendations | Integer-minor-unit Money, package price estimates, partial/unknown totals, optional plan budget |
| 19. Recipe Import | Recipes and Integrations | Import DTO/draft/review pipeline, ingredient matching, existing CreateRecipe action |
| 20. AI-Assisted Recipe Processing | Integrations and Recipe Import | RecipeTextExtractor contract, provider adapter, structured validation, same review pipeline |
| 21. Barcode Scanning | Integrations, Ingredients, and Pantry | Barcode mapping/provider, confirmed package mapping, existing pantry action/batch workflow |
| 22. Progressive Web App | Presentation and web delivery | Manifest, icons, versioned static shell cache, generic offline fallback; no offline mutations |
| 23. Public API | HTTP delivery | Versioned routes/controllers/requests/resources, Sanctum when approved, household-aware policies, existing actions and queries |

## 7. Request and data flow

### 7.1 Read flow

1. A Livewire page or controller receives a request.
2. Authentication middleware establishes the user.
3. The page/controller invokes a focused query or a simple user-scoped Eloquent query.
4. The query eager-loads only required relationships and returns models or explicit result data.
5. The Livewire page renders Blade and Flux components.
6. No database query is executed from a Blade expression.

Simple CRUD pages may query Eloquent directly when the query is short and used once. Recommendation results and aggregate pantry balances warrant query objects because their shape and performance are substantial.

### 7.2 Write flow

1. Livewire Form object or Form Request validates input structure and normalizes locale-specific text input.
2. A policy authorizes the requested operation.
3. The delivery layer passes validated primitives or small data objects to a feature action.
4. The action opens a transaction if multiple records or invariants are involved.
5. Rows are locked in a stable order when stock or reservation state can race.
6. Models and domain services enforce compatibility, arithmetic, and state invariants.
7. The action persists all authoritative and synchronous derived state.
8. The transaction commits or fully rolls back.
9. Meaningful after-commit events may notify non-critical listeners.
10. The delivery layer redirects, navigates, or shows a Flux toast.

### 7.3 Typical mutation flow

~~~mermaid
sequenceDiagram
    actor User
    participant Page as Livewire page
    participant Form as Livewire Form
    participant Policy as Laravel policy
    participant Action as Feature action
    participant Domain as Domain services
    participant DB as MySQL
    participant Event as After-commit event

    User->>Page: Submit validated intent
    Page->>Form: Validate and normalize input
    Form-->>Page: Validated data
    Page->>Policy: Authorize operation
    Policy-->>Page: Allowed
    Page->>Action: handle(data, user, model)
    Action->>DB: Begin transaction
    Action->>DB: Lock affected rows in stable order
    Action->>Domain: Enforce invariants and calculate
    Domain-->>Action: Exact result objects
    Action->>DB: Persist authoritative state
    Action->>DB: Persist synchronous derived state
    DB-->>Action: Commit
    Action->>Event: Dispatch business fact after commit
    Action-->>Page: Model or result
    Page-->>User: Redirect, navigation, or toast
~~~

Authorization is repeated at the server action boundary when an action may be invoked outside the original UI. UI visibility is convenience, not security.

## 8. Laravel directory and namespace structure

### 8.1 Proposed tree

Only directories needed by the current milestone should be created.

~~~text
app/
├── Actions/
│   ├── Fortify/                         existing
│   ├── Ingredients/
│   ├── Recipes/
│   ├── Pantry/
│   ├── DinnerPlans/
│   └── Groceries/
├── Data/
│   ├── Measurements/
│   ├── Recommendations/
│   └── Groceries/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/Api/V1/                Epic 23 only
├── Jobs/                                only when a real async task exists
├── Listeners/                           only with a concrete event consumer
├── Livewire/
│   ├── Actions/                         existing Logout action
│   └── Forms/
├── Models/
├── Notifications/                       only when product notifications exist
├── Policies/
├── Queries/
├── Rules/
├── Services/
│   ├── Measurements/
│   ├── Recipes/
│   ├── Pantry/
│   ├── Recommendations/
│   └── Groceries/
├── ValueObjects/
└── Providers/

config/
├── dinner-decider.php
├── measurements.php
└── recommendations.php

resources/views/
├── components/
├── layouts/
└── pages/
    ├── ingredients/
    ├── recipes/
    ├── pantry/
    ├── recommendations/
    ├── dinner-plan/
    └── groceries/

routes/
├── web.php
├── settings.php
└── product.php

tests/
├── Unit/
│   ├── Measurements/
│   ├── Recipes/
│   ├── Pantry/
│   ├── Recommendations/
│   └── Groceries/
├── Feature/
│   ├── Ingredients/
│   ├── Recipes/
│   ├── Pantry/
│   ├── Recommendations/
│   ├── DinnerPlans/
│   └── Groceries/
└── Integration/
    └── Reservations/
~~~

The proposed routes/product.php is included from routes/web.php. This keeps the existing auth/settings routes stable without introducing a custom route loader.

### 8.2 Directory responsibilities

| Directory | Responsibility | Belongs here | Must not go here | Project example |
| --- | --- | --- | --- | --- |
| app/Actions/{Feature} | One state-changing application use case and its transaction boundary | Orchestration, calls to policies/services/models, transaction and locks | Reusable arithmetic, HTTP response formatting, generic helper methods | App\Actions\DinnerPlans\MarkDinnerCooked |
| app/Data/{Feature} | Immutable input and result shapes where nested arrays become unclear | QuantityInput, IngredientMatch, RecommendationResult, AllocationResult | Eloquent queries, persistence, service-container lookups | App\Data\Recommendations\IngredientMatch |
| app/Enums | Closed sets with domain meaning | UnitCode, MeasurementGroup, PlannedDinnerStatus, GroceryItemSource | User-editable catalogue data or translated labels with database identity | App\Enums\UnitCode |
| app/Events | Meaningful facts that have a concrete consumer | DinnerCooked, RecipeArchived, if and when listeners need them | Commands such as RecalculateEverything or correctness-critical orchestration | App\Events\DinnerCooked |
| app/Exceptions | Named domain/application failures | IncompatibleUnits, InvalidDinnerTransition, ActiveReservationsPreventRemoval | Validation bags, swallowed infrastructure errors | App\Exceptions\DinnerAlreadyProcessed |
| app/Http/Controllers | Traditional HTTP endpoints and future API adapters | Image response/upload endpoints, future API controllers | Allocation, scaling, recommendation, or grocery algorithms | App\Http\Controllers\RecipeImageController |
| app/Http/Requests | Validation and authorization for controller requests | StoreRecipeRequest for a future API or non-Livewire endpoint | Business transactions and model creation | App\Http\Requests\Recipes\StoreRecipeRequest |
| app/Http/Resources/Api/V1 | Future JSON representation layer | Versioned API resources if a mobile/public API is approved | Current Livewire view models | App\Http\Resources\Api\V1\RecipeResource |
| app/Jobs | Retriable work that may finish after the response | Future image processing or external recipe import | Reservation, cooking, or grocery correctness | App\Jobs\ProcessRecipeImage |
| app/Listeners | Side-effect reaction to a business fact | Cache invalidation, analytics, queued notification dispatch | Core state transitions that must share the initiating transaction | App\Listeners\RecordDinnerCookedMetric |
| app/Livewire/Forms | Complex Livewire form state and validation | Nested recipe ingredients, pantry quantity input | Eloquent querying unrelated to the form or cross-model transactions | App\Livewire\Forms\RecipeForm |
| app/Models | Eloquent persistence entities with relationships, casts, scopes, and local behavior | Recipe, PantryEntry, PlannedDinner, GroceryItem | Large cross-aggregate workflows or view formatting | App\Models\IngredientReservation |
| app/Notifications | User-facing Laravel notifications | Future dinner reminders or failed import notices | Domain events or synchronous inventory rules | App\Notifications\DinnerReminder |
| app/Policies | Model/resource authorization | Ownership and allowed actions by status | Input validation or query construction | App\Policies\RecipePolicy |
| app/Queries | Reusable or complex read models | Eager-loaded recipe search, pantry balances, recommendation input loading | Writes, hidden transactions, or generic repository CRUD | App\Queries\RecipeRecommendationsQuery |
| app/Rules | Reusable Laravel validation rules | CompatibleUnitForIngredient, PositiveDecimalQuantity | Conversion algorithms or database writes | App\Rules\CompatibleUnitForIngredient |
| app/Services/Measurements | Pure or focused measurement algorithms | Parsing, normalization, compatibility, formatting | Eloquent writes or request access | App\Services\Measurements\UnitConverter |
| app/Services/Recipes | Reusable recipe calculations | Scaling an immutable requirement set | Recipe CRUD orchestration | App\Services\Recipes\RecipeScaler |
| app/Services/Pantry | Stock matching and allocation algorithms | Build an AllocationResult from requirements and balances | Opening transactions or rendering UI | App\Services\Pantry\PantryAllocator |
| app/Services/Recommendations | Deterministic scoring and explanations | RecommendationEngine | Querying the authenticated user from globals | App\Services\Recommendations\RecommendationEngine |
| app/Services/Groceries | Deterministic grouping and grocery calculation | GroceryCalculator | Persisting manual items or authorizing users | App\Services\Groceries\GroceryCalculator |
| app/ValueObjects | Immutable concepts with equality and invariants | Quantity, CompatibilityKey | Eloquent identity, service-container dependencies, mutable state | App\ValueObjects\Quantity |
| config/dinner-decider.php | MVP presentation conventions | Europe/Amsterdam display timezone, DD-MM-YYYY, 24-hour time, Monday week start | Per-user mutable preferences or translated content | display_timezone |
| config/measurements.php | Environment-independent measurement policy | Calculation scale, fraction display thresholds, supported defaults | User-specific ingredients or mutable unit records | Calculation scale of 6 |
| config/recommendations.php | One source for recommendation factors | Quantity/full/partial/missing/incompatible weights | User-specific rankings or database queries | quantity_coverage weight of 60 |
| resources/views/pages/{feature} | Livewire 4 page components using existing SFC convention | Page state, calls to forms/actions/queries, Flux composition | Business algorithms and unscoped database access | resources/views/pages/pantry/⚡index.blade.php |
| tests/Unit/{feature} | Fast isolated domain tests | Quantity, scaler, allocator, scoring, formatter tests | HTTP or database assertions | QuantityTest |
| tests/Feature/{feature} | User-visible behavior through Laravel/Livewire | Validation, policy, persistence, page interactions | Direct testing of private implementation details | PlanDinnerTest |
| tests/Integration/Reservations | MySQL-specific transaction behavior | Locking, idempotency, decimal persistence | General UI assertions | CookDinnerTransactionTest |

### 8.3 Naming conventions

- Product actions use a verb phrase and a public handle method: PlanDinner::handle.
- Models are singular; tables are plural snake_case.
- Feature directories use plural domain names where natural.
- Backed enum cases use TitleCase, for example PlannedDinnerStatus::Planned.
- User-facing routes are plural and named with dot notation, for example recipes.index.
- Livewire pages follow the installed pages namespace and lightning-bolt naming.
- Existing package-mandated methods such as Fortify CreateNewUser::create remain unchanged.

## 9. Responsibilities of each layer or component

### 9.1 Livewire pages

Livewire pages are delivery adapters. They:

- Hold UI state or delegate complex state to a Livewire Form.
- Validate at submission and optionally on blur for user feedback.
- Authorize every mutation.
- Load display data through user-scoped Eloquent queries or query objects.
- Call exactly one top-level action for a business mutation.
- Translate expected domain failures into field errors or Flux callouts.
- Render with Flux free components where available.

They do not open database transactions, calculate conversions, allocate stock, or regenerate grocery rows directly.

Small page components remain SFCs. A large component with significant PHP plus component-specific JS/CSS should be converted to a Livewire 4 multi-file component rather than growing one very large Blade file.

### 9.2 HTTP controllers

Controllers are used only where HTTP semantics add value: a future JSON API, file delivery, or non-Livewire form endpoint. Controller methods stay thin, use Form Requests, authorize resources, call actions/queries, and return redirects, views, or API Resources.

The application does not create controllers merely to place a class between a Livewire page and an action.

### 9.3 Form Requests and Livewire Forms

- Form Requests are for controller-driven requests.
- Livewire Forms are for complex Livewire state such as a recipe with ordered ingredient rows.
- Simple Livewire pages may define a rules method locally.
- Both parse supported decimal, simple-fraction, mixed-fraction, and common Unicode-fraction input before creating QuantityInput.
- Both validate structure and ownership references, then the action/domain layer rechecks business invariants.

Validated arrays are acceptable for small flat inputs. Data objects are used when nested quantity/ingredient rows would otherwise require undocumented array shapes across several layers.

### 9.4 Actions

Actions define application use cases. They:

- Receive explicit User/model/data arguments.
- Use constructor injection.
- Authorize defensively when callable from more than one adapter.
- Open transactions for multi-record changes.
- Lock rows when inventory can race.
- Call pure calculation services.
- Persist state through Eloquent.
- Return a model or result object.

There is no generic IngredientService or DinnerPlanManager. Class names describe concrete operations.

### 9.5 Eloquent models

Models define:

- Fillable/hidden attributes following the current attribute-based model convention.
- Typed casts, including backed enums, immutable dates, booleans, and decimal strings.
- Relationships with return types.
- Reusable local scopes.
- Local state questions and transitions.
- Computed quantities that do not query unexpectedly in a loop.

Models do not reach into the authenticated session, render UI, or coordinate unrelated aggregate roots.

### 9.6 Domain services and value objects

Services exist for algorithms that do not naturally belong to one entity:

- QuantityInputParser
- UnitConverter
- QuantityFormatter
- RecipeScaler
- PantryAllocator
- RecommendationEngine
- GroceryCalculator

They accept explicit inputs and return values/result objects. Their unit tests need no HTTP request and, wherever possible, no database.

### 9.7 Queries

Query objects are selective, not a repository layer. They are introduced for:

- Recommendation candidate loading and scoring input.
- Pantry totals/reserved/available aggregation.
- Grocery list display with dinner contributions.
- Recipe search with categories/tags and pagination.

Simple model lookups remain in the calling page/action. Queries must eager-load required relationships, select only needed columns where useful, and apply explicit ordering.

### 9.8 Repositories

**MVP decision: no Eloquent repository interfaces.**

Eloquent already provides persistence abstraction, relationships, scopes, factories, and transaction integration. Wrapping every model in CRUD repositories would duplicate Laravel and obscure query capabilities.

A repository or gateway interface is justified only at an external boundary, for example a future RecipeImportSource implemented by multiple remote providers. Reconsider if a module must support a second persistence mechanism or complex persistence operations need a stable interface used by multiple callers.

### 9.9 API resources and view models

No API layer is required for the MVP. Livewire pages can render Eloquent models or purpose-built result data. RecommendationResult and IngredientMatch are domain/read result objects, not JSON API resources.

When Epic 23's API dependency and client contract are approved, add versioned controllers and Eloquent API Resources under App\Http\Resources\Api\V1, reusing the same actions and policies.

## 10. Domain model overview

### 10.1 Terminology

- **Recipe** is the reusable definition: name, default servings, instructions, and ingredients.
- **PlannedDinner** is one selected occurrence of a Recipe for a chosen serving count.
- **Dish** is acceptable UI language but is not a separate model. Creating both Dish and Recipe would duplicate the same concept.
- **PantryEntry** is a user-owned stock balance in one compatible representation.
- **Ingredient availability** uses two separate fields on the user-owned Ingredient: is_staple describes normal household behaviour and is_currently_available is the temporary availability override. The state is not duplicated on PantryEntry.
- **IngredientReservation** allocates part of a PantryEntry to one planned requirement.
- **PlannedDinnerRequirement** is an immutable-at-selection snapshot of what that dinner needs.
- **IngredientPackage** is an architecture term introduced for the reusable package definition implied by the epic, such as one can containing 400 g.

Availability truth table:

| is_staple | is_currently_available | Calculation behaviour |
| --- | --- | --- |
| true | true | Assume covered without exact stock; do not reserve/deduct/generate. |
| true | false | Treat as missing; generate exact shortfall or Required non-exact check. |
| false | true | Use normal PantryEntry totals/reservations. |
| false | false | Ignore recorded stock temporarily and treat requirements as missing. |

### 10.2 Entity relationships

~~~mermaid
erDiagram
    USER ||--o{ INGREDIENT : owns
    USER ||--o{ RECIPE : owns
    USER ||--o{ PANTRY_ENTRY : owns
    USER ||--|| DINNER_PLAN : has

    INGREDIENT ||--o{ INGREDIENT_ALIAS : has
    INGREDIENT ||--o{ INGREDIENT_PACKAGE : defines
    INGREDIENT ||--o{ RECIPE_INGREDIENT : referenced_by
    INGREDIENT ||--o{ PANTRY_ENTRY : stocked_as

    RECIPE ||--o{ RECIPE_INGREDIENT : contains
    RECIPE ||--o{ RECIPE_STEP : contains
    RECIPE }o--o{ RECIPE_CATEGORY : categorized_by
    RECIPE }o--o{ TAG : tagged_with

    DINNER_PLAN ||--o{ PLANNED_DINNER : contains
    RECIPE ||--o{ PLANNED_DINNER : selected_as
    PLANNED_DINNER ||--o{ PLANNED_DINNER_REQUIREMENT : snapshots
    PLANNED_DINNER_REQUIREMENT ||--o{ INGREDIENT_RESERVATION : allocated_by
    PANTRY_ENTRY ||--o{ INGREDIENT_RESERVATION : supplies

    DINNER_PLAN ||--|| GROCERY_LIST : generates
    GROCERY_LIST ||--o{ GROCERY_ITEM : contains
    GROCERY_ITEM ||--o{ GROCERY_ITEM_CONTRIBUTION : explains
    PLANNED_DINNER_REQUIREMENT ||--o{ GROCERY_ITEM_CONTRIBUTION : contributes
~~~

### 10.3 Core entities and invariants

| Entity | Important data | Invariants and behavior |
| --- | --- | --- |
| User | Existing identity/auth fields | Owns all MVP aggregate roots; product data is inaccessible to other users. |
| Ingredient | user_id, name, normalized_name, category, preferred group/unit, is_staple, is_currently_available, archived_at | Name is unique per user after normalization; preferred unit must match its group. Defaults are non-staple/currently available. An available staple needs no exact stock; unavailable state masks stock without deleting it. |
| IngredientAlias | ingredient_id, name, normalized_name | Alias is unique within the owning ingredient/user catalogue and resolves to exactly one Ingredient for that user. |
| IngredientPackage | ingredient_id, package type, label, content amount/unit/normalized amount nullable | Known contents must be mass or volume compatible; unknown packages compare only by the same package definition ID. |
| Recipe | user_id, name, description, default_servings, times, difficulty, cuisine, meal_type, notes, image_path, source_url, archived_at | Name/default servings/one ingredient/one step are required. Other metadata is nullable without invented defaults. Archived recipes are excluded from editing/recommendations but may be planned through the archive/history flow using snapshots. |
| RecipeIngredient | recipe_id, ingredient_id, quantity type, entered amount/unit, normalized amount, compatibility key, package ID, description, non_exact_status, position | Exact rows require positive amount and compatible unit. Non-exact rows require a description and NonExactStatus::Required or Optional, with no numeric calculation amount. |
| RecipeStep | recipe_id, instruction, position | Position is unique within recipe; blank steps are rejected. |
| PantryEntry | user_id, ingredient_id, display unit, total normalized amount, compatibility key, package ID, merge key | Total is non-negative; any reduction/removal reconciles reservations so their sum never exceeds the resulting total; compatible additions merge according to representation-aware merge key. |
| DinnerPlan | user_id | Exactly one rolling MVP list per user; there is no week/name/current-plan selector. Planned rows form the active list and Cooked/Cancelled rows form history. |
| PlannedDinner | dinner_plan_id, recipe_id nullable, recipe name/metadata snapshot, servings, planned_date, status, position, cooked_at, cancelled_at, restored_at | Same recipe/snapshot may appear more than once; servings is positive; Planned may become Cooked or Cancelled, Cancelled may be restored to Planned through a reallocation action, and Cooked is terminal. |
| PlannedDinnerRequirement | planned_dinner_id, source recipe ingredient nullable, ingredient/display/base/scaled snapshots, compatibility key, missing amount, unresolved_at_cooking, unresolved reason | Snapshot survives recipe edits/archive/deletion policy changes; missing is never negative; non-exact rows have Required/Optional status and no exact reservation. |
| IngredientReservation | requirement_id, pantry_entry_id, normalized amount | Amount is positive and compatible; one requirement/entry pair is unique; summed reservations never exceed pantry total. |
| GroceryList | dinner_plan_id, regenerated_at | Exactly one for the user's rolling DinnerPlan. |
| GroceryItem | grocery_list_id, ingredient_id nullable, source, generation_key nullable, calculated amount/description, temporary override amount/unit, is_manually_adjusted, category, checked_at, previous amount, quantity_increased_at | Generated and manual items are distinguishable. Generated overrides clear on recalculation; an increased calculated quantity unchecks the row and records enough change state to explain why. |
| GroceryItemContribution | grocery_item_id, requirement_id, normalized contribution nullable | Explains which planned dinner created each grocery quantity/check item. |

The rolling DinnerPlan is a conceptual singleton and has a unique user_id. EnsureDinnerPlan creates it idempotently during first product onboarding/access (and may also be called after registration) so existing starter users are supported without a schema migration that mixes data backfill into DDL.

### 10.4 Quantity value concepts

#### Quantity

App\ValueObjects\Quantity represents only an exact measurable quantity. It contains a decimal string, UnitCode, MeasurementGroup, CompatibilityKey, and normalized base amount. It is immutable and supports compare, add, subtract, and scale only when compatibility is proven.

The value object never silently converts:

- mass to volume,
- count units to mass,
- whole items to slices,
- cloves to bulbs,
- exact values to non-exact descriptions.

#### Non-exact requirement

“Salt to taste” is not represented as Quantity with zero. RecipeIngredient and PlannedDinnerRequirement use QuantityType::NonExact plus a required description. This keeps non-exact rows out of recommendation scoring and exact reservations while allowing explanatory display and grocery check items.

App\Enums\NonExactStatus has exactly Required and Optional. Required non-exact ingredients are covered by an available staple or positive pantry presence while the ingredient is currently available; otherwise they generate a grocery check and may appear in unresolved-cooking confirmation. Optional ingredients do not count as missing, affect scoring, or generate groceries; the UI may show them separately and users may add them manually.

#### CompatibilityKey

CompatibilityKey identifies a calculation bucket:

- mass
- volume
- count:{ingredient_id}:piece
- count:{ingredient_id}:clove
- count:{ingredient_id}:bulb
- count:{ingredient_id}:slice
- count:{ingredient_id}:leaf
- count:{ingredient_id}:stalk
- count:{ingredient_id}:sprig
- package:{ingredient_package_id} for a package without known metric contents

Every comparison also requires the same Ingredient. Count compatibility explicitly includes ingredient plus semantic unit, so tomato pieces cannot match another ingredient's pieces and tomato slices cannot match tomato pieces. Known packages normalize to their content compatibility key for calculation while retaining package identity for display. For example, a 400 g can of tomatoes participates in tomato-mass comparisons but can still render as “1 can — 400 g”.

#### Unit definitions

UnitCode and MeasurementGroup are PHP backed enums because MVP units are a closed, code-governed set. Conversion factors and display metadata are code/configuration, not user-editable database rows. IngredientPackage remains data because package sizes are user/catalogue definitions.

Supported initial units:

- Mass: mg, g, kg; base g.
- Volume: ml, l, tsp, tbsp; base ml; 5 ml per tsp and 15 ml per tbsp.
- Semantic count: piece, clove, bulb, slice, leaf, stalk, sprig; each is ingredient-specific and not convertible to another count unit or mass.
- Package type labels: can, jar, pack, bag, bottle.

Adding a genuinely user-configurable unit catalogue is a future option. Do not create database-managed conversion formulas for the MVP.

#### Display policy

QuantityFormatter retains full calculation precision but normally renders at most two decimal places and removes trailing zeroes. Common fractions may be used for count/package display. Recipe and pantry views show meaningful known-package context plus normalized metric content, for example “2 cans × 400 g — 800 g total”. Generated groceries lead with the exact metric requirement and may add source context such as “equivalent to 1.5 × 400 g cans”; they never round up to supermarket packages.

## 11. Database and persistence strategy

### 11.1 MySQL and Eloquent

MySQL remains the authoritative store. Eloquent is used directly through models, relationships, scopes, and focused queries. Every aggregate root has user ownership either directly or through a required parent.

Use Laravel migrations generated by Artisan, foreign keys, reversible down methods, and indexes matching actual filters, joins, uniqueness, and ordering.

### 11.2 Decimal representation

**MVP decision: DECIMAL(18,6) columns plus decimal strings and BCMath in PHP.**

- Never cast a domain amount to float or double.
- Eloquent decimal casts return strings and may be used for persistence access.
- App\ValueObjects\Quantity performs arithmetic using BCMath at calculation scale 6.
- Add ext-bcmath to Composer platform requirements when the measurement implementation begins; it is already present in the Sail PHP 8.5 image.
- Round only at explicit domain boundaries using one documented half-up policy.
- Re-scaling always starts from the stored base recipe amount, not a previously scaled result.

Six decimal places support mg normalized to g, common cooking fractions, and repeated serving ratios while remaining readable. Reconsider the scale only if real inputs require more precision; changing scale requires a migration and calculation test review.

### 11.3 Entered, display, and normalized fields

An exact RecipeIngredient stores:

- entered_amount: the canonical decimal string produced from the user's decimal or fraction input;
- entered_unit: the selected UnitCode;
- normalized_amount: the base calculation amount;
- compatibility_key: the bucket used for safe comparison;
- ingredient_package_id: an optional package definition.

PlannedDinnerRequirement snapshots the source display metadata plus its base and scaled normalized values. IngredientReservation stores the exact normalized allocated amount and compatibility metadata; its related requirement, pantry entry, and formatter provide readable display.

PantryEntry is an aggregate balance, so it stores total_normalized_amount, compatibility_key, merge_key, and a compatible display_unit. Direct compatible quantities merge into a metric/count balance: after adding 1 kg and 500 g there is one 1,500 g balance. Package entries use IngredientPackage in the merge key, including known packages, so two 400 g cans can remain “2 cans × 400 g” while allocation aggregates their 800 g with other compatible tomato-mass entries. Different known package definitions remain separate display rows but jointly satisfy metric requirements. Unknown packages match only the same package definition. Preserving individual purchase lots would require a ledger/lot model, which expiry/audit requirements do not justify for the MVP.

The server derives all normalized and merge fields; clients never submit them as trusted values. The intentional duplication on recipe/snapshot rows preserves understandable editing, while normalized values make comparisons and indexes practical.

### 11.4 Table outline and constraints

This is a design outline, not migration code.

| Table | Key constraints and indexes |
| --- | --- |
| ingredients | FK user_id cascade; unique user_id + normalized_name; index user_id + archived_at; is_staple default false/is_currently_available default true mirrored in model attributes; boolean indexes only if measured |
| ingredient_aliases | FK ingredient_id cascade; unique ingredient_id + normalized_name |
| ingredient_packages | FK ingredient_id cascade; index ingredient_id + package_type; positive content check when content exists |
| recipes | FK user_id cascade; index user_id + archived_at + name; positive default_servings check |
| recipe_ingredients | FK recipe_id cascade; FK ingredient_id restrict; unique recipe_id + position; index ingredient_id; exact/non-exact check constraints where practical |
| recipe_steps | FK recipe_id cascade; unique recipe_id + position |
| recipe_categories and tags | FK user_id cascade; unique user_id + normalized_name |
| category_recipe and recipe_tag | Composite primary/unique keys and cascading parent FKs |
| pantry_entries | FK user_id cascade; FK ingredient_id restrict; unique user_id + ingredient_id + merge_key; index user_id + compatibility_key |
| dinner_plans | FK user_id cascade; unique user_id for MVP |
| planned_dinners | FK dinner_plan_id cascade; nullable FK recipe_id null on permanent recipe deletion; snapshot columns required; indexes plan_id + status + planned_date + position and plan_id + status + created_at |
| planned_dinner_requirements | FK planned_dinner_id cascade; nullable source recipe ingredient FK null on delete; snapshot/non-exact/unresolved-at-cooking columns; index ingredient_id + compatibility_key |
| ingredient_reservations | FK requirement_id cascade; FK pantry_entry_id restrict; unique requirement_id + pantry_entry_id; index pantry_entry_id |
| grocery_lists | FK dinner_plan_id cascade; unique dinner_plan_id |
| grocery_items | FK grocery_list_id cascade; nullable ingredient FK restrict; unique list_id + source + generation_key for generated rows; calculated/override/change columns; indexes list_id + checked_at and category |
| grocery_item_contributions | FK grocery_item_id cascade; FK requirement_id cascade; unique item_id + requirement_id |

MySQL nullable uniqueness must be considered when implementing manual grocery items. Generated rows should always have a non-null generation_key; manual rows may have null and are not deduplicated automatically.

### 11.5 Ownership strategy

For the MVP, Ingredient, Recipe, PantryEntry, and DinnerPlan are directly user-owned. Children inherit ownership through required parents. Queries use explicit ownership constraints such as whereBelongsTo($user) or parent-scoped bindings; a hidden global user scope is avoided because it can conceal data in maintenance jobs and tests.

Epic 15 adds Household and membership models, creates one default household per existing user, backfills household_id, and then updates policies and queries through the migration contract in Section 25.10. A polymorphic owner_type/owner_id remains rejected because it would weaken concrete foreign keys and complicate every ownership query.

### 11.6 Archive and delete behavior

- Recipes and Ingredients use explicit archived_at business state rather than destructive deletion when referenced.
- Archived recipes are excluded from ordinary editing/recommendations. Archive/history actions may snapshot an archived recipe into a new dinner, duplicate a historical occurrence, or restore the recipe to the catalogue.
- PlannedDinner stores recipe metadata and requirement snapshots so history remains accurate if the source recipe is archived or later permanently removed.
- Permanent recipe deletion is not a normal MVP UI operation. Any future administrative deletion removes its image through Storage and nulls snapshot source references without destroying dinner history.
- PlannedDinner cancellation retains the occurrence and releases reservations.
- Direct PantryEntry deletion is rejected while reservations exist; RemovePantryEntry requires confirmation, releases/reconciles affected reservations, then deletes safely.
- Self-service account deletion, export, and configurable retention are postponed. A future explicit deletion/anonymisation action must handle files and owned rows transactionally; the schema must not make export or anonymisation impossible.
- Deployed migrations are immutable; changes use new migrations.

### 11.7 Transactions and locking

Use DB::transaction with a small deadlock retry count for reservation-sensitive actions. Use pessimistic lockForUpdate for:

- the PlannedDinner being changed/cooked and all affected active Planned rows,
- affected PantryEntry rows,
- existing reservations being released or consumed,
- the rolling DinnerPlan/GroceryList when regeneration writes generated items.

Lock rows in ascending primary-key order to reduce deadlocks. Never call an external HTTP service or send mail while holding the transaction.

The following operations are transaction boundaries:

- Add, reduce, or remove pantry stock and change ingredient availability/staple state.
- Plan from catalogue/archive/history or duplicate a dinner.
- Change planned servings, date, or rolling-list position.
- Cancel, restore, or remove a planned dinner.
- Mark a dinner cooked.
- Regenerate generated grocery items when invoked by those operations.

Simple recipe/ingredient CRUD may use ordinary Eloquent writes unless multiple dependent rows must be replaced atomically.

### 11.8 Persistence trade-offs

Storing normalized and display values adds columns and requires disciplined writes. The alternative—normalizing every query from arbitrary entered units—would make aggregate matching, locking, and indexing error-prone. All creation/update actions therefore centralize quantity persistence so the representations cannot drift.

## 12. Validation and error-handling strategy

Validation has two complementary levels. Boundary validation tells a user whether submitted data has the right shape. Domain validation prevents invalid state regardless of whether an action was called from Livewire, an Artisan command, a test, or a future API.

### 12.1 Boundary validation

- Livewire screens use dedicated App\Livewire\Forms form objects once a form has more than a few fields or is reused. Small single-field interactions may validate directly in the component.
- Conventional HTTP endpoints use App\Http\Requests Form Requests. Controllers must call validated or safe rather than pass request input wholesale.
- Validation rules reference enums and model ownership. An ID existing in a table is not enough; the record must also belong to the authenticated user when it is user-owned.
- QuantityInputParser accepts decimal comma or point, simple fractions such as 1/2, mixed fractions such as 1 1/2, and supported Unicode fraction glyphs. It rejects division by zero, ambiguous thousands separators, unsupported expressions, and values that exceed calculation precision.
- Every collection has explicit maximum sizes, and every string has an explicit maximum length.
- Public error messages are translatable and identify the field or operation without revealing SQL, stack traces, or other users' records.

Representative rules include:

| Input | Boundary rules |
| --- | --- |
| Ingredient | Required name, normalized per-user uniqueness, compatible preferred unit, is_staple, and is_currently_available booleans |
| Exact quantity | Positive parseable decimal/fraction input, normalized scale no greater than six, supported unit, ingredient/unit compatibility |
| Non-exact quantity | Explicit non-exact type, required description, Required/Optional status; amount and unit must be absent |
| Package quantity | Positive package count plus an IngredientPackage belonging to the same ingredient |
| Recipe | Required name, positive default servings, at least one ingredient and one instruction; optional metadata remains nullable; unique positive positions |
| Pantry adjustment | Non-negative resulting total and compatible unit/package; reductions/removals require locked reservation reconciliation and explicit removal confirmation where applicable |
| Planned dinner | Owned active recipe, authorized archived recipe/archive snapshot, or stored historical snapshot; positive servings, optional date, allowed state/action transition |
| Generated grocery edit | Positive amount and compatible unit for the generated ingredient/key; writes only temporary override fields |

Recipe description, preparation/cooking time, cuisine, meal type, difficulty, categories, tags, notes, image, and source URL are optional. Missing metadata stays null/unknown: it does not block recommendations and is not assigned a guessed default, but the recipe cannot match a filter for a value it lacks. A missing image uses a standard presentation placeholder rather than a fake stored path.

The client may provide immediate feedback, but server-side validation remains authoritative.

### 12.2 Domain invariants

Value objects, model methods, and actions re-check invariants that matter to stored state. Examples are:

- Quantity never contains a float, zero/negative amount, unsupported scale, or incompatible measurement group.
- PlannedDinner follows Planned to Cooked or Cancelled; Cancelled may return to Planned only through RestoreCancelledDinner, while Cooked remains terminal.
- A reservation never exceeds the entry's available quantity.
- Cooking consumes each reservation at most once.
- An archived recipe is not editable/recommended or selectable from the ordinary active catalogue, but archive/history actions may snapshot it into a new occurrence without depending on later live changes.
- A recipe requirement and an ingredient package must reference the same ingredient.

Do not duplicate all UI rules in every layer. Re-check only rules required for correctness or security, and express calculations through shared value objects/services.

### 12.3 Exception and user-error policy

Expected business conflicts use small, named exceptions in App\Exceptions, such as InvalidDinnerTransition, PantryRemovalRequiresConfirmation, or UnresolvedRequirementsRequireConfirmation. The calling Livewire component converts these into a form, modal, or banner result. Authorization failures remain AuthorizationException responses, missing owned records remain 404 where appropriate, and unexpected failures are allowed to reach Laravel's exception handler and logs.

| Failure | User response | Log level |
| --- | --- | --- |
| Invalid input | Field-level 422-style validation feedback | None |
| Stale pantry availability during selection | Clear retry/change-servings message | Notice or structured info |
| Unresolved cook without current confirmation | Structured confirmation modal with missing/incompatible/unchecked summary | None |
| Forbidden owned resource | 403, or 404 when concealing existence | Security context at warning only if suspicious/repeated |
| Deadlock after configured retries | Generic retry message | Error with operation IDs |
| Integration/queue failure | Non-blocking status and retry where possible | Error |
| Programming/database failure | Generic error page; no internals | Error with trace |

Actions must not catch Throwable merely to return false. A transaction rolls back on an exception; the presentation boundary decides how an expected exception is displayed.

## 13. Authentication and authorization strategy

### 13.1 Authentication

Laravel Fortify remains the authentication backend and the existing Livewire/Flux pages remain the UI. The present features—registration, password reset, password confirmation, passkeys, and two-factor authentication—are compatible with this architecture and should not be replaced.

Email verification is enabled in config/fortify.php, App\Models\User implements Illuminate\Contracts\Auth\MustVerifyEmail, and the dashboard route uses verified middleware. Feature coverage proves an unverified user is redirected to the verification notice.

MVP product routes require auth and verified middleware. Sensitive account operations continue to use password confirmation and Fortify's existing rate limits.

### 13.2 Authorization

Use Laravel policies for Ingredient, Recipe, PantryEntry, DinnerPlan, PlannedDinner, and GroceryList. Ownership is checked through the aggregate root:

- ingredient, recipe, pantry entry, and dinner plan: user_id equals the current user's ID;
- planned dinner: its DinnerPlan belongs to the user;
- grocery list/item: its DinnerPlan/GroceryList belongs to the user;
- recipe requirement or reservation: authorize through its owned parent rather than creating public routes for child IDs.

Livewire public methods call authorize before loading or mutating protected state. HTTP controllers use authorize or policy-aware route binding. Actions accept an already authorized model/user context but still scope all lookups by owner so a missed presentation check cannot turn a foreign ID into a mutation.

Do not use an implicit global user scope on every model. Explicit owner relationships and query constraints are easier to audit, test, and later adapt to households. Do not add roles or a permissions package for the single-user MVP.

### 13.3 Post-MVP shared households

Shared households are outside the implemented MVP and are approved for Epic 15. Add a Household aggregate and membership/role policy, migrate existing user-owned data into one default household per user, then change ownership foreign keys through the expand/backfill/enforce sequence in Section 25.10. Avoid a polymorphic owner_type/owner_id abstraction: it would complicate every query and foreign key without improving the concrete household target.

## 14. Events, jobs, queues, and notifications

### 14.1 Synchronous core, asynchronous edges

Planning, reserving, consuming, and grocery regeneration are consistency-critical and remain synchronous inside their application actions. A successful UI response therefore means the transaction is complete. Recommendation scoring is also synchronous and pure for MVP-scale data.

The epics' “recalculate recommendations” requirement does not require a RecalculateRecommendations event because rankings are not persisted: every recommendation query reads current available stock. Grocery rows are persisted for shopping state, so their regeneration is called synchronously by the initiating action.

Events are not used to hide the core workflow. Introduce a past-tense event only when it has at least one real, independent after-commit consumer. Likely examples are:

- DinnerCooked, consumed later by analytics or an activity feed;
- PantryLowStockDetected, consumed by an opt-in notification;
- GroceryListChanged, consumed by a future real-time or integration adapter.

An event payload contains stable scalar IDs, not a large serialized Eloquent object graph. Events that could be dispatched from a transaction must be emitted after commit so listeners cannot observe rolled-back state.

### 14.2 Jobs and queues

Jobs are appropriate for slow, retriable, externally visible work: image processing, importing a future recipe source, sending reminders, exporting data, or syncing a grocery provider. Jobs must:

- be idempotent or carry a unique operation key;
- reload and re-authorize/validate current state rather than trust stale serialized models;
- define timeouts, retry/backoff, and failed-job behaviour;
- log correlation IDs and the owned aggregate ID;
- be dispatched after the database commit when dependent on new state.

Do not queue reservation allocation, dinner cooking, or core grocery calculation. Queue failure must not leave the application's truth half-applied.

The repository currently uses the sync queue connection and has no worker service in compose.yaml. Database queue configuration and tables remain available but inactive. Before the first queued feature, enable after-commit dispatch at the connection or job/event level, add supervised worker configuration, and test failure and retry paths.

### 14.3 Notifications

There is no required notification in the epics. Postpone notification classes for the MVP. Likely opt-in additions are a dinner reminder and a low-stock/grocery-ready notification. Notifications should use Laravel channels and user preferences; they must not be emitted for every recalculation by default.

## 15. Recommendation and pantry-consumption workflow

This workflow is intentionally split into a read-only recommendation calculation and transactionally safe planning/consumption commands. A recommendation is advice based on a snapshot; PlanDinner rechecks availability while holding locks.

### 15.1 Deterministic recommendation calculation

GetPantryAwareRecommendations loads active recipes with ordered requirements, ingredients, packages, and the current user's pantry availability in bounded eager-loaded queries. RecommendationEngine then calculates in memory using decimal strings.

For each scaled requirement:

1. Scale from the immutable recipe amount and original servings, never from a previously rounded display amount.
2. Treat an ingredient as unlimited staple coverage only when is_staple and is_currently_available are both true. An unavailable staple is missing; a non-staple marked unavailable ignores recorded pantry stock until re-enabled.
3. For an exact comparable requirement, calculate coverage ratio as min(available / required, 1).
4. For a non-exact requirement, report Required/Optional status and coverage as explanatory information only. Exclude it from every scoring term and exact reservation.
5. For a known-content package, convert package count to its declared metric content.
6. For an unknown-content package, compare only the identical IngredientPackage definition. It is incompatible with grams, millilitres, or a different package definition.
7. Record missing, partially covered, and incompatible requirements for explanation.

Use one documented score for MVP:

ranking score = clamp(60Q + 20F − 10P − 10M − 10I, 0, 80)

Where:

- Q is mean exact quantity coverage;
- F is the proportion of exact ingredient lines fully available;
- P is the proportion partially available;
- M is the proportion completely missing;
- I is the proportion with incompatible measurements.

All factors are zero-to-one proportions over exact measurable requirements. The categories F/P/M/I are mutually exclusive; incompatible lines contribute zero quantity coverage. An available staple counts as fully covered. Non-exact lines are excluded. The ranking value is deliberately a score, not a pantry-coverage percentage; display quantity coverage separately. If a recipe has no exact requirements, its score is zero and the explanation says it cannot be quantity-matched. Rank by descending score, then fewer incompatible/missing/partial exact lines, recipe name, and recipe ID. Round only the displayed score.

The weights, including the initial 10-point incompatibility penalty, live once in config/recommendations.php and are covered by golden examples. They are an accepted MVP starting point but remain adjustable after user testing. Do not duplicate them in controllers/queries or add AI, embeddings, a rules engine, or persisted recommendation rows.

Each recommendation view model includes enough explanation to answer “why?”:

- scaled servings;
- coverage score;
- fully available ingredients;
- partial and missing amounts;
- incompatible unit/package cases;
- available and temporarily unavailable staples;
- Required/Optional non-exact items, clearly excluded from scoring.

### 15.2 Planning and reservation allocation

Stage 3 implements this design with `DinnerPlan` as the per-user lock root, snapshot-backed `PlannedDinnerRequirement` rows, and `IngredientReservation` allocations. All supply, demand, and priority mutations call the same `ReconcilePlanReservations` workflow with deadlock retries. `AvailablePantry` subtracts a reservation aggregate in its existing bounded query, so recommendations immediately reflect planned dinners without a refresh projection or event pipeline.

There is one rolling DinnerPlan for each user. Its Planned occurrences are the active ordered list; Cooked and Cancelled occurrences remain queryable as history, so no plan selector, plan name, or week boundary exists.

PlanDinner runs a database transaction for an active catalogue recipe:

1. Authorize the recipe and rolling plan; ordinary catalogue planning requires an active recipe.
2. Lock the rolling plan, affected Planned rows, relevant pantry entries, and reservations in stable order.
3. Create the PlannedDinner in Planned state and snapshot recipe identity/metadata.
4. Snapshot scaled ingredients into PlannedDinnerRequirement, including Required/Optional non-exact status.
5. Reconcile every affected active requirement in dinner-priority order.
6. Store each exact shortfall and required non-exact coverage state.
7. Regenerate generated grocery items/contributions and return changed-item notices.

Dinner priority is:

1. dated dinners before undated dinners;
2. planned_date ascending;
3. rolling-list position ascending;
4. created_at ascending;
5. primary key ascending as a final deterministic tie-break.

For each prioritized requirement, PantryAllocator uses exact native compatibility first, then convertible known-content packages, then PantryEntry ID. Manual allocation is postponed; users influence priority through dates and list reordering.

Every operation that can change supply, demand, or priority—stock add/reduce/removal, ingredient availability/staple state, dinner add/restore/remove/cancel/cook, servings/date/position changes—runs ReconcilePlanReservations for affected ingredients. It releases affected active reservations and recalculates them from the beginning in priority order; it does not preserve allocations that now belong to an earlier dinner. Reductions may lower stock below the old reserved amount because reconciliation first resolves those reservations, but the resulting total and available balances may never be negative. Removing a pantry entry with reservations requires explicit confirmation and the same reconciliation.

Recipe edits do not change an existing planned dinner because recipe metadata and requirements are snapshots. Changing servings recalculates from the snapshot's original amounts, never a rounded scaled result.

### 15.3 Historical snapshots, duplication, and restoration

Archived recipes are excluded from ordinary recommendations/editing. PlanArchivedRecipe may snapshot an owned archived recipe from the archive screen, and PlanDinnerFromHistory may copy a Cooked/Cancelled snapshot without relying on the live recipe. Either flow may offer a separate RestoreRecipeToCatalogue action.

DuplicatePlannedDinner creates an independent Planned occurrence with the same recipe snapshot, serving count, ingredient configuration, and optional date when requested. It never copies reservations, shortfalls, checked groceries, or terminal status; requirements are recalculated and reservations are allocated from current stock.

RestoreCancelledDinner changes only a Cancelled occurrence back to Planned, records restored_at, recalculates requirements from its snapshot, and performs full priority reconciliation. Old reservations are never restored blindly. Repeating a restore on an already Planned occurrence is idempotent; Cooked occurrences cannot be restored.

### 15.4 Cooking, cancellation, and removal

MarkDinnerCooked locks the PlannedDinner, affected pantry/reservation rows, and grocery contributions, then refreshes unresolved state. Unresolved means an exact shortfall whose generated grocery contribution is absent/unchecked, an incompatible exact requirement, or an unavailable Required non-exact item whose check item is absent/unchecked. If unresolved state exists and confirmation was not supplied, the action throws UnresolvedRequirementsRequireConfirmation with a structured summary for the Livewire confirmation modal.

After explicit confirmation—or immediately when fully covered—the action subtracts exactly the reserved amounts from pantry totals, removes/consumes those reservations, stores unresolved_at_cooking details on requirement snapshots, sets Cooked with cooked_at, reallocates remaining stock to later Planned dinners, and regenerates groceries. Missing quantities are never deducted. The confirmation is revalidated inside the locked transaction so a stale modal cannot approve different hidden state.

CancelDinner releases reservations without changing pantry totals, sets Cancelled, reallocates later dinners, and regenerates groceries; the occurrence remains in history and may be restored. RemovePlannedDinner releases/reallocates in the same way but permanently removes an unprocessed occurrence from the rolling list. Repeating Cook/Cancel/Remove safely returns the existing result where possible, and a Cooked dinner can never deduct twice.

### 15.5 End-to-end workflow

~~~mermaid
flowchart TD
    A[User chooses servings] --> B[Load recipes and current pantry availability]
    B --> C[Scale immutable recipe requirements]
    C --> D[RecommendationEngine scores and explains coverage]
    D --> E{User selects recipe?}
    E -- No --> A
    E -- Yes --> F[PlanDinner transaction]
    F --> G[Lock plan and compatible pantry entries]
    G --> H[Snapshot planned requirements]
    H --> I[Reallocate active dinners in date and list priority]
    I --> J[Record remaining shortfalls]
    J --> K[Regenerate generated grocery items]
    K --> L[Commit PlannedDinner]
    L --> M{Later action}
    M -- Change servings, date, order, or pantry --> N[Release affected reservations and fully reallocate]
    N --> K
    M -- Cancel --> O[Release reservations]
    O --> P[Set Cancelled, reallocate, and regenerate]
    P --> T{Restore later?}
    T -- Yes --> N
    M -- Cook --> Q[Lock and summarize unresolved requirements]
    Q --> R{Unresolved and confirmed?}
    R -- No confirmation --> U[Show confirmation summary]
    U --> Q
    R -- Covered or confirmed --> V[Deduct each reservation exactly once]
    V --> S[Store unresolved history, set Cooked, reallocate, regenerate]
~~~

### 15.6 Concurrency guarantees

- Available equals total minus active reservations, calculated from locked rows inside mutation transactions.
- Reducing/removing stock first releases affected reservations and reallocates against the new non-negative total; availability never becomes negative.
- Two simultaneous planning/reordering/stock requests serialize on the rolling plan and affected pantry rows; the second recomputes the authoritative priority order.
- A unique constraint prevents duplicate reservation allocation for one planned requirement and pantry entry.
- Cooked state and timestamps, plus transaction locks, prevent double consumption; restoration is allowed only from Cancelled.
- The recommendation screen may become stale, but the planning action never promises stale quantities.

## 16. Grocery-list generation workflow

The rolling DinnerPlan has one GroceryList containing generated items and user-created manual items. Generated items are derived from active Planned requirement shortfalls; manual items are never rewritten by generation.

### 16.1 Generation algorithm

RegenerateGroceryList executes within the calling plan transaction:

1. Load active PlannedDinnerRequirement shortfalls for Planned dinners.
2. Exclude exact requirements with no shortfall. An available staple is excluded; a temporarily unavailable staple contributes its exact shortfall or a Required non-exact check item.
3. Group exact shortfalls by ingredient and compatibility key. Convertible mass/volume/count amounts use normalized units; unknown packages group only by IngredientPackage ID.
4. Group uncovered Required non-exact requirements by ingredient and normalized description, displaying a check item without inventing an amount. Exclude Optional rows.
5. Sum with BCMath and format through QuantityFormatter.
6. Upsert each generated GroceryItem by a stable generation_key, replacing any temporary user override with the new calculated result.
7. Replace its GroceryItemContribution rows with the exact planned requirements and amounts that produced it.
8. Remove generated items whose keys no longer occur. Leave manual items untouched.

A generation key is derived from versioned, canonical data such as ingredient ID, compatibility group, normalized unit or package ID, and Required non-exact description. It is not based on display text. A materially different key is a new unchecked item.

The user can therefore see that “750 g potatoes” consists of, for example, 300 g for one dinner and 450 g for another. Contribution rows are explanatory trace data, not a second source of quantity truth. SetIngredientAvailability toggles whether a staple is assumed covered and triggers reservation/grocery reconciliation without destroying pantry balances.

### 16.2 Purchase-unit behaviour

Generated quantities express the recipe shortfall in a compatible metric/count/package unit. The MVP does not optimize how many retail packs to buy, compare prices, or round to supermarket quantities. Unknown packages remain counts of the exact package definition. For known packages, groceries primarily show the exact normalized metric need and may add equivalent source-package context; they never round 1.5 packs to 2.

Manual GroceryItem rows are fully editable and unaffected by recalculation. EditGeneratedGroceryQuantity writes a temporary override amount/unit and marks the generated row manually adjusted while leaving calculated_amount and contribution rows unchanged. The override controls display until the next relevant recalculation, at which point regeneration clears it, replaces the display with the newly calculated amount, and includes the reset item in GroceryRegenerationResult so the UI may announce that the generated list was refreshed. Generated and manual source states remain visibly distinct.

### 16.3 Idempotency and updates

Regeneration is idempotent: the same plan state produces the same generated keys, calculated amounts, and contributions. It runs after dinner add/duplicate/restore/remove/cancel/cook, servings/date/order changes, reservation changes, ingredient availability changes, and pantry adjustments.

For a same-key generated item, compare the old and new calculated amounts before clearing any override:

- equal or lower requirement: preserve checked_at;
- increased requirement: clear checked_at, store previous_calculated_amount and quantity_increased_at, and show “Quantity changed from … to …”;
- compatibility/fundamental key change: replace it with a new unchecked item.

Checked items remain in the active list until ClearCompletedGroceries. Removing a manual item deletes it. Generated items disappear when no longer required. No separate shopping-history model, completed shopping session, or visible regeneration history is created for the MVP; ordinary timestamps/change metadata are enough for debugging and the active UI.

If regeneration later becomes expensive, preserve the same pure GroceryCalculator and stable-key contract while moving only projection refresh to a queued, version-checked job. That is a future scaling option, not an MVP requirement.

## 17. Testing strategy

The test suite uses PHPUnit 12 through Laravel's test runner. Stage 1 adds focused measurement, recipe-scaling, catalogue-action, policy, route, and Livewire tests. The Stage 1-focused verification set contains 47 passing tests with 94 assertions; the pre-existing starter, authentication, settings, configuration, and health tests remain available for full-suite verification.

### 17.1 Test layers

| Test type | Purpose | Project examples |
| --- | --- | --- |
| Unit | Pure rules, no Laravel container or database | Quantity/InputParser; ingredient-specific conversion matrix; configurable recommendation fixtures; priority allocator; grocery grouping/check-state transitions |
| Application/feature | Actions, validation, policies, Eloquent relationships, and transactions | Plan/duplicate/restore archived snapshot; full priority reconciliation; unresolved cook confirmation; transient grocery override; cook exactly once |
| Livewire feature | Page behaviour, authorization, validation, and rendered state | Required recipe fields; history/restore UI; unresolved summary modal; quantity-increased notice; English/Dutch-format presentation |
| MySQL integration | Behaviour SQLite cannot prove | DECIMAL round trips; unique constraints; stable lock order; competing reorder/stock/plan requests; generated-key indexes |
| Browser/end-to-end, selective | A few high-value JavaScript/browser interactions | Complete first recipe-to-cooked-dinner journey; passkey/2FA only where browser APIs require it |

Pure services should have exhaustive data providers around boundaries. Example cases include decimal comma, small/large values, g↔kg, ml↔l, tsp/tbsp, same-ingredient/same-count compatibility, cross-ingredient/count incompatibility, known-package dual display, unknown packages, Required/Optional non-exact amounts, display rounding/fractions, and scaling without drift.

New database tests should use LazilyRefreshDatabase as required by AGENTS.md. Existing starter tests use RefreshDatabase; that difference is acceptable until those files are touched, then migrate them in small verified batches. Factories define valid defaults and named states such as archived, nonExact, staple, planned, cooked, and cancelled.

### 17.2 Epic acceptance coverage

| Epic | Minimum architectural acceptance tests |
| --- | --- |
| 1. Ingredients and measurement | Exact/non-exact creation, compatibility matrix, package definitions, decimal precision |
| 2. Recipe catalogue | Minimum required fields, nullable metadata/unknown filters, image placeholder, archive/history-snapshot planning |
| 3. Pantry | Merge/package context, add/reduce/removal reconciliation, total/reserved/available, two-field staple availability |
| 4. Serving scaling | Scale from original quantities, fraction/decimal input, no cumulative rounding |
| 5. Pantry recommendations | Configured Q/F/P/M/I weights, tie-breaks, unavailable staples, non-exact exclusion, explanations |
| 6. Dinner plan | Rolling order/date priority, duplicate/history planning, cancel/restore, Cooked terminal snapshots |
| 7. Reservation lifecycle | Full priority reallocation, partial/concurrent selection, unresolved confirmation/history, exactly-once cooking |
| 8. Grocery list | Aggregation/package context, transient generated edits, increase-unchecks/decrease-preserves, manual preservation, clearing/no history |

### 17.3 Test rules

- Assert externally meaningful state and invariants, not framework implementation details.
- Test every policy with owner, other user, and unauthenticated cases.
- Use model factories; avoid opaque shared seed data.
- Freeze time for plan dates/status timestamps.
- Use Mail/Notification/Queue/Event fakes only at application boundaries; unit-test core results directly.
- Never substitute floats into a quantity test.
- Include failure and rollback assertions, not just happy paths.
- Add a regression test before correcting any discovered production bug.
- Keep query-count or eager-loading regression tests around the recommendation and grocery screens once realistic fixtures exist.

CI runs formatting/static checks, unit/feature tests, and MySQL 8.4 integration across the supported PHP/Node matrix. Query ceilings cover recommendations and the pantry, dinner-plan, and grocery read paths; response-time samples remain observational because wall-clock CI assertions are unstable.

## 18. Scalability and future expansion

### 18.1 Appropriate MVP scaling

The modular monolith scales vertically and across stateless web containers while MySQL remains the consistency boundary. Session/cache/queue are already configured for database-backed operation, although a production deployment may move cache/session and queues to Redis without changing domain actions.

MVP performance depends more on sound queries than distributed architecture:

- eager-load recommendation graphs in a bounded number of queries;
- index user ownership and active/status filters;
- index recipe requirements by recipe/position;
- index pantry entries by user/ingredient/merge key;
- index planned dinners by plan/status/date;
- index active reservations by pantry entry and planned requirement;
- uniquely index generated grocery keys per list;
- paginate recipe, pantry, and history screens;
- select only required columns for list views.

Do not cache mutable pantry availability or recommendation rankings initially. Measure first. If calculation becomes material, cache a per-user recommendation projection keyed by recipe-catalogue version, pantry version, and requested servings; invalidate by version rather than broad cache scans.

### 18.2 Planned seams for growth

The following seams were deliberately left by the MVP. The post-MVP plan activates them only in the named epic; an earlier epic must not pre-build a later integration:

| Post-MVP need | Existing seam and activation point |
| --- | --- |
| Recipe favourites | Recipe list/recommendation queries can join a personal user/recipe pivot without changing Recipe ownership (Epic 10) |
| Richer dinner history | PlannedDinner already stores terminal state, lifecycle timestamps, and immutable requirement snapshots (Epic 11) |
| Decision mode | RecommendationResult already exposes deterministic eligibility, score, and explanations; PlanDinner remains the selection command (Epic 12) |
| Structured dietary filters | Existing free-form Tag remains separate; recipe queries can add explicit dietary facts and reuse one eligibility specification (Epic 13) |
| Ingredient substitutions | PlannedDinnerRequirement snapshots isolate an occurrence from its source recipe and provide a reconciliation boundary (Epic 14) |
| Shared households | Explicit owner policies and user foreign keys can migrate to concrete Household foreign keys (Epic 15) |
| Internationalisation | English strings are presentation-only; central formatters/config isolate Dutch-style dates/decimals for later locale preferences |
| Expiry-aware pantry | Add PantryStockBatch and change allocation within a requirement from entry ID to earliest suitable expiry (Epic 16) |
| Retail pack optimization | Extend IngredientPackage and optimize only after GroceryCalculator has produced exact shortfalls (Epic 17) |
| Prices and budgets | Package suggestions provide the purchase basis; add Money/CostEstimate without using unknown as zero (Epic 18) |
| External recipe import | Import drafts map reviewed external data into the same CreateRecipe action/value rules (Epic 19) |
| AI-assisted extraction | The import review boundary accepts a validated DTO from a replaceable RecipeTextExtractor (Epic 20) |
| Barcode scanning | IngredientPackage and pantry batch entry provide the confirmation target for a provider lookup (Epic 21) |
| PWA/mobile delivery | Responsive Livewire pages and Vite assets allow an installable static shell without caching household responses (Epic 22) |
| API/mobile client | Actions and policies remain; add versioned controllers/requests/resources and approved token authentication (Epic 23) |
| Recommendation preferences | Add implemented factors through the focused engine/filter pipeline; keep hard eligibility separate from ranking |
| Notifications | Consume committed events with queued listeners only when an epic adds an actual notification |
| Object storage/CDN | Storage disk configuration; no direct filesystem paths in domain code |
| Search | Begin with indexed MySQL queries, add an external index only when measured |

Adding a future seam does not mean adding an interface today. Extract an interface when there is a true external boundary, multiple implementations, or a testability problem not solved by Laravel fakes.

### 18.3 Deliberately postponed

Postpone microservices, event sourcing, CQRS read databases, generic repositories, a service bus, a rules engine, a third-party multi-tenancy package, Redis, search services, websocket updates, AI recommendation/ranking, complex offline writes, automatic allergy inference, price-history analytics, and a universal integration framework. The roadmap does not require them. AI in Epic 20 is limited to reviewed recipe-text extraction; household isolation in Epic 15 remains ordinary Laravel models, policies, foreign keys, and query scopes.

## 19. Observability, logging, and operational concerns

### 19.1 Structured operational context

Use Laravel logging with consistent context for important commands:

- request/correlation ID;
- authenticated user ID, never email/password/passkey material;
- action name;
- dinner plan/planned dinner/recipe IDs;
- affected pantry-entry count;
- retry attempt and duration;
- generated grocery-item count.

Log state-transition summaries, not raw recipe notes or complete request payloads. Do not log ordinary validation errors. Domain conflict logs should be low-volume and structured; unexpected exceptions retain stack traces in the server log but show a generic response to users.

Database transactions may be instrumented with duration and deadlock retry counts. Add slow-query monitoring and query-count checks around recommendations/grocery calculations once representative data exists. Metrics worth adding when an operational platform is selected include request latency/error rate, recommendation calculation duration, transaction retry count, queue depth/age/failures, and MySQL connection/lock pressure.

### 19.2 Runtime operations

- Keep the framework `/up` endpoint for process health. Docker checks it directly, while MySQL readiness is enforced separately through the database container health check and migration/integration gates. Add any future dependency details only to a protected deployment mechanism.
- Run migrations as a single release step before new containers accept traffic. Never run destructive schema changes and application cutovers without a compatible transition.
- Back up MySQL and test restoration. A backup is not proven until restore verification succeeds.
- Configure failed-job retention and alerts before enabling queued features.
- Keep stored timestamps in UTC and present MVP dates/times with the English interface's Netherlands-friendly policy: Europe/Amsterdam time, DD-MM-YYYY dates, 24-hour time, and Monday-first calendar controls.
- Set production APP_DEBUG=false, rotate logs, and keep environment secrets out of images and source control.
- Document worker, scheduler, backup, migration, and rollback commands in an operations/runbook document when deployment is introduced.

### 19.3 Current operational gaps

Stage 0 added a health-checked MySQL 8.4 CI service, minimum/runtime PHP and Node matrix, deterministic Composer/npm setup, and Docker liveness/readiness checks. Remaining operational work is feature- or deployment-driven:

- Database queues remain deliberately inactive until a supervised worker, after-commit dispatch, retry policy, and monitoring are introduced.
- boost.json says sail is false and .codex/config.toml invokes host php while DB_HOST=mysql is only resolvable on the Compose network. Reconfigure this only when database-aware Boost tooling is needed.
- Production backup/restore, scheduler, migration, rollback, and worker runbooks remain part of deployment hardening.

These are incremental operational tasks, not reasons to reorganize the application.

## 20. Security considerations

Security is enforced through ordinary Laravel controls plus domain-specific ownership and transaction rules.

### 20.1 Application controls

- Require authenticated, verified sessions for product screens; the User verification contract and enforcement coverage are in place.
- Authorize every read and mutation; scope relationship queries by the current owner to prevent insecure direct object references.
- Keep CSRF protection on state-changing web requests and use POST/PATCH/DELETE semantics instead of mutating GET routes.
- Use validated field lists and explicit model fillable/guarded decisions. Do not pass unfiltered arrays to create, update, forceFill, or query ordering.
- Escape user-provided names/notes in Blade/Flux. Render Markdown or rich text only through an allow-list sanitizer if that feature is later added.
- Let Eloquent/query builder bind values. Any raw ordering or expression must come from an allow-list, never request text.
- Rate-limit authentication and any future expensive recommendation/import endpoint.
- Regenerate sessions on login/logout through Fortify's established flow; preserve secure, HTTP-only, same-site cookie settings in production.

### 20.2 Files and external integrations

Recipe images are optional. Use Laravel Storage, generated filenames, explicit size limits, server-side MIME/content verification, safe image decoding/re-encoding, and a non-executable storage location. Do not trust extensions or expose an arbitrary local path. Future integrations keep credentials in environment/secret storage, use timeouts, validate responses, and run outside database transactions.

### 20.3 Data integrity and privacy

- Fixed-precision decimal handling prevents silent quantity corruption.
- Foreign keys, unique constraints, transitions, locks, and transactions defend invariants even under concurrent requests.
- Self-service deletion/export and custom retention are outside the MVP. Keep normalized ownership, nullable snapshot source references, and Storage-managed image paths so later export, deletion, or anonymisation remains feasible.
- Backups and logs contain personal data and require the same access/retention controls as the database.
- Passkey and two-factor secrets/recovery codes stay in the existing encrypted/hashed Fortify-compatible representation; never include them in logs or DTOs.
- Dependency updates and container base images should be scanned and patched routinely.

The MVP stores meal preferences and account data but no payment or medical data. Allergen support is explicitly outside scope; until it is implemented and verified, the UI must not imply that a recommendation is safe for an allergy.

## 21. Architectural decisions and rejected alternatives

The following decisions summarize the architecture. “MVP” is necessary to implement the current epics. “Growth” is a low-cost seam that makes a likely extension safer. “Postponed” means do not build it without a new requirement.

| ID / timing | Decision and why it fits | Alternatives not selected | Trade-off and reconsideration trigger |
| --- | --- | --- | --- |
| ADR-01 / MVP | Use a Laravel-first modular monolith. One deployment and one transaction boundary fit a small team and strongly related meal-planning data. | Microservices add network consistency and operations; full Clean/Hexagonal Architecture duplicates Laravel boundaries. | Modules are not independently deployable. Reconsider only when team/deployment boundaries or scale require independent services. |
| ADR-02 / MVP | Organize conventional App directories by role, then feature subnamespace where useful. | A parallel src/Domain/Application/Infrastructure tree or package-per-module would fight generators, policies, jobs, Livewire, and framework discovery. | Some feature files are distributed across Actions, Models, and Livewire. Consistent names and the module map mitigate this; extract a package only for a genuinely reusable/bounded subsystem. |
| ADR-03 / MVP | Keep controllers/Livewire components thin; use focused verb-named actions for use cases; keep local behaviour on Eloquent models/value objects. | Large controllers, “fat model does everything,” and a single DinnerService all create mixed responsibilities. | More small classes and naming decisions. Combine only when an action is a trivial one-line model operation with no rule or reuse. |
| ADR-04 / MVP | Use Eloquent directly and add purpose-named query objects only for complex recommendation/grocery reads. | Generic repository interfaces obscure Eloquent and duplicate its API; CQRS read stores are unnecessary. | Code is coupled to Laravel/MySQL, intentionally. Reconsider a repository at a real external persistence boundary or when two implementations exist. |
| ADR-05 / MVP | Store DECIMAL(18,6), carry decimal strings in PHP, and calculate with BCMath at scale six. | PHP floats silently drift; arbitrary-precision libraries add dependencies before needed; storing minor integer units cannot represent all unit groups as clearly. | Six decimal places and arithmetic helper discipline are required. Reconsider precision after real recipe/package data proves it inadequate. |
| ADR-06 / MVP | Model fixed UnitCode rules in code/config, ingredient-plus-semantic count compatibility, and ingredient-specific package definitions in MySQL. Preserve known package context while calculating in metric content. | User-defined global conversion is unsafe; package strings lose equality/content; universal count conversion would falsely combine cloves/bulbs/slices or different ingredients. | New units require a release and package display adds stored context. Reconsider approximate count-to-mass only as explicit ingredient rules after MVP. |
| ADR-07 / MVP | Persist PantryEntry total; persist reservations; derive reserved and available. | Persisting total, reserved, and available creates three mutable truths; ledger-only inventory is more complex for the MVP. | Aggregation is required to display availability. Add cached counters only after measured query pressure and invariant-preserving updates. |
| ADR-08 / MVP | Snapshot recipe identity/metadata and PlannedDinnerRequirement data for every occurrence. History-based planning, duplication, restore, and unresolved-at-cooking all use the snapshot. | Reading live RecipeIngredient rows saves space but silently changes history and fails after archive/deletion; full recipe versioning is more machinery. | Snapshots duplicate data. Reconsider full immutable recipe versions if catalogue audit/version comparison expands. |
| ADR-09 / MVP | Use synchronous MySQL transactions and pessimistic locks for reservation, stock, cooking, and generated grocery changes. | Optimistic retry everywhere complicates the UI; queued/eventual reservation permits double allocation; distributed locks add another system. | Contention can reduce throughput. Reconsider lock granularity only with measured contention and concurrency tests. |
| ADR-10 / MVP | Calculate recommendations on demand with configurable 60Q + 20F − 10P − 10M − 10I scoring and explicit explanations. | Random ordering, opaque AI, persisted rankings, and a rules engine are not justified. | Initial weights are product-approved but tunable in one config; calculation grows with catalogue size. Reconsider/cache/version with measured latency or research. |
| ADR-11 / MVP | Derive generated groceries with stable keys/contributions, transient user overrides, increase-sensitive checked state, and no long-term shopping history; preserve manual rows. | Display-only strings lose truth; persistent generated overrides become stale; rebuilding indiscriminately loses checks/manual work; shopping sessions add premature scope. | Regeneration owns more state transitions. Add completed shopping sessions only when shopping history is a real feature. |
| ADR-12 / MVP | Separate is_staple from is_currently_available. Only an available staple is assumed covered; temporarily unavailable ingredients create shortfalls/groceries without deleting pantry totals. | Infinite quantities conflate preference and state; a single staple flag cannot represent “normally stocked but out”; deleting stock loses user data. | Two booleans need an explicit truth table and reconciliation. Reconsider richer availability reasons only with a concrete UX need. |
| ADR-13 / MVP | Use per-user ownership and explicit policies/query scoping. | Premature household tenancy/polymorphic owners burden all foreign keys and policies. | A future household migration is required. Reconsider when shared planning is approved, before significant multi-user product data exists if possible. |
| ADR-14 / MVP | Use Livewire 4 pages and Flux components, with Livewire Form objects for substantial forms. | A React/Vue SPA duplicates validation/API work; Blade-only forms make highly interactive planning less direct. | Server round trips require careful eager loading/loading states. Reconsider a separate client only when offline/native/API requirements appear. |
| ADR-15 / Growth | Emit events only for real after-commit side effects; queue only slow/retriable edge work. | Event-driven orchestration hides core control flow and makes correctness depend on workers. | The action knows its core collaborators. Split when a side effect can independently fail and eventual consistency is acceptable. |
| ADR-16 / MVP | Use MySQL as development/test production-parity database for persistence-sensitive tests. | SQLite is fast but does not prove MySQL DECIMAL, locking, or concurrency behaviour. | MySQL tests cost startup time. Pure unit tests remain fast; use targeted integration tests and parallel CI services. |
| ADR-17 / MVP | Archive ingredients/recipes with explicit archived_at and retain independent dinner snapshots. Archived recipes are excluded from editing/recommendations but archive/history actions can plan snapshots, duplicate occurrences, and optionally restore recipes. | Hard deletion breaks history; SoftDeletes everywhere hides joins; forbidding archived/history planning contradicts the rolling workflow. | Queries/actions must distinguish active-catalogue selection from archive/history snapshot planning. Reconsider full recipe versioning only with broader audit needs. |
| ADR-18 / Postponed | Do not add DTOs universally. Use immutable data/value objects only for quantities, calculation inputs/results, and unstable external boundaries. | DTO-per-request/model creates mapping code without a boundary; raw arrays are too weak for calculation contracts. | Some actions accept models and named scalars. Introduce a DTO when argument count/shape or serialization makes the contract clearer. |
| ADR-19 / MVP | Use one rolling DinnerPlan per user. Planned rows are the ordered active list; Cooked/Cancelled rows are history, and Cancelled may be restored. | Calendar weeks/named plans impose management and a current-plan selector unrelated to irregular cooking. | The plan table is a singleton aggregate and history grows under it. Reconsider multiple plans only for an approved household/calendar use case. |
| ADR-20 / MVP | Reconcile affected reservations globally in explicit-date, position, creation order whenever supply, demand, or order changes. | Preserving old allocations violates earliest-dinner priority; manual allocation adds complex controls; queue-based reconciliation risks stale stock. | More rows are locked/rebuilt per mutation. Reconsider incremental optimization only after equivalent deterministic/concurrency tests and measured contention. |
| ADR-21 / MVP | NonExactStatus is exactly Required or Optional. Required may create a check/missing confirmation; Optional affects neither score nor automatic grocery generation. | A free-form status is inconsistent; extra statuses duplicate the written description and add ambiguous rules. | Two states may be limiting later. Add a status only with distinct calculation/UI behaviour. |
| ADR-22 / Post-MVP | Model favourites as a unique User–Recipe relationship. Favourite is a personal preference, survives recipe archive, and is a secondary recommendation tie-break after pantry suitability. | A Recipe boolean would leak one user's preference to another and would migrate poorly to households; a large score bonus could hide a worse pantry match. | Joining the pivot adds query/index work. Reconsider an explicit configurable preference score only after user testing. |
| ADR-23 / Post-MVP | Extend PlannedDinner history rather than create a second history aggregate. Keep current state/timestamps on PlannedDinner and append compact lifecycle events for post-MVP transitions to preserve repeated-transition evidence. | Copying terminal dinners into another table duplicates snapshots; deriving every transition from the latest timestamps loses repeated cancel/restore cycles. | A small append-only child table adds writes. It is audit evidence, not event sourcing or the source of current state. |
| ADR-24 / Post-MVP | Implement first-version Decision Mode as a deterministic pure selection service over current recommendation/filter results; keep exclusions and reroll seed in the Livewire session and plan through PlanDinner. | Persisted decision sessions add cleanup/privacy state; unseeded randomness is hard to explain and test; AI is unnecessary. | Refreshing the browser may end the session. Persist sessions only if product usage proves continuity or sharing is required. |
| ADR-25 / Post-MVP | Keep free-form Tag separate from explicit structured dietary facts. Absence of a fact means unknown, never safe; hard dietary eligibility runs before recommendation scoring. | Reusing tags makes spelling/safety semantics unreliable; inferring allergy safety from incomplete ingredients creates false assurances. | Explicit unknowns reduce apparent result counts. Add ingredient-derived facts only with complete provenance and contradiction handling. |
| ADR-26 / Post-MVP | Store a confirmed substitution on the PlannedDinnerRequirement occurrence, including the rule/ratio snapshot and effective ingredient quantity. Allocation and grocery calculations use the effective requirement; the RecipeIngredient remains unchanged. | Rewriting the recipe destroys intent; silent runtime substitution is unsafe; storing only a substitute ID cannot reproduce historical quantities. | Effective-requirement resolution adds complexity. Keep it in one resolver used by snapshots, allocation, groceries, and history. |
| ADR-27 / Post-MVP | Introduce concrete Household ownership through an expand/backfill/enforce migration. Shared aggregates use household_id; memberships and invitations use explicit Owner/Member roles; favourites and active-household preference remain user-owned. | Polymorphic ownership weakens foreign keys; a global scope can hide cross-tenant defects; a tenancy package is unnecessary for one database/schema. | Most policies and queries must change together. Require full tenant-isolation coverage and a verified rollback/cutover plan. |
| ADR-28 / Post-MVP | Make PantryStockBatch the stock source of truth for expiry-aware inventory and allocate reservations to batches in earliest-expiry order within the existing earliest-dinner priority. Expired or manually unavailable batches do not satisfy demand. | One aggregate PantryEntry quantity cannot express several expiry dates; a recommendation-only expiry field would not control consumption correctly. | More rows and locks are involved. Retain PantryEntry as the ingredient/representation grouping boundary and aggregate batches in bounded queries. |
| ADR-29 / Post-MVP | Keep exact GroceryCalculator need separate from a PackagePurchaseOptimizer suggestion. The first optimizer chooses one compatible package option repeated, ordered by least excess, then fewest packages, then stable ID; manual purchase overrides are explicit. | Rounding the grocery requirement corrupts domain truth; an unbounded mixed-package/price optimizer is premature. | A mixed-size combination can sometimes waste less. Add bounded combination optimization only with explicit acceptance cases and performance limits. |
| ADR-30 / Post-MVP | Represent prices/budgets as integer minor units plus ISO currency. A CostEstimate carries known subtotal and unknown-price item count; unknown is never zero. Initial product currency is EUR with no automatic conversion. | Floats drift; DECIMAL money arithmetic is unnecessary for EUR cents; treating missing prices as zero misleads budgets. | Minor units need currency-specific scale metadata if more currencies are added. Price history and exchange rates remain separate future features. |
| ADR-31 / Post-MVP | Route JSON, Schema.org, and URL imports through validated import DTOs and a review draft. ConfirmRecipeImport invokes existing ingredient/recipe actions; no importer writes Recipe rows directly. | Provider-specific model writes duplicate creation rules; automatic save makes ambiguous ingredient matches unsafe. | The review draft adds a lifecycle and cleanup policy. Keep it bounded and delete abandoned drafts after the documented retention window. |
| ADR-32 / Post-MVP | Put AI extraction behind RecipeTextExtractor and feed its structured, validated output into the same import review boundary. AI never performs measurement conversion, allocation, reservation, grocery, dietary-safety, or persistence decisions. | Provider calls in Livewire couple credentials and response shapes to the UI; direct writes let malformed/uncertain output enter the domain. | The adapter and fake add classes, justified by an unstable external boundary and deterministic tests. The application retains a non-AI import/manual path. |
| ADR-33 / Post-MVP | Treat barcode/product data as untrusted provider suggestions. Preserve barcodes as validated strings, store confirmed household catalogue mappings, and call the normal package/batch pantry action after user review. | Provider products as canonical Ingredient rows allow remote changes to corrupt local data; numeric barcode storage loses leading zeroes. | Confirmation adds a step. Cache only provider facts with bounded TTL and never use cached household mappings across tenants. |
| ADR-34 / Post-MVP | The first PWA caches versioned static assets and a generic offline fallback only. Authenticated HTML, Livewire traffic, API responses, recipe images requiring authorization, and household data are network-only; offline mutations are postponed. | Cache-first product responses can expose the previous household/user and require conflict resolution. | Offline functionality is limited but safe. Add data caching only with encryption, per-user cache partitioning, logout purge, and a conflict protocol. |
| ADR-35 / Post-MVP | Add `/api/v1` as a delivery adapter over existing actions/queries using Form Requests, policies, and API Resources. Prefer Sanctum for an approved first-party/mobile token use case; identify Household in the route and authorize membership per request. | Returning Eloquent directly leaks schema; session active-household state is ambiguous for API clients; Passport is unnecessary without OAuth2 delegation. | Versioning and resource mapping add maintenance. Breaking representations require a new API version, not a database-driven accidental change. |

### 21.1 Decision ownership

Architecture changes should update this section in the same pull request as the implementing change. A decision may be revised when its trigger occurs; the table is guidance with rationale, not a ban on evidence-based evolution.

## 22. Implementation rules for future development

These rules translate the architecture into reviewable code conventions.

### 22.1 Use-case and presentation rules

1. Name application actions as imperative verbs in App\Actions\<Feature> and give them one public handle method.
2. Inject action/query dependencies through constructors or Livewire method injection; do not use the service container directly in business code.
3. Controllers coordinate HTTP only. Livewire components coordinate state/rendering only. Neither performs quantity arithmetic or opens multi-model transactions.
4. Use App\Livewire\Forms for substantial Livewire forms and App\Http\Requests for conventional endpoints.
5. Authorize at the presentation boundary and scope again in owned application queries.
6. Return models or focused result/view objects with domain meaning; do not return false for exceptional outcomes.
7. Use Flux components and Tailwind utility conventions already present. Keep new reusable Alpine behaviour outside large inline script blocks.

### 22.2 Domain and persistence rules

1. Never use a PHP float for ingredient, pantry, reservation, or grocery quantities.
2. Create Quantity through one parser/factory; persist display and normalized forms only through focused actions/model methods.
3. A non-exact amount is explicit state, not zero or null accidentally interpreted as zero.
4. Scale from the immutable source amount. Round only for display or a specifically documented storage boundary.
5. Use enums for closed state/unit sets and exhaustive match expressions when behaviour differs.
6. NonExactStatus has only Required and Optional; do not infer behaviour from description text.
7. Put local invariants and transitions on the relevant model/value object; put cross-aggregate operations in actions with DB::transaction.
8. Lock the rolling plan and affected rows in one stable order; reservation iteration follows planned-date/list/creation priority. Do no external I/O inside a transaction.
9. Use foreign keys, unique/check constraints where MySQL supports the invariant, and duplicate the critical check in domain code for useful errors.
10. Scope Eloquent relations/queries explicitly and eager-load; do not introduce lazy-loading-dependent loops.
11. Do not add a generic BaseRepository, BaseService, universal Result wrapper, or application-specific dependency injection framework.
12. Deployed migrations are immutable. Use additive/compatible schema transitions and a later cleanup migration.
13. Use Storage disks for files, config for tunable values, and environment variables only from config files.
14. Keep calculated grocery amounts/contributions separate from temporary display overrides.
15. Generate migrations with Artisan, keep each migration focused, separate DDL from data backfill commands, and provide a real reversible down method when reversal is safe.
16. Mirror database defaults in model attributes, cast dates/decimals/enums explicitly, and index the actual ownership/filter/order/join paths introduced by each slice.

### 22.3 Events, jobs, API, and external boundary rules

1. Core truth is updated synchronously. An event name is past tense and has a concrete consumer.
2. Dispatch transaction-dependent events/jobs after commit.
3. Jobs are idempotent, bounded, retriable, observable, and do not trust stale ownership/state.
4. External clients have an interface/adapter at App\Integrations\<Provider> and explicit timeouts/error translation. Do not wrap first-party Eloquent behind an interface.
5. Add API Resources/controllers only when an API is approved; do not pre-build parallel endpoints for Livewire pages.

### 22.4 Test and documentation rules

1. Every action has success, authorization, validation/invariant, and rollback/conflict coverage proportional to risk.
2. Every calculation service has table-driven unit tests including incompatible and boundary cases.
3. Reservation and quantity persistence behaviour is proven against MySQL.
4. New database tests use LazilyRefreshDatabase and factories.
5. A new state, unit, conversion, or ownership rule updates tests and this architecture document.
6. A future agent must read AGENTS.md, the relevant skill instructions, this document, and affected code before implementing an epic.

### 22.5 Post-MVP ownership and filtering rules

1. Until Epic 15 completes, existing user ownership is authoritative. Do not partially query household_id before the cutover contract for that slice is in place.
2. After Epic 15, every shared route/query/action receives an explicit Household or CurrentHousehold value and proves membership; active-household session state alone never authorizes a record.
3. A cross-household record ID must behave as not found or unauthorized consistently and must never reveal whether the record exists.
4. Personal preferences such as favourites remain scoped by user_id plus an accessible recipe; switching households never transfers or deletes them.
5. Apply hard eligibility filters—ownership, archive state, explicit dietary requirements, and user-selected filters—before ranking factors.
6. Absence of a dietary fact is Unknown. UI copy and API representations must not convert Unknown into allergen-free or suitable.
7. Every recommendation/decision factor returns structured explanation data and has a deterministic tie-break. Seeded randomness may choose among eligible results only after deterministic eligibility and scoring.

### 22.6 Post-MVP inventory, purchasing, and price rules

1. Dinner priority remains dated-before-undated and earliest-first. Expiry ordering chooses stock only within one dinner requirement; it never lets a later dinner take stock from an earlier dinner.
2. Expiry is a date in the household presentation timezone. Compare dates, not UTC instants, and treat a null expiry as after every non-expired dated batch.
3. Exact recipe need, effective substituted need, pantry reservations, grocery shortfall, suggested purchase amount, and displayed/manual purchase override are separate named values.
4. A substitution requires explicit confirmation and a complete quantity mapping. It must never rely on an otherwise unsupported unit conversion.
5. Package optimization receives an exact shortfall and compatible purchase options. It cannot mutate recipe, pantry, reservation, or GroceryItemContribution truth.
6. Money uses integer minor units and a currency code. A partial estimate must expose unknown items/counts alongside the known subtotal.
7. Budget and cost are ranking/feedback inputs after mandatory filters; they never override household isolation, dietary eligibility, or measurement compatibility.

### 22.7 Post-MVP integration, API, and PWA rules

1. Every external provider has a narrow contract, a Laravel HTTP-client adapter with explicit connect/request timeouts, translated failures, bounded response size, and a fake used by automated tests.
2. URL import protects against server-side request forgery: allow only HTTP/HTTPS, reject credentials and private/reserved destinations, revalidate redirects/resolved addresses, and cap redirects, bytes, and processing time.
3. Provider/AI output is untrusted input. Parse it into a DTO, validate it, preserve uncertainties, and require a review/confirmation action before domain persistence.
4. No external request runs while pantry, planning, grocery, membership, or recipe-write transaction locks are held.
5. Before the first queued integration, add and verify a supervised worker, after-commit dispatch, exponential backoff, a `retry_after` longer than every job timeout, uniqueness/idempotency where needed, explicit failed handling, provider rate limiting, failed-job monitoring, and operational runbook changes.
6. API controllers contain no business rules and never serialize Eloquent models directly. Versioned API Resources define output, and API Form Requests define validation/authorization.
7. API tenant context is explicit in the route or another signed/authenticated request value; browser session active-household state is not an API contract.
8. Service workers do not cache authenticated HTML, Livewire requests, API responses, or household data in the first version. Cache names are release-versioned and old static caches are removed on activation.
9. Provider tests prevent stray HTTP requests and cover safe retryable failures, non-retryable client errors, malformed/oversized responses, and timeout behavior without reaching the real network.

Relevant framework references:

- [Laravel 13 database transactions and deadlock retries](https://laravel.com/docs/13.x/database#database-transactions)
- [Laravel 13 Form Requests and validation](https://laravel.com/docs/13.x/validation#form-request-validation)
- [Laravel 13 authorization policies](https://laravel.com/docs/13.x/authorization#creating-policies)
- [Laravel 13 events after commit](https://laravel.com/docs/13.x/events#dispatching-events-after-database-transactions)
- [Laravel 13 queues](https://laravel.com/docs/13.x/queues)
- [Laravel 13 Eloquent casts](https://laravel.com/docs/13.x/eloquent-mutators#attribute-casting)
- [Laravel 13 scoped route-model binding](https://laravel.com/docs/13.x/routing#implicit-model-binding-scoping)
- [Laravel 13 HTTP client timeouts, retries, and fakes](https://laravel.com/docs/13.x/http-client)
- [Laravel 13 API routing and Sanctum installation](https://laravel.com/docs/13.x/routing#api-routes)
- [Laravel 13 API Resources](https://laravel.com/docs/13.x/eloquent-resources)
- [Laravel 13 rate limiting](https://laravel.com/docs/13.x/routing#rate-limiting)
- [Livewire 4 components](https://livewire.laravel.com/docs/4.x/components), [forms](https://livewire.laravel.com/docs/4.x/forms), and [validation](https://livewire.laravel.com/docs/4.x/validation)

Prefer these framework mechanisms over custom equivalents.

## 23. Suggested incremental migration or implementation plan

Stages 0–5 record how the MVP was built from the original Laravel/Livewire/Fortify starter. Post-MVP work begins at Stage 6 and extends the implemented domain in small vertical slices. Each post-MVP epic starts with a read-only repository analysis and an approved slice plan as required by the Post-MVP Development Plan; do not implement an entire epic as one undifferentiated change.

### Stage 0 — Baseline hardening (complete)

Completed on 17 July 2026:

- App\Models\User implements MustVerifyEmail and verified-route enforcement is tested.
- PHP 8.3/Node 22.12 minimums and the PHP 8.5/Node 24 Docker runtime are documented and tested in CI against MySQL 8.4.
- MySQL-only setup is deterministic; the stale SQLite post-create step is removed.
- The English-only Europe/Amsterdam presentation contract is configured while application storage remains UTC.
- ext-bcmath is a Composer platform requirement and is verified in the Docker runtime.
- Queues execute synchronously until a supervised worker and after-commit behavior are introduced.
- Docker enforces MySQL readiness and Laravel `/up` liveness during bounded startup.

Stage 0 resolved baseline inconsistencies without introducing domain layering.

### Stage 1 — Measurement, catalogue, and serving scale (Epics 1, 2, and 4) (complete)

Completed on 20 July 2026:

1. Added measurement enums (including ingredient-specific semantic-count compatibility), Quantity, QuantityInputParser, converter/formatter, and display-policy tests.
2. Added Ingredient, IngredientAlias, and IngredientPackage migrations, models, policies, factories, actions, and catalogue screens.
3. Added Recipe, RecipeIngredient with Required/Optional NonExactStatus, RecipeStep, RecipeCategory, and Tag persistence with policies, factories, and transactional actions.
4. Implemented required and nullable metadata, category/tag filtering, placeholders, optional validated image storage, create/update/archive/restore flows, and authenticated Livewire/Flux pages.
5. Added RecipeScaler and a serving preview that always scales from persisted source quantities and preserves non-exact and package context.

Exit condition met: an authenticated, verified user can maintain ingredients and recipes and reliably preview scaled recipes without pantry concepts.

### Stage 2 — Pantry and recommendations (Epics 3 and 5) (complete)

Completed on 20 July 2026:

1. Added the user-owned PantryEntry schema, model, factory, policy, relationships, non-negative decimal total, compatibility and deterministic merge keys, ownership indexes, and restricted ingredient/package deletion.
2. Added authorized pantry actions and PantryEntryForm. Additions normalize through the shared quantity kernel, merge under transaction locks with BCMath, and preserve explicit update/remove contracts with a Stage 3 reserved-balance seam fixed at zero for Stage 2.
3. Added AvailablePantry as the centralized stock read model. It exposes display rows, zero reserved balances, masked availability, calculation buckets, known-package metric aggregation, exact unknown-package isolation, and unlimited available staples.
4. Added the config-driven RecommendationEngine and GetPantryAwareRecommendations query. Every active owned recipe is scaled from persisted amounts, scored in memory with decimal strings, explained line by line, deterministically sorted, and only then paginated.
5. Added authenticated Livewire 4 page SFCs and Flux Free interfaces for pantry management and pantry-aware recommendations, while retaining the unranked recipe catalogue.
6. Made package content conversion immutable once a package is referenced by either a recipe requirement or pantry entry. Referenced definitions are retained if omitted during ingredient editing; a different content amount/unit requires a new definition.
7. Added focused PHPUnit coverage for exact mass/volume/count merging, semantic count isolation, package behavior, input/ownership rules, scoring fixtures, duplicate consumption, staples and temporary unavailability, non-exact explanations, deterministic tie-breaks, and bounded query counts.

Verification evidence on 20 July 2026: the Stage 2 targeted tests pass against MySQL 8.4 in the Sail container; Laravel Pint and Larastan also pass. The recommendation query remains at six queries as the recipe fixture count grows.

Exit condition met: pantry totals remain precise and non-negative, current availability is derived centrally, and every active recipe receives a deterministic, explainable pantry-aware ranking.

### Stage 3 — Dinner planning and reservation lifecycle (Epics 6 and 7) (complete)

Completed on 20 July 2026:

1. Added the singleton rolling DinnerPlan, snapshot-rich PlannedDinner/PlannedDinnerRequirement, and IngredientReservation persistence with factories and ownership policies.
2. Added planning from recipes/history, independent duplication, servings/date/order changes, cancel/restore/remove, and exactly-once cooking with unresolved confirmation snapshots.
3. Centralized reservation allocation in ReconcilePlanReservations, using the plan as the lock root and full priority ordering after every supply, demand, date, and ordering mutation.
4. Added the responsive Livewire/Flux rolling-plan and history screen plus MySQL concurrency, rollback, restoration, allocation, and consumption tests.

Exit condition met: simultaneous planning cannot over-reserve, and cook/cancel transitions preserve pantry invariants.

### Stage 4 — Grocery list (Epic 8) (complete)

Completed on 21 July 2026:

1. Added one GroceryList per rolling plan, generated/manual GroceryItem rows, explanatory GroceryItemContribution rows, ownership policies, factories, uniqueness constraints, and category/checked indexes.
2. Added the pure GroceryCalculator with immutable input/result objects, BCMath aggregation, versioned canonical keys, exact mass/volume and semantic-count behavior, known/unknown package handling, and Required non-exact checklist generation.
3. Added transactional RegenerateGroceryList at the single reconciliation exit point. It locks and replaces the complete generated projection, preserves manual items, replaces contributions, clears temporary overrides, preserves checks on equal/decreased quantities, and unchecks plus records increases.
4. Corrected Required non-exact coverage so only an available staple or positive pantry presence covers it; Optional non-exact rows remain informational. Grocery categories use the nine specification aisles plus `Other`, with unmatched ingredient categories mapped to `Other`.
5. Added authorized manual add/edit/remove, generated-quantity override, check toggle, and completed-clearing actions, plus a reusable Livewire form and responsive category-grouped Flux checklist with contribution and change explanations.
6. Updated cooking confirmation so a current generated shortfall is resolved by a checked contribution while pantry consumption remains reservation-only; checking groceries never adds pantry stock.

Verification evidence on 21 July 2026: 33 focused grocery, dinner-plan (including MySQL concurrency), pantry, and product-route tests pass with 125 assertions; Laravel Pint and Larastan pass; the Vite production build succeeds in Sail.

Exit condition met: generated shortfalls stay synchronized without overwriting manual items, and temporary shopping state follows the documented reset rules.

### Stage 5 — MVP hardening and release (complete)

Completed on 22 July 2026:

1. Replaced the starter dashboard with a query-free six-area launchpad, removed starter navigation links, removed self-service account deletion, and completed authenticated/verified grocery-route coverage.
2. Expanded the idempotent demo fixture to 34 ingredients, 10 package definitions, 10 active recipes plus one archived recipe, and action-derived planned/cooked/cancelled/manual/checked/adjusted states. Production refuses to create the known demo account.
3. Added `RecipeImageStorage`, which validates successful uploads, parsed JPEG/PNG/WebP content, byte and dimension limits, generates managed filenames, and centralizes rollback/replacement/removal cleanup. Security re-encoding is deferred until GD is approved as a required platform extension.
4. Added a connected `MvpJourneyTest`, focused image tests, route/lifecycle tests, accessible non-drag ordering controls, date-picker dialog/grid keyboard semantics, narrow-screen wrapping, and a labelled pantry table scroll region.
5. Retained the six-query recommendation baseline and added deterministic pantry/dinner/grocery ceilings. The seeded fixture measured 7/7/8 queries respectively; ten warm local Sail samples are recorded in `docs/mvp-release-checklist.md` without wall-clock CI gates.
6. Added Composer/npm Dependabot coverage and documented security, deployment-proxy, trusted Fortify QR output, backup/restore, sync queue, future worker, scheduler, rollback, and release procedures.

Baseline evidence: 135 tests and 363 assertions passed against MySQL 8.4 before changes. Final automated evidence: 143 tests and 451 assertions passed including concurrency; Pint, Larastan level 7, Vite build, npm audit, platform checks, and optimized configuration passed. The final release gate remains open for the dependency update and staging/manual checks listed in `docs/mvp-release-checklist.md`; WCAG or ASVS certification is not claimed.

### Stage 6 — Post-MVP quality gate (Epic 9)

1. Re-run the MVP automated and manual release gates against the current dependency set.
2. Trace each critical mutation from Livewire/Form Request through policy, action, transaction, model/service, and tests; record and remove actual duplication rather than reorganizing speculatively.
3. Inspect MySQL constraints/indexes and use query plans for measured hot reads. Review lock order and rollback behavior for pantry, plan, cooking, and grocery actions.
4. Exercise the nine roadmap regression scenarios, including archive/history planning, duplicate occurrences, restore, unresolved cooking, earliest-dinner allocation, grocery recalculation/check reset, unavailable staples, and manual grocery rules.
5. Update architecture, codebase guide, release checklist, and runbook with evidence and remaining risk.

Exit condition: the current suite and quality checks pass, every roadmap regression has focused coverage, and no unresolved correctness/ownership/transaction defect is knowingly carried into feature work.

### Stage 7 — Everyday usability (Epics 10–12)

1. Add the user-owned recipe_favourites relationship, actions, policies/scopes, catalogue/detail controls, filters, and pantry-safe recommendation tie-break.
2. Extend the existing dinner-history surface with bounded chronological queries, recipe/date/status filters, and lifecycle evidence; continue using PlannedDinner snapshots and history-planning actions.
3. Add a pure, seeded DecisionEngine over eligible recommendation results, Livewire exclusion/reroll state, structured explanations, and direct planning through PlanDinner.

Exit condition: personal favourites are isolated, dinner history remains accurate after recipe/lifecycle changes, and Decision Mode is deterministic, explainable, and creates an ordinary planned dinner without AI.

### Stage 8 — Recipe discovery and flexibility (Epics 13–14)

1. Reuse Tag for free-form organization and add explicit structured dietary facts with Unknown semantics.
2. Centralize catalogue, recommendation, and Decision Mode filtering in one eligibility/query contract with visible exclusion explanations.
3. Add general and recipe-specific substitution rules, explicit confirmation, and occurrence-level substitution snapshots.
4. Route substituted effective requirements through the existing full reservation reconciliation and grocery regeneration path.

Exit condition: combined filters are predictable and make no unsupported safety claims; a substitution never rewrites the recipe and is preserved in planning/history with correct quantities.

### Stage 9 — Shared households (Epic 15)

1. Add households, Owner/Member memberships, registered-user invitations, and a default household for every existing user.
2. Add an explicit active-household context and switching UI, while policies and queries independently prove membership.
3. Expand shared aggregate tables with nullable household_id, backfill and verify counts, switch reads/writes aggregate by aggregate, then enforce non-null foreign keys/indexes and retire obsolete user ownership only in a later compatible migration.
4. Migrate Ingredients/Packages/Tags/Recipes, Pantry/Batches, DinnerPlan/PlannedDinner/Reservations, and GroceryList/Items to household ownership in dependency order.
5. Keep favourites and personal preferences user-owned; add invitations and locked membership/last-owner transitions.

Exit condition: all existing data belongs to a default household, users may switch among multiple isolated households, and cross-household feature/API-style tests cannot observe or mutate foreign data.

### Stage 10 — Smarter pantry and purchasing (Epics 16–18)

1. Introduce PantryStockBatch, backfill one batch per current PantryEntry, move reservations/consumption to batches, and prove earliest-dinner then earliest-expiry allocation under concurrency.
2. Extend IngredientPackage into compatible retail purchase options and add deterministic package suggestions after exact grocery shortfall calculation.
3. Add explicit manual purchase overrides without changing exact need or generated contributions.
4. Add optional EUR minor-unit package prices, partial CostEstimate results, grocery totals, recipe additional-cost estimates, and an optional dinner-plan budget.
5. Add expiry and cost factors to recommendations only after their source data exists, with configured weights and structured explanations.

Exit condition: batch truth preserves all migrated stock, expiry affects allocation correctly, package suggestions recalculate without corrupting exact requirements, and unknown prices remain visibly unknown.

### Stage 11 — Reviewed recipe ingestion (Epics 19–20)

1. Define import DTOs/drafts and ingredient match/ambiguity results.
2. Implement reviewed JSON import first, then Schema.org extraction and hardened URL fetching.
3. Confirm through the existing Ingredient/Recipe actions; never allow an importer to write the aggregate directly.
4. Add RecipeTextExtractor with a fake, then one approved provider adapter, structured response validation, uncertainty display, timeout/cost/privacy controls, and the same review screen.
5. Enable queue infrastructure only if measured/provider latency requires it, completing the worker/monitoring/runbook gate first.

Exit condition: every imported or AI-extracted recipe is reviewed, invalid or ambiguous data cannot enter the domain, and manual/JSON import still works when AI is unavailable.

### Stage 12 — Product expansion (Epics 21–23)

1. Add confirmed barcode-to-household ingredient/package mappings and a provider adapter, followed by optional camera scanning; finish pantry entry through the normal batch action.
2. Complete a mobile usability/accessibility pass, then add manifest/icons, a versioned static asset service worker, install/update tests, and a generic offline fallback without offline household data.
3. Approve and install API token authentication, add routes/api.php with `/api/v1/households/{household}`, and expose read/write slices through Form Requests, policies, actions/queries, and API Resources.
4. Add per-token/user rate limits, consistent errors/pagination/filtering, feature/contract tests, and API documentation.

Exit condition: unknown provider/barcode/offline states retain safe manual fallbacks, the app is installable without caching private responses, and every versioned API endpoint enforces household membership while matching web action behavior.

Avoid creating every proposed folder up front. A directory appears with its first concrete class. Existing settings SFCs and starter tests may migrate toward these conventions only when touched; no big-bang rewrite is warranted.

## 24. Open questions and assumptions

### 24.1 Confirmed repository facts

- The application root namespace is App.
- It runs Laravel 13, PHP 8.5, Livewire 4, Flux UI, Fortify, MySQL 8.4, and a Sail/Docker development environment.
- Authentication/settings/passkey/2FA starter code and Stages 1–4 exist, including measurement/catalogue/recipes, pantry/recommendations, rolling dinner planning/reservations, and the synchronized grocery checklist.
- BCMath and pdo_mysql are installed in the application container.
- Stage 1–4 schemas are migrated in the reviewed MySQL testing database; outside production, the default DatabaseSeeder creates a known local demo account and an idempotent action-derived fixture covering catalogue, pantry, recommendations, rolling plans, reservations, history, and groceries.

### 24.2 Resolved MVP product decisions

The previously open product questions are resolved and are authoritative for MVP implementation:

| Decision | Architectural consequence |
| --- | --- |
| One rolling dinner list | One singleton DinnerPlan per user; Planned is the active ordered list, Cooked/Cancelled is history, with no week/name/current selector. |
| Historical/archive planning | Store complete dinner snapshots; allow archived-recipe and history-based planning, independent duplication, Cancelled restoration, and optional recipe-catalogue restoration. |
| Cooking with unresolved requirements | Require a current structured confirmation, deduct reservations only, and retain unresolved details in the Cooked snapshot. |
| Earliest-dinner allocation | Fully reconcile by explicit date, list position, creation time, and ID after every supply/demand/order change; no manual allocation UI. |
| Temporary staple unavailability | Store is_staple separately from is_currently_available; unavailable ingredients produce shortfalls without deleting stock. |
| Increased checked grocery amount | Clear checked_at on increase, retain it for equal/decreased amounts, and expose previous/new amount feedback. |
| No shopping history | Keep checked items only until cleared; delete removed manual/no-longer-required generated rows; no completed-session model. |
| Package display | Preserve package context and show package plus metric totals where meaningful; grocery need remains exact metric and never rounds to retail packs. |
| Ingredient-specific count units | Match ingredient plus semantic unit, include bulb, and prohibit automatic count-to-mass conversion. |
| Minimal recipe requirements | Require name, default servings, one ingredient, and one step; other metadata/image is nullable/unknown and does not block recommendation eligibility. |
| English with Dutch conventions | English-only MVP, metric units, DD-MM-YYYY, 24-hour time, Monday-first calendars, decimal comma or point. |
| Deferred account/data lifecycle | No self-service delete/export/custom retention; keep snapshots, ownership, Storage paths, and relationships future-safe. |
| Configurable recommendation weights | Use the Q/F/P/M/I formula in one config/scoring service, with quantity coverage dominant and non-exact rows excluded. |
| Temporary generated-quantity edits | Store a display override separate from calculated truth and clear it on any relevant recalculation; manual rows remain unchanged. |
| Required/Optional non-exact status | Required participates in coverage checks/grocery/cook confirmation; Optional is informational and never automatically missing/purchased. |

### 24.3 Remaining implementation assumptions and postponed scope

None of the original 15 product questions remains open. The following narrow implementation assumptions remain documented:

- The existing repository already requires accounts, so ownership remains per User until the explicit Epic 15 household migration completes.
- Manually removing an unprocessed Planned dinner deletes that occurrence; cancelling retains it in history. If product later wants removed-item history, use Cancelled rather than another status.
- Europe/Amsterdam is the presentation timezone implied by the Netherlands-friendly convention; timestamps remain UTC in storage.
- The initial incompatible-measurement penalty is 10 points in config/recommendations.php and may be tuned with the other accepted weights after user testing.
- Checking a grocery item records shopping progress but does not add stock automatically. A future receive-purchase action would require confirmed quantities.
- Optional images use the public Storage disk through `RecipeImageStorage`. JPEG/PNG/WebP uploads are content/MIME/dimension verified; security re-encoding, resizing, responsive derivatives, and advanced retention remain postponed until GD is approved as a required platform extension.
- Basic GroceryItem change timestamps are operational/UI state, not a user-facing shopping-history feature.

### 24.4 MVP epic support verification

The implemented MVP architecture maps Epics 1–8 to concrete owners and workflows:

- Ingredient/measurement: Ingredient, IngredientPackage, Quantity, ingredient-specific count compatibility, package/metric display, decimal persistence.
- Recipe catalogue: minimally required Recipe aggregate, nullable metadata, ordered ingredients/steps, image placeholder, actions/policies/archive/history snapshots.
- Pantry: PantryEntry totals/package context, separate staple/current availability, merge keys, owned stock actions.
- Serving scaling: RecipeScaler from immutable originals with display-only rounding.
- Pantry recommendations: AvailablePantry query, configurable Q/F/P/M/I RecommendationEngine, unavailable-staple handling, explanation view models.
- Dinner plan: singleton rolling DinnerPlan, active/history queries, complete snapshots, duplicate/cancel/restore/cook transitions.
- Reservation lifecycle: locked full-priority reconciliation, partial reservations, derived availability, unresolved confirmation/history, exactly-once consumption.
- Grocery list: stable generated keys/contributions, temporary overrides, increase-sensitive checking, manual/completed state, transactional regeneration without shopping history.

No epic requires a microservice, generic repository, event-sourced aggregate, or asynchronous core workflow.

### 24.5 Resolved post-MVP architecture decisions

These decisions resolve implementation shape while leaving product tuning to the epic-level plan:

| Decision | Architectural consequence |
| --- | --- |
| Favourite ownership | A unique User–Recipe pivot; archive does not remove it; household switching does not transfer it. |
| Favourite ranking | Pantry score and mismatch counts remain primary; favourite is a deterministic secondary tie-break unless later user testing approves a bounded configured weight. |
| Dinner history baseline | Reuse PlannedDinner and its snapshots. Add filtered queries and compact lifecycle events only to preserve repeated transition evidence. |
| Decision-session lifetime | First version is Livewire/session scoped with a stable seed and no decision-session table. |
| Dietary truth | Free-form tags stay separate. Structured facts are explicit Present/Absent; a missing row is Unknown and cannot justify a safety claim. |
| Substitution truth | A user confirms a quantity mapping; the occurrence snapshots it; effective requirements drive reservations/groceries while the recipe stays unchanged. |
| Shared ownership | Household owns shared catalogue, pantry, plan, reservation, and grocery aggregates; User owns favourites, memberships, invitation response, and active-household preference. |
| Household roles | Owner and Member only. The final owner cannot leave or be removed without an atomic ownership transfer. |
| Expiry allocation | Earliest dinner remains primary; the allocator uses earliest suitable non-expired batch within each requirement. |
| Retail package optimization | Optimize after exact shortfall; first version repeats one compatible option and ranks least excess, fewest packages, then stable ID. |
| Money | EUR integer cents initially; partial estimates expose unknown items and never substitute zero. |
| Import confirmation | Every import becomes a validated review draft and is saved through existing creation actions only after confirmation. |
| AI scope | AI extracts reviewed structured recipe data only and is never part of measurement, dietary safety, allocation, reservation, grocery, or persistence decisions. |
| Barcode scope | Provider data is a suggestion; confirmed household mappings and ordinary package/batch pantry actions are authoritative. |
| PWA scope | Installable static shell and generic fallback; no private response cache or offline mutation in the first version. |
| API shape | `/api/v1/households/{household}` delivery layer, explicit membership authorization, API Resources, and approved Sanctum token authentication for first-party/mobile use. |

### 24.6 Product and provider decisions to confirm during the relevant epic

These do not block the first vertical slice, but they must be resolved and recorded before the affected behavior ships:

- Epic 13: final structured dietary vocabulary, filter labels, and safety disclaimer copy.
- Epic 16: the configurable “expiring soon” threshold and whether users may mark a batch unavailable for reasons beyond expiry.
- Epic 17: whether a later optimizer may combine different package sizes; the first version deliberately chooses one repeated option.
- Epic 18: who enters package prices, what “budget” period the rolling plan presents, and whether any currency beyond EUR is required.
- Epic 19: import-draft retention duration and whether URL import is open to all public HTTP(S) sites or an allow-list.
- Epic 20: provider, data-processing terms, regional/privacy requirements, per-user limits, cost ceiling, and whether extraction is synchronous or queued.
- Epic 21: product-data provider, supported GTIN formats, cache lifetime, and camera/browser support matrix.
- Epic 22: final app name, icon artwork, theme colors, and install/update acceptance devices.
- Epic 23: API audience, token abilities/lifetime/revocation UX, per-endpoint rate limits, and published support/deprecation policy.

## 25. Post-MVP architecture

This section is the implementation bridge between the completed MVP and `Dinner Decider Post-MVP Development Plan.docx`. The DOCX remains authoritative for product goals, functionality, and completion criteria; this section supplies repository-specific ownership, persistence, workflows, boundaries, and verification.

### 25.1 Dependency and delivery sequence

~~~mermaid
flowchart LR
    E9["Epic 9<br/>quality gate"] --> E10["Epic 10<br/>favourites"]
    E9 --> E11["Epic 11<br/>history"]
    E10 --> E12["Epic 12<br/>decision mode"]
    E11 --> E12
    E12 --> E13["Epic 13<br/>dietary filters"]
    E13 --> E14["Epic 14<br/>substitutions"]
    E14 --> E15["Epic 15<br/>households"]
    E15 --> E16["Epic 16<br/>expiry batches"]
    E16 --> E17["Epic 17<br/>retail packages"]
    E17 --> E18["Epic 18<br/>prices/budget"]
    E15 --> E19["Epic 19<br/>recipe import"]
    E19 --> E20["Epic 20<br/>AI extraction"]
    E17 --> E21["Epic 21<br/>barcodes"]
    E15 --> E22["Epic 22<br/>PWA"]
    E15 --> E23["Epic 23<br/>public API"]
~~~

The recommended epic order remains the default because later features reuse earlier truth. Within an epic, implement one end-to-end slice at a time. A later epic may begin only when its actual dependencies are stable; independent presentation work such as the mobile audit may run earlier, but must not introduce later domain state.

### 25.2 Target module map

The application remains one Laravel modular monolith and one MySQL database. Add feature namespaces only as their first classes arrive:

| Capability | Target owners | Dependency rule |
| --- | --- | --- |
| Personal preferences | App\Actions\Favourites, User/Recipe relationship | May read accessible recipes; never owns or mutates a household recipe |
| Decision Mode | App\Queries\GetDecisionCandidates, App\Services\Decisions\DecisionEngine, App\Data\Decisions | Reads filters/recommendations/history/favourites; selection delegates to PlanDinner |
| Dietary facts/filtering | Recipe child model/enum plus App\Queries recipe eligibility | Filters catalogue/recommendations/decisions before scoring |
| Substitutions | App\Actions\Substitutions, App\Services\Substitutions, rule/snapshot models | Recipe rule definition is separate from occurrence application; planning remains reconciliation owner |
| Households | App\Actions\Households, models/policies/middleware, CurrentHousehold | Establishes owner context; other modules must not reimplement membership rules |
| Pantry batches | PantryStockBatch, batch-aware AvailablePantry/PantryAllocator | Owns stock/expiry truth; planning owns reservation orchestration |
| Purchasing and costs | App\Services\Purchasing, purchase/cost data objects | Consumes exact grocery shortfalls; never changes recipe need |
| Imports | App\Actions\Imports, App\Data\Imports, App\Services\Imports | Produces reviewed drafts; confirmation delegates to Ingredients/Recipes |
| External integrations | App\Integrations\<ProviderOrCapability> contracts/adapters | No Eloquent business orchestration; returns validated provider-neutral DTOs |
| API | App\Http\Controllers\Api\V1, App\Http\Requests\Api\V1, App\Http\Resources\Api\V1, and routes/api.php | Delivery adapter over the same policies/actions/queries |
| PWA | Vite-managed assets, manifest, service worker, offline fallback | Presentation-only first version; no alternate domain or offline database |

Do not add a generic `PreferencesService`, `IntegrationManager`, `TenantRepository`, or `ApiService`. Purpose-named actions/services and Laravel's container are sufficient.

### 25.3 Target ownership and data relationships

The post-MVP target uses concrete relationships. This is the destination after the household and batch migrations; it is not permission to create all tables during an earlier epic.

~~~mermaid
erDiagram
    USER ||--o{ HOUSEHOLD_MEMBERSHIP : has
    HOUSEHOLD ||--o{ HOUSEHOLD_MEMBERSHIP : has
    HOUSEHOLD ||--o{ HOUSEHOLD_INVITATION : issues
    USER ||--o{ HOUSEHOLD_INVITATION : receives

    HOUSEHOLD ||--o{ INGREDIENT : owns
    HOUSEHOLD ||--o{ RECIPE : owns
    USER ||--o{ RECIPE_FAVOURITE : marks
    RECIPE ||--o{ RECIPE_FAVOURITE : receives
    RECIPE ||--o{ RECIPE_DIETARY_FACT : declares
    RECIPE ||--o{ INGREDIENT_SUBSTITUTION : scopes
    INGREDIENT ||--o{ INGREDIENT_SUBSTITUTION : participates

    HOUSEHOLD ||--o{ PANTRY_ENTRY : owns
    PANTRY_ENTRY ||--o{ PANTRY_STOCK_BATCH : groups
    PANTRY_STOCK_BATCH ||--o{ INGREDIENT_RESERVATION : supplies

    HOUSEHOLD ||--|| DINNER_PLAN : has
    DINNER_PLAN ||--o{ PLANNED_DINNER : contains
    PLANNED_DINNER ||--o{ PLANNED_DINNER_STATUS_EVENT : records
    PLANNED_DINNER ||--o{ PLANNED_DINNER_REQUIREMENT : snapshots
    PLANNED_DINNER_REQUIREMENT ||--o| PLANNED_DINNER_SUBSTITUTION : applies

    DINNER_PLAN ||--|| GROCERY_LIST : generates
    GROCERY_LIST ||--o{ GROCERY_ITEM : contains
    INGREDIENT_PACKAGE ||--o{ GROCERY_ITEM : suggests_or_overrides

    HOUSEHOLD ||--o{ RECIPE_IMPORT_DRAFT : reviews
    HOUSEHOLD ||--o{ PRODUCT_BARCODE_MAPPING : confirms
~~~

Child ownership continues to flow through aggregate parents. Direct household_id columns belong on shared aggregate roots and high-volume query roots where they enforce or materially simplify isolation; do not duplicate household_id on every child without a query/constraint reason.

### 25.4 Epic 9 — MVP Quality and Architecture Review

Epic 9 is a gate, not a redesign epic. Stage 5 already added release hardening and broad evidence, so begin from the current suite and `docs/mvp-release-checklist.md`.

Required review:

1. Inventory each Livewire mutation and confirm it validates, authorizes, and calls one top-level action.
2. Trace pantry/plan/grocery actions and confirm the single `ReconcilePlanReservations` and `RegenerateGroceryList` exits remain authoritative.
3. Inspect MySQL migrations for foreign keys, uniqueness, check constraints, and indexes that match real filters/orderings. Use `EXPLAIN` for a measured query before adding an index.
4. Confirm every multi-record invariant uses one transaction and every competing inventory path locks the rolling plan/affected rows in stable order.
5. Run focused tests for all nine scenarios in the post-MVP plan and add regression coverage only where an observable gap exists.
6. Compare code, this architecture, `docs/codebase-guide.md`, release checklist, and runbook; update inaccuracies in place.

Outputs are evidence, focused fixes, and documentation updates. Do not create base classes, repositories, events, or folders merely to make the tree look more symmetrical.

Minimum verification includes the full existing suite, Pint, Larastan, Vite build, dependency/platform checks, MySQL concurrency tests, query ceilings, and the still-open staging/manual release checks. Any bug fix receives a failing regression test first.

### 25.5 Epic 10 — Recipe Favourites

**Current baseline:** Recipe is user-owned, has active/archive scopes, and is loaded by catalogue, detail, recommendation, and planning flows. There is no favourite state.

**Persistence:**

- Add `recipe_favourites` with user_id, recipe_id, and timestamps.
- Enforce unique user_id + recipe_id and index recipe_id for reverse cleanup/querying.
- Cascade from User and Recipe. Archiving does not delete the Recipe row or pivot.
- Use `belongsToMany` unless pivot behavior later justifies a RecipeFavourite model.

**Application and UI:**

- Add `AddRecipeFavourite` and `RemoveRecipeFavourite` idempotent actions under App\Actions\Favourites.
- Before households, an accessible recipe is owned by the same user. After Epic 15, accessibility means current household membership while the pivot remains user-owned.
- Add favourite controls to recipe cards/detail, an indexed catalogue filter, and eager-loaded/exists-select state without per-card queries.
- `GetPantryAwareRecommendations` may expose `is_favourite`. Sort pantry score and missing/incompatible/partial counts first, then favourite, then the existing name/ID tie-break. Do not alter the accepted Q/F/P/M/I score in this slice.

**Required tests:** add/remove idempotency, owner/foreign user denial, cross-user isolation, archive preservation, catalogue filter query bound, recommendation primary pantry ordering, and post-household accessible/inaccessible recipe behavior when Epic 15 lands.

### 25.6 Epic 11 — Cooking and Dinner History

**Current baseline:** PlannedDinner already distinguishes Planned/Cooked/Cancelled, stores cooked_at/cancelled_at/restored_at, snapshots recipe/servings/requirements, supports archived/history planning, and keeps Cooked terminal. Preserve it.

**Persistence delta:**

- Add `planned_dinner_status_events` only for durable repeated-transition evidence: planned_dinner_id, event_type (`Planned`, `Cancelled`, `Restored`, `Cooked`), from_status nullable, to_status, occurred_at, actor_user_id nullable, and timestamps.
- Index planned_dinner_id + occurred_at and actor_user_id. Events cascade with the occurrence and are not an event-sourced current-state mechanism.
- Backfill the best reconstructable events from created_at/cancelled_at/restored_at/cooked_at. Mark backfilled provenance if the UI or support tooling must distinguish reconstructed events.

**Application and UI:**

- Append the event in the same transaction as Plan/Cancel/Restore/Cook. Current PlannedDinner status/timestamps remain the source of current state.
- Add `GetDinnerHistory` as a bounded, paginated query ordered by effective terminal/event time descending with stable ID tie-break.
- Filter by owned/accessibly scoped recipe, snapshot recipe name when the source is gone, terminal status, and inclusive household-local date range converted to UTC boundaries.
- Reuse `PlanDinnerFromHistory`; never restore old reservations or grocery state.
- Recipe edits/archive/deletion must not change historical name, servings, requirement, substitution, or unresolved-cooking snapshots.

**Required tests:** chronological ordering, date boundary/DST behavior, recipe/status filters, repeated cancel/restore evidence, archived/deleted/edited recipes, historical servings, replan independence, other-owner/household isolation, and bounded pagination queries.

### 25.7 Epic 12 — Decision Mode

Decision Mode is an explainable selection layer, not a second recommendation or planning system.

**First-version contract:**

- `GetDecisionCandidates` reuses active-recipe eligibility, selected filters, `GetPantryAwareRecommendations`, favourites, and dinner history factors that already exist.
- Pure `DecisionEngine` accepts candidate result data, a stable opaque seed, round number, excluded recipe IDs, and a requested result limit. It returns `DecisionCandidate` objects with factor explanations.
- A Livewire page stores only seed/session token, round, active filters, and excluded IDs. No decision-session table is created initially.
- Seeded ordering is reproducible for the same input/version. Reroll increments the round; exclusion affects only the session and never changes Recipe.
- `PlanDecisionChoice` or the page re-runs owner/household, archive, filter, and candidate eligibility before delegating to `PlanDinner`. The planning transaction rechecks pantry truth as usual.

Pantry suitability remains the primary factor. Favourite and time-since-last-cooked may refine eligible candidates only because Epics 10 and 11 exist. Dietary, expiry, and cost factors join only after Epics 13, 16, and 18; missing factors contribute no invented default. “Random” selection chooses from the already eligible/scored pool and retains the seed/explanation.

**Required tests:** same seed/input gives the same candidates, reroll changes ordering deterministically, exclusions are session-only, archived/foreign/filtered recipes cannot be selected, explanations match factors, empty/small pools degrade clearly, duplicate occurrences remain allowed through PlanDinner, and direct planning produces ordinary snapshots/reservations/groceries.

### 25.8 Epic 13 — Recipe Tags and Dietary Filters

**Current baseline:** Tag, recipe_tag, RecipeCategory, and category_recipe already exist. Reuse their owner normalization, forms, filters, and eager-loading patterns.

**Structured facts:**

- Add `recipe_dietary_facts` with recipe_id, code, state, declared_by_user_id nullable, and timestamps; unique recipe_id + code.
- Use code-governed enums such as `DietaryFactCode` and `DietaryFactState::Present|Absent`. A missing row is `Unknown`; do not persist an Unknown row.
- Initial codes may include Vegetarian, Vegan, Pescatarian, ContainsDairy, ContainsGluten, and ContainsNuts once the Epic 13 vocabulary is approved.
- Facts are explicit recipe declarations. Do not infer them from incomplete ingredients or from free-form tags.

**Filtering:**

- Introduce one immutable `RecipeEligibilityFilter` data object used by catalogue, recommendations, and Decision Mode.
- Tags may use match-any or match-all only when the UI labels the mode. Structured positive diets require explicit Present. A “without X” safety-sensitive filter requires explicit Absent for `ContainsX`; Unknown is excluded and explained.
- The shared query applies owner/household scope, active/archive state, tag/fact existence filters, eager loading, explicit ordering, and pagination. Ranking occurs only after eligibility.
- Result objects include active filters and structured exclusion reasons; UI must not label Unknown as allergen-free.

**Required tests:** multiple tag/fact combinations, match mode, Present/Absent/Unknown, contradictory/duplicate input rejection, archive behavior, cross-owner/household isolation, consistent results across catalogue/recommendations/Decision Mode, explanations, and query ceilings.

### 25.9 Epic 14 — Ingredient Substitutions

A substitution is a user-authored rule and an explicitly selected occurrence fact. It is never an automatic alias or conversion.

**Rule persistence:**

- Add `ingredient_substitutions` with initial user_id ownership (migrated to household_id in Epic 15), nullable recipe_id for recipe-specific precedence, original_ingredient_id, substitute_ingredient_id, source amount/unit/normalized metadata, replacement amount/unit/normalized metadata, note, is_active, and timestamps.
- Both ingredients and an optional recipe must belong to the same current owner. Original and replacement ingredients must differ.
- Multiple alternatives are allowed. Recipe-specific active rules are offered before general active rules; stable name/ID order resolves display ties.
- The source and replacement sides are independently valid exact Quantities. Their explicit ratio is allowed to bridge different ingredients/groups; UnitConverter must not invent a conversion between those groups.

**Occurrence persistence:**

- Add one nullable `planned_dinner_substitutions` row per PlannedDinnerRequirement with a unique requirement foreign key.
- Snapshot the selected rule ID nullable-on-delete, original/replacement ingredient names and IDs, both ratio sides, calculated effective amount/unit/normalized amount/compatibility key, confirmation actor, and timestamp.
- The existing requirement remains the source-recipe snapshot. A single `EffectiveDinnerRequirementResolver` returns original values when no substitution exists and snapshot replacement values when it does.

**Workflow:**

1. `ApplyDinnerSubstitution` authorizes and locks the plan, Planned occurrence, requirement, affected pantry batches/entries, and reservations.
2. It requires Planned status and an explicit selected rule or fully validated ad-hoc mapping.
3. `SubstitutionCalculator` calculates `original scaled amount ÷ rule source amount × rule replacement amount` with BCMath and rejects non-positive/overflowing results.
4. It writes the occurrence snapshot, runs full reservation reconciliation, and regenerates groceries in the same transaction.
5. `RemoveDinnerSubstitution` restores the effective original requirement and runs the same reconciliation.
6. Cooking/history reads the stored occurrence snapshot. Later edits/deactivation/deletion of the reusable rule do not rewrite it.

The first slice supports exact measurable requirements only. Non-exact rows require a separate explicit product rule and must not acquire an invented amount. Unsupported package/count/mass/volume mappings fail with a clear validation/domain error unless the substitution rule itself supplies two valid exact ratio sides.

**Required tests:** general versus recipe-specific options, explicit confirmation, decimal ratio/rounding, cross-group explicit mapping, unsupported/incomplete mapping, recipe immutability, apply/remove reallocation, grocery changes/check reset, rollback, concurrent change/cook protection, history retention after rule/recipe changes, and owner/household isolation.

### 25.10 Epic 15 — Shared Households

Epic 15 is the only ownership migration. It must be implemented as several deployable slices; do not mix all schema changes, backfill, policy rewrites, and invitation UI into one migration or release.

**Foundation schema:**

| Table/column | Contract |
| --- | --- |
| households | id, name, timestamps; the shared aggregate owner |
| household_user | household_id, user_id, role Owner/Member, joined_at, timestamps; unique household_id + user_id; indexes by user and household/role |
| household_invitations | household_id, invited_user_id, invited_by_user_id, status Pending/Accepted/Declined/Revoked, expires_at nullable, responded_at nullable, timestamps; unique household + invited user with row reuse for a later invitation |
| users.active_household_id | Nullable during expansion, then points to a household of which the user is a member; deleting a household clears/replaces it through an action |

Registered users are invited by user identity; do not store a second unverified email identity in the first version. Accepting an invitation creates the membership and marks the invitation accepted atomically. Notification delivery may be added after the membership/invitation domain works and the queue gate is satisfied; it is queued after commit and never determines whether the invitation exists.

**Target ownership:**

- Household directly owns Ingredient, Recipe, RecipeCategory, Tag, PantryEntry, and DinnerPlan.
- IngredientAlias/IngredientPackage, recipe children/dietary facts, pantry batches, planned dinners/requirements/reservations, and grocery lists/items inherit through their required parents.
- Recipe favourites remain user-owned and reference accessible household recipes.
- Ingredient substitution rules become household-owned because they affect shared pantry/plan calculations.
- Import drafts and barcode mappings created after Epic 15 are household-owned and record the initiating user separately.

**Active context:**

- Add a request-scoped `CurrentHousehold` value/service and `EnsureActiveHousehold` middleware. Resolve the selected ID from the authenticated user's session/preference only after verifying membership.
- `SwitchHousehold` authorizes membership, updates active_household_id/session together, and redirects to an owned-safe landing page.
- Add household_id to log/queue Context for observability/propagation, but never use log Context as authorization.
- Policies and actions receive User plus Household/owned model and independently prove membership/role. Queries explicitly constrain the owner; avoid a hidden global tenant scope.
- Cache keys, unique constraints, route bindings, validation existence rules, and job payloads include household identity where relevant.

**Migration sequence:**

1. Create household/membership tables and nullable active/household foreign keys without changing current reads.
2. Idempotently create one default household and Owner membership per existing user. Backfill every directly owned root from its current user_id; children follow required parents.
3. Verify counts, ownership consistency, unique-name collisions, orphans, and active-household membership before switching application reads.
4. Deploy a compatibility slice that writes both legacy user_id and household_id where both remain, and reads by explicit household context feature by feature.
5. Replace per-user unique/index contracts with household equivalents and make household_id non-null only after all rows and code paths are verified.
6. Remove obsolete shared-root user_id columns and compatibility writes in a later cleanup migration, not the cutover migration.

Each deployment step needs a rollback strategy that preserves ownership data. For large tables, backfill in stable primary-key chunks through an idempotent command/job only after worker/operations readiness; for the current MVP-sized database, a bounded deployment command is still preferable to opaque model events inside DDL.

**Membership invariants:**

- Every user has at least one membership after backfill; users may belong to several households.
- Only Owner may invite/remove/transfer ownership. Member may leave.
- Removing a member never deletes their account or personal favourites.
- Lock the Household and its Owner membership rows for remove/leave/role changes. The last Owner cannot leave, be removed, or be demoted in a competing request.
- Ownership transfer is one transaction; if no transfer UI is approved, require promotion of another member before the current final Owner can leave.
- Deleting a household is not part of the first slice and must not be inferred from Owner permissions.

**Required tests:** exact data-backfill preservation, default/active household creation, switching, invite lifecycle/duplicate prevention, role policies, concurrent final-owner operations, every shared feature under two households, foreign IDs in validation/routes/actions, favourite personal behavior, cache/query isolation, and queued job context if queues are active. Add a reusable tenant-isolation data provider but keep feature assertions in the owning module.

### 25.11 Epic 16 — Pantry Expiry Dates

Expiry requires stock lots. `PantryEntry` remains the household ingredient/representation grouping record; `PantryStockBatch` becomes the authoritative quantity record beneath it.

**Schema transition:**

- Add `pantry_stock_batches`: pantry_entry_id, total_normalized_amount DECIMAL(18,6), expires_on nullable DATE, is_available boolean default true, acquired_at nullable immutable timestamp/date only if the UI needs it, and timestamps.
- Add indexes supporting pantry_entry_id + is_available + expires_on + id and household expiry views through the PantryEntry parent.
- Add nullable pantry_stock_batch_id to ingredient_reservations, backfill one batch for every existing PantryEntry and connect every reservation to that batch.
- During transition, update the current PantryEntry total atomically as a compatibility cache. Once every read/write uses batch sums and invariants are proven, remove or explicitly document the cached total; do not retain two unexplained writable truths.
- Final IngredientReservation ownership is through PantryStockBatch → PantryEntry. Remove the redundant pantry_entry_id only in a later compatible cleanup if query evidence does not justify retaining it as a checked denormalization.

**Availability and ordering:**

- A batch is usable only when is_available is true, the parent Ingredient is_currently_available is true, and expires_on is null or on/after the household-local current date.
- “Expiring soon” is a configurable date threshold used for display/scoring; it is not a persisted status and requires no scheduler to become correct.
- Preserve dinner requirement priority first. For each requirement, choose compatible usable batches by expires_on ascending with null last, then exact/native representation preference if still relevant, then batch ID.
- Expired batches remain visible/filterable and never satisfy recommendation, reservation, grocery coverage, or cooking. Deletion/adjustment follows the same confirmed reconciliation rules as current pantry reductions.
- Cooking deducts the exact reserved amount from each locked batch once. Zero batches may be removed only if no retained trace requires them; dinner history remains accurate through requirement/substitution snapshots rather than stock rows.

**Recommendation/UI delta:**

- `AvailablePantry` aggregates batch totals minus batch reservations in bounded queries.
- Pantry views group batches by entry/ingredient while showing individual expiry and availability controls and filters for expired, expiring, no-expiry, and unavailable.
- Add an expiring-stock factor to RecommendationEngine only after batch truth exists. It remains subordinate to hard eligibility and includes the specific usable amount/date in the explanation.

**Required tests:** one-entry/multiple-expiry batches, null expiry last, expired/unavailable/masked ingredient behavior, household-local date and DST boundary, earliest dinner before earliest expiry, partial multi-batch reservations, stock update/delete reconciliation, exactly-once multi-batch cooking, concurrent allocation, migration/backfill totals, recommendation explanation, filters, and bounded query counts.

### 25.12 Epic 17 — Package Sizes and Supermarket Rounding

**Current baseline:** IngredientPackage already stores package type/label and optional exact metric content, and recipes/pantry preserve package context. Extend this model; do not add a competing PackageSize table.

**Persistence delta:**

- Add package purchase-option state only where needed, such as is_purchase_option/is_active and a retail display label. Existing positive known content remains the calculation basis.
- Add separate GroceryItem purchase-projection fields or a focused child model for suggested package ID/count/purchase total and manual package ID/count/overridden_at. Choose the child model only if multiple purchase lines are approved later.
- A manual purchase override is not the existing temporary generated-quantity override. It stays visible across regeneration while its package remains compatible, and the UI marks whether it covers, exactly meets, or falls short of the current exact need.

**Calculation:**

- Pure `PackagePurchaseOptimizer` accepts one exact GroceryCalculationItem shortfall and active compatible IngredientPackage options with known positive contents.
- For each option, calculate integer `ceil(required normalized amount ÷ package content)` without float. Rank by smallest non-negative excess, then fewest packages, then stable package ID.
- First version selects one package option repeated; it does not mix package definitions. An exact unknown-package requirement may suggest counts only of that identical package definition.
- If no compatible known option exists, return `NoSuggestion` with a reason and preserve exact need/manual entry.
- `RegenerateGroceryList` calculates exact shortfall and contributions first, then attaches/recomputes the purchase suggestion. Suggested/manual purchased quantities never flow back into RecipeIngredient, PlannedDinnerRequirement, IngredientReservation, or calculated GroceryItem amount.

**Required tests:** the 650 g/500 g → 2/1,000 g example, exact divisibility, decimal boundaries, multiple options/tie-breaks, inactive/unknown/incompatible packages, count and package identity behavior, no mass-volume conversion, manual override preservation/invalidations, requirement increase/decrease, checked-state rules based on exact need, and performance bounds.

### 25.13 Epic 18 — Grocery Prices and Budgeting

**Money contract:**

- Add immutable `Money` with integer minor amount and ISO 4217 currency code; add/subtract/compare only the same currency.
- Add `CostEstimate` with known subtotal, unknown item count/identifiers, and completeness state. It never returns a deceptively complete zero.
- Initial configured/persisted currency is EUR and minor scale is two. Currency conversion and price history are outside this epic.

**Persistence and calculation:**

- Add nullable price_minor, currency_code, and price_updated_at to active purchase options, scoped with their household-owned IngredientPackage after Epic 15.
- Add optional budget_minor/budget_currency to DinnerPlan. The first UI treats it as the budget for the current rolling active plan/grocery projection; any weekly/monthly semantics require a separate product decision.
- Calculate GroceryItem estimate from the effective suggested/manual purchase package count, not from a fractional exact need that cannot be bought.
- Calculate GroceryList and recipe-additional-cost estimates by summing known purchase estimates and carrying unknown options separately. Shared package purchases across grouped exact grocery need are priced once at the grocery-item level.
- Recommendation/Decision cost uses the recipe's additional pantry shortfall, package optimizer, and current household prices. Apply mandatory dietary filters first, keep pantry suitability dominant, and expose known subtotal plus unknown count in every explanation.

Persist price inputs and budget, not every derived estimate, unless measured read cost or audit requirements later justify a versioned projection. Any persisted estimate includes an input/version timestamp and is never historical price truth.

**Required tests:** integer arithmetic, zero-priced versus missing price, mixed known/unknown totals, currency mismatch rejection, package/manual override effects, grouped purchase pricing, recipe additional cost, budget boundary, diet-before-budget eligibility, explainable ranking, household isolation, and bounded queries.

### 25.14 Epic 19 — Recipe Import

All import sources feed one provider-neutral review pipeline.

**Data and persistence:**

- Add immutable DTOs such as `RecipeImportData`, `ImportedIngredientData`, `IngredientMatch`, and `ImportValidationIssue`; use explicit array-shape PHPDoc only at JSON serialization boundaries.
- Add household-owned `recipe_import_drafts` when persistence is needed: UUID/id, initiated_by_user_id, source_type JSON/SchemaOrg/Url/Ai, sanitized source_reference nullable, status Draft/Ready/Failed/Confirmed/Expired, normalized payload JSON, issues/warnings JSON, confirmed_recipe_id nullable, expires_at, and timestamps.
- Store normalized review data, not arbitrary fetched HTML or secrets. Encrypt only if later input contains sensitive data; otherwise protect it through ordinary household policies.
- Draft retention is configured and cleaned through a scheduled command only after scheduler operations exist; access paths also reject/expire stale drafts.

**Pipeline:**

1. A source parser returns provider-neutral DTOs and never Eloquent models.
2. `IngredientMatcher` uses current-household normalized Ingredient names and aliases. Exact unique matches may be suggested; ambiguous/unknown matches remain unresolved.
3. The same QuantityInputParser, unit enums, size limits, and recipe minimum rules validate every imported row.
4. The review page exposes unsupported fields, uncertainties, ingredient mappings, quantity/unit corrections, and optional creation of missing ingredients through existing actions.
5. `ConfirmRecipeImport` locks/reloads the owned draft, revalidates every edited field/mapping, invokes existing ingredient actions as explicitly approved and `CreateRecipe`, then marks the draft confirmed in one transaction where practical. It does not duplicate SaveRecipeDetails.
6. Confirmation is idempotent: a confirmed draft returns its recipe and never creates a duplicate.

Implement JSON first. Schema.org reads `Recipe` JSON-LD defensively, including arrays/graphs and bounded text. URL import uses the Laravel HTTP client behind a `RecipeDocumentFetcher` contract with HTTP/HTTPS only, no URL credentials, DNS/resolved-address private/reserved rejection, redirect revalidation, explicit connect/request timeout, redirect/byte limits, safe content types, and translated failures. Never fetch inside the recipe-write transaction.

**Required tests:** valid/minimal JSON, malformed/oversized input, unsupported units, exact/ambiguous/unknown ingredients, edited review, no-save-before-confirmation, idempotent confirm, household isolation, archived ingredient rules, existing action reuse, Schema.org variants, SSRF/redirect/timeout/content limits, provider fake, rollback, and draft expiry.

### 25.15 Epic 20 — AI-Assisted Recipe Processing

AI is one optional source for the Epic 19 review draft.

**Boundary:**

- Define `RecipeTextExtractor` under a capability-focused App\Integrations\RecipeExtraction contract namespace. Its method accepts a bounded text/request DTO and returns provider-neutral RecipeImportData plus uncertainty/issues.
- Bind one approved provider adapter in a service provider through configuration. Keep provider SDK/HTTP response classes inside the adapter.
- Provide a deterministic fake/stub binding for feature tests. Unit-test schema/DTO validation independently from the provider.
- Version the requested structured response schema. Reject missing required structure, extra oversized collections/text, invalid quantities/units, malformed JSON, and unsupported enum values before a review draft is Ready.

**Workflow:**

1. The user pastes bounded text and explicitly starts extraction.
2. `ExtractRecipeImportDraft` records/authorizes the draft intent, then calls the provider outside any domain transaction.
3. The adapter uses explicit timeout/retry policy. Retry only failures safe to repeat and attach a provider request/correlation ID without logging recipe text or credentials.
4. Validated output becomes an Epic 19 review draft with uncertain fields highlighted. Confidence is display assistance, not permission to skip review.
5. Confirmation follows `ConfirmRecipeImport` and ordinary domain validation/actions. The provider has no database credentials or model access.

If provider latency makes synchronous Livewire requests unsuitable, use a queued extraction job only after the worker/after-commit/monitoring gate. Make the job unique/idempotent by draft and extraction version, define timeout/backoff/max attempts, store a safe failure state, and let the user retry. The manual recipe form and JSON import remain available during outages.

The implementation plan must document sent data, provider retention/training terms, region, secrets, estimated per-request cost, quotas/rate limits, deletion/retention of raw input, and user-facing privacy copy. Never send pantry, household membership, authentication, or unrelated recipe history unless an approved extraction need explicitly requires it.

**Required tests:** fake happy path, schema violations, uncertain/unsupported fields, timeout/retry/rate-limit/malformed response, safe logs, idempotent queued/synchronous retry, provider outage/manual fallback, no direct persistence, normal review confirmation, authorization/household isolation, and cost/quota enforcement.

### 25.16 Epic 21 — Barcode Scanning

Barcode scanning accelerates a normal confirmed pantry entry; it does not create a second inventory path.

**Persistence and provider boundary:**

- Add household-owned `product_barcode_mappings` with barcode string, ingredient_id, ingredient_package_id nullable, provider/provider_product_id nullable, confirmed label snapshot, confirmed_by_user_id, last_confirmed_at, and timestamps.
- Enforce unique household_id + barcode. Validate Ingredient/Package belong to that household/ingredient.
- Preserve a barcode as a canonical digit string so leading zeroes remain. Validate the approved GTIN lengths/check digit; do not cast to integer.
- Define `ProductDataProvider` returning bounded provider-neutral ProductLookupData. External name, quantity, unit, image, or category is untrusted suggestion data.
- Provider response cache keys include provider/version/barcode and contain public product facts only. Confirmed household mappings are queried separately and never shared through a global cache entry.

**Workflow:**

1. Camera scanning or manual entry supplies the same barcode field. Camera permission is requested only after user interaction and requires a secure browser context.
2. Look up the current household mapping first; otherwise call the provider through explicit timeout/error handling.
3. Show product facts, ingredient/package match, quantity/unit, uncertainty, and edits on a confirmation screen.
4. `ConfirmBarcodeMapping` validates and stores the household mapping.
5. `AddScannedPantryStock` delegates to the ordinary pantry batch/add action with the confirmed IngredientPackage, count, expiry, and availability input. It never writes stock from the browser/provider payload directly.
6. An unknown barcode or failed provider offers manual mapping and ordinary pantry entry without blocking the user.

**Required tests:** leading zero/check digits, manual/camera-equivalent input, confirmed mapping reuse, cross-household isolation, provider fake/unknown/timeout/malformed/oversized response, ingredient/package mismatch, user correction, unknown fallback, package/batch pantry action reuse, no duplicate mapping race, cache separation, and camera denial/manual usability.

### 25.17 Epic 22 — Progressive Web App

The first PWA is an installable, mobile-focused presentation layer over the same online Laravel/Livewire application.

**Assets and caching:**

- Add a standards-compliant web app manifest with approved name, start URL, scope, display mode, theme/background colors, and generated icon sizes.
- Build/register a versioned service worker through the existing Vite asset path or a small dedicated source file. Do not add a PWA dependency unless its concrete benefit and update behavior are approved.
- Precache only hashed static application assets, icons, and a generic offline fallback. Use network-only for authenticated HTML, Livewire update/upload endpoints, `/api`, logout, account settings, recipe media requiring authorization, and household data.
- Use a release/version cache name, delete obsolete static caches on activation, and avoid `skipWaiting` behavior that can interrupt an in-progress form unless the update UX handles it.
- The offline fallback contains no prior user's names, recipes, pantry, groceries, tokens, or household identifiers.

**Mobile UX:**

- Audit recipes, decision mode, pantry batch entry, dinner planning, grocery checking, household switching, imports, and barcode confirmation at narrow viewports and touch targets.
- Preserve keyboard/focus/screen-reader behavior and visible online/offline/loading/error states.
- “Installed” must not imply offline editing. Disable or explain unavailable mutations when offline; do not queue writes in IndexedDB/localStorage.
- Browser storage must not contain long-lived API tokens or serialized household data for this first version.

**Required verification:** manifest/icon validation, installability on the approved device/browser matrix, service-worker scope/update/cache cleanup, static offline fallback, no private response in Cache Storage, logout/user-switch safety, narrow-screen accessibility, degraded connection behavior, Vite production build, and manual install/update checks. Do not claim full offline support or PWA certification from a single automated audit.

### 25.18 Epic 23 — Public API

The API is a versioned delivery adapter. It is introduced only after household ownership is complete so its tenancy contract does not encode temporary user ownership.

**Foundation:**

- With explicit dependency approval, use Laravel's API installation/Sanctum path for first-party/mobile tokens. Passport is reserved for an actual OAuth2 delegation requirement.
- Add `routes/api.php` and group the initial contract under `/api/v1`. Shared resource routes are nested under `/api/v1/households/{household}` and use scoped bindings plus explicit membership policies.
- Personal endpoints such as token/favourite management still verify that referenced recipes are accessible in the named household.
- Add controllers under App\Http\Controllers\Api\V1, Form Requests under App\Http\Requests\Api\V1, and API Resources under App\Http\Resources\Api\V1. Controllers call the same actions/queries used by Livewire and return Resources; never return Eloquent models directly.
- Define one documented JSON error shape for authentication, authorization/not-found policy, validation, conflict/domain errors, throttling, and server/provider failure. Do not leak SQL, stack traces, foreign record existence, or provider secrets.

**Contract behavior:**

- Start with read-only recipes and filters, then pantry, plan, and grocery reads before adding mutations.
- Add writes one use case at a time through existing actions: favourite, pantry batch add/update, plan/change/cancel/restore/cook, grocery check/manual item, and substitution only when the web action contract is stable.
- Pagination is cursor or page based per documented resource; collection order is explicit and stable. Filters reuse RecipeEligibilityFilter and other query data objects rather than parsing independently in controllers.
- Resource representations distinguish exact need, normalized/display quantities, substituted effective need, reservations, purchase suggestions/overrides, known/unknown costs, and timestamps/timezone semantics.
- Define retry/idempotency semantics for each mutation. Naturally idempotent actions keep their current contract; duplicate-creating operations such as planning require an approved idempotency-key design before clients rely on automatic retries.
- Rate limits are segmented by authenticated user/token and endpoint cost. Provider-backed import/barcode/AI endpoints also apply their provider quota.
- Tokens use least-privilege abilities if the approved client needs read/write separation, while Household membership remains mandatory and is rechecked on every request. Membership removal takes effect immediately regardless of token lifetime.

**Documentation and tests:**

- Document endpoints, auth, abilities, version/deprecation policy, pagination/filter syntax, quantity/money shapes, timezones, errors, rate limits, idempotency, and examples in `docs/`.
- Feature/contract tests cover unauthenticated, unverified/invalid token as applicable, member/non-member, Owner-only operations, validation, conflict, resource shape, pagination/order/filtering, rate limits, token revocation, and parity with the corresponding web action.
- Add query ceilings for collection endpoints and ensure Resources do not trigger lazy-loading/N+1 queries.

Breaking representation or behavior changes require a new version or documented compatible transition. Database schema changes do not automatically become API changes.

### 25.19 Cross-epic quality and completion contract

For every post-MVP epic:

1. Start with a read-only repository analysis covering the affected migrations/models/actions/services/forms/controllers/policies/routes/views/tests/docs and the baseline/delta in this section.
2. Produce an implementation plan with current behavior, proposed schema/classes, reused code, vertical slices, acceptance tests, risks, migration/backfill/rollback, operations, and documentation updates. Resolve relevant Section 24.6 decisions before the behavior that depends on them.
3. Implement one vertical slice that reaches persistence/domain/application/delivery/tests/docs as appropriate; avoid horizontal “all models first” batches.
4. Run the narrowest feature/unit/MySQL concurrency tests, then Pint for PHP changes, Larastan when types/query shapes change, Vite for frontend assets, and any provider/PWA/API contract checks.
5. Review the complete diff for duplicated logic, authorization, household isolation, transaction/lock order, fixed-precision arithmetic, query bounds, external failure handling, accessibility, and accurate comments/docs.
6. Update the relevant stage/epic status and verified evidence in this architecture only after the code exists. Planned target tables/classes in Section 25 must not be rewritten as confirmed repository facts early.

An epic is not complete merely because its happy-path UI works. Its failure, authorization/isolation, migration/backfill, rollback/conflict, edge calculation, query/performance, and documentation obligations must be satisfied in proportion to risk.

The complete post-MVP roadmap still fits the Laravel-first modular monolith. External providers, a service worker, and a versioned API are adapters around the same application/domain truth; shared households and pantry batches are explicit schema evolutions inside the same MySQL consistency boundary.
