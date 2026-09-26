# Catalog

What can be booked and who serves it: locations, staff, resources, service
categories, services with their variants, staff assignments (with their own
price or duration) and resource requirements, and extras.

- **Domain:** `Service` is the only aggregate with parts (`Variant`,
  `ServiceStaff`, `ResourceRequirement`). `Service::terms()` resolves a staff
  member's duration and price for a variant. The other entities only guard
  their own fields. Invalid input throws `InvalidValue` with an error code.
  One repository interface per aggregate; deletes are soft.
- **Application:** `CatalogService` holds the admin use cases. Each checks
  the `manage_catalog` capability again (through `Shared\Domain\Authorizer`)
  and the references between aggregates. `CatalogReader` implements the
  contract.
- **Contracts:** `CatalogApi` for the other modules: `offer($variantId)` (a
  bookable variant with its active staff and their terms, and the active
  resources of each group it needs) and `location($id)`. Only active,
  non-deleted items come back. No capability check: the callers authorize.
- **REST (admin):** `/locations`, `/staff`, `/resources`,
  `/service-categories`, `/services`, `/extras`, each with list, create, read,
  replace (PUT) and delete (docs/api.md).
- **Capabilities:** `manage_catalog` (administrator).
- **Events:** the action `catalog/changed` after every repository write,
  after COMMIT (the availability cache listens to it).
- **Tables:** `locations`, `staff`, `resources`, `service_categories`,
  `services`, `service_variants`, `service_staff`, `service_resources`,
  `extras` (data-model §2, migration `CreateCatalogTables`).
