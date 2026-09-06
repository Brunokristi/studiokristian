# Generic Billing API

StudioKristian exposes a generic, versioned billing API for SaaS products represented by SaaS Projects. The API contains no product-specific billing logic.

## Authentication

Each SaaS Project has a project credential. Each Company using that product has a customer credential scoped to the same project.

Send the project credential as:

```http
Authorization: Bearer PROJECT_TOKEN
```

Send the customer credential for customer-specific endpoints as:

```http
X-Billing-Customer-Token: CUSTOMER_TOKEN
```

Administrators issue credentials through the admin API. Tokens are returned once and are stored hashed by StudioKristian.

## Available Plans

```http
GET /api/v1/billing/plans
Authorization: Bearer PROJECT_TOKEN
```

Only active plans and active prices belonging to the authenticated SaaS Project are returned. Price IDs are internal StudioKristian IDs. Stripe IDs are never returned.

## Customer Credential Provisioning (self-service)

```http
POST /api/v1/billing/customer-credentials
Authorization: Bearer PROJECT_TOKEN
Content-Type: application/json

{
    "external_reference": "42",
    "name": "ADOCare Company 42"
}
```

Lets a SaaS product provision its own Customer Credentials without a StudioKristian admin session. `external_reference` is the SaaS product's own opaque tenant id (e.g. its local Company id) and is unique per Project - StudioKristian creates a Company to scope subscriptions/trials to on first use.

Idempotent: calling again with the same `external_reference` never mints a second credential or Company. The response distinguishes the two cases:

```json
{ "data": { "id": 8, "name": "ADOCare Company 42", "token": "customer-token-returned-once", "created_at": "...", "already_provisioned": false } }
```

```json
{ "data": { "id": 8, "name": "ADOCare Company 42", "already_provisioned": true } }
```

`token` is only ever present on first issuance (`already_provisioned: false`, HTTP 201) - it cannot be retrieved again afterwards, matching admin-issued credentials. A `200` with `already_provisioned: true` means the caller already has (or already lost) the token from the original call.

## Customer Billing State

```http
GET /api/v1/billing/customer/subscriptions
Authorization: Bearer PROJECT_TOKEN
X-Billing-Customer-Token: CUSTOMER_TOKEN
```

The response contains the authenticated Company’s subscriptions for that SaaS Project, including status, plan, price, billing interval, current billing period, and cancellation state.

## Checkout

```http
POST /api/v1/billing/checkout
Authorization: Bearer PROJECT_TOKEN
X-Billing-Customer-Token: CUSTOMER_TOKEN
Idempotency-Key: unique-request-key
Content-Type: application/json

{
    "plan_price_id": 20,
    "success_url": "https://product.example/billing/success",
    "cancel_url": "https://product.example/billing/cancel",
    "customer_email": "customer@example.com"
}
```

StudioKristian verifies that the internal price belongs to the authenticated SaaS Project, is active, and belongs to an active plan. It resolves the Stripe Price server-side and creates a normal paid Checkout Session. No Stripe trial configuration is used.

The response is:

```json
{
    "id": "cs_...",
    "url": "https://checkout.stripe.com/..."
}
```

## Errors

- `401`: missing project bearer token
- `403`: invalid, revoked, or incorrectly scoped credential
- `404`: requested internal resource does not exist
- `422`: invalid request, inactive price/plan, or Stripe checkout failure
- `429`: rate limit exceeded

## Isolation

The project credential determines the SaaS Project. The customer credential determines the Company and must belong to that project. Request bodies cannot choose a Company, SaaS Project, or Stripe Price.

Stripe webhooks are centralized at `POST /api/webhooks/stripe`. Checkout and subscription metadata contains generic internal identifiers for the SaaS Project, Company, Plan, and Plan Price.

## Trials

Trials are application-managed through `CompanyTrial`. Stripe never creates or manages application trials. A paid Checkout Session starts a normal Stripe subscription, and successful billing converts the application trial through webhook processing.

For the complete setup and integration walkthrough, see [saas-billing-integration-manual.md](saas-billing-integration-manual.md).
