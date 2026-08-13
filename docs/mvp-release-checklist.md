# Dinner Decider MVP release checklist

Status: Stage 6 automated quality gate passed; staging and recovery-operations gates remain open
Evidence date: 27 July 2026

## Executable baseline

Environment: Sail on Docker Desktop 29.6.1, PHP 8.5.8, Laravel 13.20.0, Livewire 4.3.3, Flux UI 2.15.0, MySQL 8.4, Composer 2.10.2, Node 24 in the application container. Windows bind mounts materially affect timings.

| Check | Result |
| --- | --- |
| Stage 6 baseline | Clean worktree at `d030ddbdb5ed02b8033566b80708834c7c4f5502`; 143 tests discovered; all 22 migrations applied |
| Focused Stage 6 slice | Pass: 30 tests, 139 assertions, 83.93 s |
| MySQL full suite | Pass: 153 tests, 516 assertions, 154.12 s, including concurrency |
| Pint | Pass after Stage 6 changes |
| Larastan level 7 | Pass, 172 files |
| Vite production build | Pass, 36.94 s; optional Fontaine optimization warning only |
| Composer validate/platform | Pass |
| Locked Composer/npm audits | Pass, 0 advisories/vulnerabilities |
| Optimized config/routes/views smoke | Pass; `optimize` and `about` succeeded, caches cleared afterward |

Host PHP cannot resolve Docker's `mysql` service name and host Node/npm are absent, so container results are authoritative. The application container was intermittently marked unhealthy while its `/up` health check competed with the slow bind-mounted test run; production health behavior must be rechecked on staging.

The former Guzzle blocker is closed: the lock file contains Guzzle 7.15.1, the complete gate was rerun, and `composer audit --locked` reports no advisories.

## Demo fixture and performance

The idempotent demo fixture contains 34 ingredients, 10 known/unknown package definitions, 10 active recipes, one archived recipe, 10 pantry rows, and planned/cooked/cancelled/manual/checked/adjusted scenarios. A second seed leaves aggregate and relationship counts unchanged. Production seeding does not create `test@example.com`.

Deterministic CI ceilings and observed demo counts:

| Read path | Demo queries | CI ceiling |
| --- | ---: | ---: |
| Recommendations | 6 | 6 |
| Pantry | 7 | 12 |
| Dinner plan | 7 | 14 |
| Groceries | 8 | 16 |

Ten warm Livewire component samples were recorded inside Sail after one discarded warm-up, using the seeded fixture on the local Windows/Docker bind mount. These are observational, not CI wall-clock assertions.

| Screen | Median | p95 |
| --- | ---: | ---: |
| Recommendations | 226.00 ms | 435.85 ms |
| Pantry | 194.69 ms | 263.29 ms |
| Dinner plan | 196.43 ms | 281.75 ms |
| Groceries | 185.35 ms | 525.86 ms |

The actual MySQL 8.4.10 definitions retain the required singleton, pantry-merge, requirement-position, reservation, generated-key, and contribution uniqueness constraints; foreign-key delete behavior matches aggregate ownership. Representative `EXPLAIN ANALYZE` results used the recipe ownership/archive index, the pantry primary/ownership filtering path, the active-plan priority index, and a grocery-list index lookup. The plan and grocery reads sort only their already-filtered two- and five-row demo sets. No evidence justified a new constraint or index. Re-run measurements on staging before release.

## Product-decision acceptance map

| Decision | Evidence |
| --- | --- |
| Rolling list; archive/history snapshots | `DinnerPlanningTest`, `DinnerLifecycleTest`, `MvpJourneyTest` |
| Unresolved cooking and exactly-once consumption | `DinnerLifecycleTest`, `GroceryManagementTest`, `MvpJourneyTest` |
| Earliest allocation and mutation reconciliation | `PantryAllocatorTest`, dinner/pantry/grocery feature tests, MySQL concurrency test |
| Staples and grocery check invalidation | `RecommendationEngineTest`, `GroceryManagementTest` |
| No shopping history and temporary overrides | `GroceryManagementTest` |
| Package/metric display and ingredient-specific counts | measurement, scaler, grocery calculator, pantry and seeder tests |
| Minimal recipe and Dutch presentation conventions | `DatabaseSeederTest`, recipe tests, `ConfigurationTest`, date-picker markup test |
| Deferred lifecycle | `ProfileUpdateTest`; deletion components removed and no route exists |
| Configurable scoring | `RecommendationEngineTest` and six-query recommendation integration test |
| Required/Optional non-exact workflow | recommendation, grocery, dinner lifecycle and journey tests |

