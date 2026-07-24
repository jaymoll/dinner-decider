# Dinner Decider MVP release checklist

Status: Stage 5 implemented; production release gates remain open  
Evidence date: 22 July 2026

## Executable baseline

Environment: Sail on Docker Desktop 29.6.1, PHP 8.5.8, Laravel 13.20.0, Livewire 4.3.3, Flux UI 2.15.0, MySQL 8.4, Composer 2.10.2, Node 24 in the application container. Windows bind mounts materially affect timings.

| Check | Result |
| --- | --- |
| MySQL full suite before Stage 5 | Pass: 135 tests, 363 assertions, 417.92 s |
| MySQL full suite after Stage 5 | Pass: 143 tests, 451 assertions, 304.11 s, including concurrency |
| Pint baseline | Pass |
| Larastan level 7 baseline | Pass, 171 files |
| Vite production build baseline | Pass, 57.74 s; optional Fontaine optimization warning only |
| Composer validate/platform | Pass |
| npm audit | Pass, 0 vulnerabilities |
| Optimized config/routes/views smoke | Pass; `optimize` and `about` succeeded, caches cleared afterward |
| Composer audit | **Blocked:** three medium Guzzle advisories require `guzzlehttp/guzzle >= 7.15.1`; dependency update approval is required |

Host PHP cannot resolve Docker's `mysql` service name and host Node/npm are absent, so container results are authoritative. The application container was intermittently marked unhealthy while its `/up` health check competed with the slow bind-mounted test run; production health behavior must be rechecked on staging.

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

No `EXPLAIN`-identified regression required a new index. Re-run measurements on staging before release.

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

## Security and accessibility review

- Product routes require authentication and verified email, including groceries; ownership failures are covered across ingredient, recipe, pantry, dinner, grocery, and recommendation paths.
- State changes remain Livewire/POST actions with CSRF middleware; GET product routes are read-only. Ordering inputs are allow-listed by action signatures and model fillable attributes are explicit.
- Fortify login/passkey throttling, password confirmation, session regeneration, email verification, 2FA and passkeys remain enabled and tested where automation is reliable.
- Recipe images use shared managed storage with upload-success, actual content/MIME, byte and dimension checks. Forged content, SVG, GIF, oversize and unsafe dimensions are rejected; replacement/removal/rollback cleanup and null placeholders are covered. Security re-encoding is explicitly deferred pending approval of GD as a required platform extension.
- The dinner picker exposes dialog/grid naming, selected/today state, Escape handling, arrow/Home/End movement, and focus return. Dinner order has Move up/down controls. Critical forms/actions wrap at narrow widths, fixed dinner/pantry modal minimums are removed, and the pantry table has a labelled keyboard-focusable scroll region.
- WCAG 2.2 AA and OWASP ASVS 5.0 are review frameworks only; no certification is claimed. Fortify's generated QR SVG is the documented trusted raw-output exception.
- Composer/npm/GitHub Actions Dependabot coverage is configured. TLS/HSTS and report-only-to-enforced CSP are deployment-proxy responsibilities.

## Open release gates

- Approve and apply the Guzzle security update, refresh the lock file, then rerun the full gate.
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
