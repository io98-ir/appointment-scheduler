# Catalog

What can be booked and who serves it: locations, staff, resources, service
categories, services with their variants, staff assignments (with their own
price or duration) and resource requirements, and extras.

- **Domain:** `Service` is the only aggregate with parts (`Variant`,
  `ServiceStaff`, `ResourceRequirement`). `Service::terms()` resolves a staff
  member's duration and price for a variant. The other entities only guard
  their own fields. Invalid input throws `InvalidValue` with an error code.
- **Tables:** `locations`, `staff`, `resources`, `service_categories`,
  `services`, `service_variants`, `service_staff`, `service_resources`,
  `extras` (data-model §2, migration `CreateCatalogTables`).
- **Contracts, events, capabilities:** none yet. The repositories, the admin
  REST API, its capabilities and the `CatalogApi` contract come with T1.2.