## Stage 6 roadmap regression map

| Roadmap scenario | Direct observable evidence |
| --- | --- |
| Archived recipe can be planned again | `DinnerPlanningTest` covers the owned archived snapshot and foreign-owner denial |
| Duplicate dinner occurrences | `DinnerPlanningTest` asserts independent dinner, requirement, and reservation identities |
| Cancelled dinner restoration | `DinnerLifecycleTest` asserts current-stock reallocation, partial/missing amounts, timestamps, and cooked terminal states |
| Missing requirements require confirmation | `DinnerLifecycleTest`, `GroceryManagementTest`, and `MvpJourneyTest` cover fresh confirmation and exactly-once consumption |
| Earliest dinner receives stock first | `DinnerPlanPriorityTest` covers dated before undated, earliest date, position tie-break, reorder, and date reprioritization |
| Grocery quantities recalculate | `GroceryManagementTest` drives serving, pantry add/update/remove, and regeneration through production actions |
| Increased checked quantity becomes unchecked | `GroceryManagementTest` retains checks for equal/decreased quantities and invalidates them on a real increase |
| Unavailable staple retains staple designation | `PantryManagementTest` asserts persistence, no reservation, recommendation gap, and generated grocery need |
| Manual grocery rules | `GroceryManagementTest` covers add, update, remove, regeneration preservation, generated-item rejection, and override reset |

Transaction evidence also covers injected grocery-generation failure with no partial pantry/reservation/requirement/grocery state, competing reorder/stock/plan operations, and exactly-once concurrent cooking. Every reviewed `lockForUpdate()` is inside a transaction, and high-risk transaction retries are bounded to three attempts.

## Security and accessibility review

- Product routes require authentication and verified email, including groceries; ownership failures are covered across ingredient, recipe, pantry, dinner, grocery, and recommendation paths.
- State changes remain Livewire/POST actions with CSRF middleware; GET product routes are read-only. Ordering inputs are allow-listed by action signatures and model fillable attributes are explicit.
- Fortify login/passkey throttling, password confirmation, session regeneration, email verification, 2FA and passkeys remain enabled and tested where automation is reliable.
- Recipe images use shared managed storage with upload-success, actual content/MIME, byte and dimension checks. Forged content, SVG, GIF, oversize and unsafe dimensions are rejected; replacement/removal/rollback cleanup and null placeholders are covered. Security re-encoding is explicitly deferred pending approval of GD as a required platform extension.
- The dinner picker exposes dialog/grid naming, selected/today state, Escape handling, arrow/Home/End movement, and focus return. Dinner order has Move up/down controls. Critical forms/actions wrap at narrow widths, fixed dinner/pantry modal minimums are removed, and the pantry table has a labelled keyboard-focusable scroll region.
- WCAG 2.2 AA and OWASP ASVS 5.0 are review frameworks only; no certification is claimed. Fortify's generated QR SVG is the documented trusted raw-output exception.
- Composer/npm/GitHub Actions Dependabot coverage is configured. TLS/HSTS and report-only-to-enforced CSP are deployment-proxy responsibilities.

## Open release gates

- Playwright/axe packages were not added because dependency approval is required. Run keyboard-only, focus, screen-reader spot checks, 200% zoom, light/dark mode, and 320/375/768/1024/1440 px checks manually or approve that tooling.
- Run the two browser journeys, console/network review, passkey and 2FA secure-origin checks on staging.
- Complete the coordinated database/image backup-and-restore drill and fill the RPO/RTO/retention/owner fields in the operations runbook.
- Verify `APP_DEBUG=false`, cookie/TLS/proxy/passkey settings and CSP report-only output on the selected host.
- Perform a fresh staging deploy and MVP walkthrough. Only then change status from release candidate to released.

## Final gate order

1. Targeted tests, then Pint.
2. Full MySQL 8.4 suite including concurrency.
3. Larastan level 7 and Vite production build.
4. Composer validation/platform checks and locked Composer/npm audits.
5. Optimized-configuration smoke test.
6. Browser journeys, accessibility/responsive matrix, console/network review.
7. Isolated backup/restore drill.
8. Fresh staging deployment and walkthrough.

Steps 1–5 passed on 27 July 2026. Steps 6–8 require the staging environment and production operations decisions and remain open.
