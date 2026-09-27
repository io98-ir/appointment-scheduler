# Customers

The people who book. One customer per phone number (E.164); the number is
also the identity of the OTP login (T4.3). A WordPress account is optional.

- **Domain:** `Customer` (first and last name, at least one; phone; email;
  birth date; note; up to 20 tags; `CustomerStatus` active or blocked) and
  `CustomerRepository`. Deletes are soft and free the phone number.
- **Application:** `CustomerService` holds the admin use cases, checks the
  `manage_customers` capability again and refuses a phone number or a
  WordPress account another customer has (`phone_taken`, `user_taken`).
  Search queries go through `Shared\Domain\SearchText`. `CustomerReader`
  implements the contract.
- **Contracts:** `CustomerApi::canBook($id)`: the customer exists, is not
  deleted and is not blocked. Booking checks it before confirming.
- **REST (admin):** `/customers` with list (`search`), create, read, replace
  (PUT) and delete (docs/api.md).
- **WordPress:** on `deleted_user` the account is unlinked; the customer
  stays.
- **Capabilities:** `manage_customers` (administrator).
- **Tables:** `customers` (data-model §2, migration `CreateCustomersTable`).
  No ascii columns (implementation-notes §4.5). `otp_codes` and
  `customer_sessions` come with T4.3.
