# StudioKristian SaaS Billing Integration Manual

This guide explains how to connect a SaaS product to StudioKristian's generic Billing API.

ADOCare is one possible consumer. Product B or any future SaaS application uses the same process.

StudioKristian owns:

- SaaS Projects
- Plans and prices
- Stripe Products and Prices
- Stripe Customers
- Checkout Sessions
- Paid subscriptions
- Invoices
- Stripe webhooks
- Billing state

The SaaS application owns:

- Its customer-facing billing page
- Its customer authentication
- Its application-specific trial and credit experience
- Redirecting customers to the Checkout URL
- Displaying the billing state returned by StudioKristian

Stripe never manages the application free trial.

## 1. Create The SaaS Project

In StudioKristian, create or identify a Project for the product.

Mark it as a SaaS Project:

```text
Project.is_saas = true
```

Each SaaS product must have its own Project. Do not share plans between products.

Examples:

```text
ADOCare       -> Project #12
Product B     -> Project #18
Product C     -> Project #24
```

The Project is the product boundary used by every Billing API request.

## 2. Create Plans And Prices

In the StudioKristian admin SaaS management screen:

1. Open the SaaS Project.
2. Create a plan.
3. Add the plan features.
4. Add the Monthly price.
5. Add the Yearly price if required.
6. Save the plan.

StudioKristian creates and synchronizes:

```text
SaaS Plan       -> Stripe Product
SaaS Plan Price -> Stripe Price
```

The SaaS application never creates Stripe Products or Stripe Prices.

The application receives the internal StudioKristian `plan_price_id`, not the Stripe Price ID.

## 3. Configure The Application Trial

Trial settings are configured on the SaaS Project, not on an individual plan.

Configure:

- Trial enabled
- Trial duration in days
- Trial credit allowance

Example:

```text
ADOCare:
30 days
100 credits
```

A different product can have different settings:

```text
Product B:
14 days
500 credits
```

A trial is associated with:

```text
Company + SaaS Project
```

It is not associated with a plan or price.

There is no Stripe Customer or Stripe Subscription required for an application trial.

## 4. Issue A Project Credential

A StudioKristian administrator can issue a project credential from the StudioKristian UI:

```text
SaaS Projects
    -> open the SaaS Project
    -> Billing API
    -> Generate Project Credential
```

Enter a descriptive name such as `ADOCare production billing API`. The token is displayed once in a dedicated dialog with a Copy Token action.

The same operation is available through the admin API.

The admin must already be authenticated as a StudioKristian administrator.

```http
POST /admin/client-portal/api/saas/projects/{project_id}/billing-api/project-credentials
Content-Type: application/json
```

Request:

```json
{
    "name": "ADOCare production billing API"
}
```

Response:

```json
{
    "id": 1,
    "name": "ADOCare production billing API",
    "token": "project-token-returned-once"
}
```

Store the token in the SaaS application's server-side secret store.

Never put the project credential in browser JavaScript, mobile app bundles, public repositories, or client-visible HTML.

The Billing API section also lists safe metadata for existing credentials:

- Name
- Created date
- Last used date
- Active/revoked status

Existing plaintext tokens cannot be retrieved. An administrator can revoke an active credential from the same section. Revocation immediately prevents future Billing API authentication with that token.

The token is sent on every Billing API request as:

```http
Authorization: Bearer PROJECT_TOKEN
```

## 5. Issue A Customer Credential

Each Company that uses a SaaS product receives a customer credential scoped to both:

```text
SaaS Project + Company
```

An administrator issues it through:

```http
POST /admin/client-portal/api/saas/projects/{project_id}/billing-api/customer-credentials
Content-Type: application/json
```

Request:

```json
{
    "company_id": 42,
    "name": "Company 42 billing session"
}
```

Response:

```json
{
    "id": 8,
    "name": "Company 42 billing session",
    "token": "customer-token-returned-once"
}
```

The SaaS application must associate this customer credential with its own authenticated Company account.

Send it only from the SaaS application's server to StudioKristian:

```http
X-Billing-Customer-Token: CUSTOMER_TOKEN
```

Do not accept a customer token from an untrusted request and forward it without checking that it belongs to the current application user.

## 6. Retrieve Available Plans

The SaaS application requests plans using only the project credential.

```bash
curl https://studio.example.com/api/v1/billing/plans \\
  -H "Authorization: Bearer PROJECT_TOKEN" \\
  -H "Accept: application/json"
```

Response example:

```json
{
    "data": [
        {
            "id": 12,
            "name": "Professional",
            "slug": "professional",
            "description": "For growing teams.",
            "features": [
                "Unlimited client records",
                "AI summaries"
            ],
            "prices": [
                {
                    "id": 20,
                    "amount": 1900,
                    "currency": "EUR",
                    "interval": "monthly",
                    "active": true
                },
                {
                    "id": 21,
                    "amount": 19000,
                    "currency": "EUR",
                    "interval": "yearly",
                    "active": true
                }
            ]
        }
    ]
}
```

Use the returned internal price ID when starting Checkout:

```text
20
```

Do not use or expose:

- `stripe_product_id`
- `stripe_price_id`
- Stripe Customer IDs
- Stripe Subscription IDs

## 7. Retrieve Customer Billing State

The SaaS application requests the billing state for the currently authenticated Company.

```bash
curl https://studio.example.com/api/v1/billing/customer/subscriptions \\
  -H "Authorization: Bearer PROJECT_TOKEN" \\
  -H "X-Billing-Customer-Token: CUSTOMER_TOKEN" \\
  -H "Accept: application/json"
```

Response example:

```json
{
    "subscriptions": [
        {
            "id": 77,
            "status": "active",
            "current_period_start": "2026-09-05T10:00:00+00:00",
            "current_period_end": "2026-10-05T10:00:00+00:00",
            "canceled_at": null,
            "ended_at": null,
            "plan": {
                "id": 12,
                "name": "Professional",
                "slug": "professional"
            },
            "price": {
                "id": 20,
                "amount": 1900,
                "currency": "EUR",
                "interval": "monthly"
            }
        }
    ],
    "trial": {
        "status": "active",
        "started_at": "2026-09-05T10:00:00+00:00",
        "expires_at": "2026-10-05T10:00:00+00:00",
        "credits_allowance": 100,
        "credits_used": 12,
        "credits_remaining": 88
    }
}
```

When there is no application trial, `trial` is `null`.

When there is no paid subscription, `subscriptions` is an empty array.

## 7a. Start The Application Trial

Starting a trial requires both the Project Credential and the Company credential:

```http
POST /api/v1/billing/customer/trial
Authorization: Bearer PROJECT_TOKEN
X-Billing-Customer-Token: CUSTOMER_TOKEN
Content-Type: application/json
```

The request body is empty. Do not send start dates, end dates, duration, credits, Company IDs, Project IDs, or Stripe IDs. StudioKristian resolves the Company and SaaS Project from the credentials and reads the trial configuration from the SaaS Project.

Example response:

```json
{
    "data": {
        "status": "active",
        "started_at": "2026-09-05T18:00:00+00:00",
        "ends_at": "2026-10-05T18:00:00+00:00",
        "credit_allowance": 100,
        "credits_used": 0,
        "credits_remaining": 100
    },
    "created": true
}
```

Calling the endpoint again returns the existing trial and does not restart it:

```json
{
    "data": {
        "status": "active"
    },
    "created": false
}
```

The dedicated trial state endpoint is:

```http
GET /api/v1/billing/customer/trial
Authorization: Bearer PROJECT_TOKEN
X-Billing-Customer-Token: CUSTOMER_TOKEN
```

Expiration is evaluated using StudioKristian server time whenever the trial is read. At or after `ends_at`, the returned status is `expired`; no scheduled job is required for correctness.

Trial creation does not create a Stripe Customer, Stripe Payment Method, Stripe Checkout Session, or Stripe Subscription. The application trial is separate from paid billing.

## 8. Start Paid Checkout

The SaaS application's server sends the selected internal price ID to StudioKristian.

```bash
curl -X POST https://studio.example.com/api/v1/billing/checkout \\
  -H "Authorization: Bearer PROJECT_TOKEN" \\
  -H "X-Billing-Customer-Token: CUSTOMER_TOKEN" \\
  -H "Idempotency-Key: company-42-price-20-unique-request" \\
  -H "Content-Type: application/json" \\
  -d '{
    "plan_price_id": 20,
    "success_url": "https://adocare.example/billing/success",
    "cancel_url": "https://adocare.example/billing/cancel",
    "customer_email": "billing@example.com"
  }'
```

Response:

```json
{
    "id": "cs_test_123",
    "url": "https://checkout.stripe.com/c/pay/cs_test_123"
}
```

The SaaS application redirects the customer to `url`.

The Checkout request performs these server-side checks:

1. Project credential is valid.
2. Customer credential is valid.
3. Customer credential belongs to the authenticated Project credential.
4. Internal price exists.
5. Internal price belongs to the authenticated SaaS Project.
6. Internal price is active.
7. Its plan is active.
8. Its Stripe Price ID exists.
9. Stripe Customer is created or reused for that Project and Company.
10. Checkout is created using the stored Stripe Price ID.

The request cannot choose:

- Another SaaS Project
- Another Company
- An arbitrary Stripe Price
- A Stripe trial

Always send an `Idempotency-Key`. Reuse the same key when retrying the same logical checkout request. Generate a new key for a genuinely new checkout attempt.

## 9. What Happens After Checkout

The sequence is:

```text
SaaS application
    -> StudioKristian Billing API
    -> Stripe Checkout
    -> Customer completes payment
    -> Stripe webhook to StudioKristian
    -> Local paid subscription synchronized
    -> SaaS application reads billing state API
```

Stripe Checkout creates a normal paid subscription. No `trial_period_days`, `trial_end`, or trial Checkout configuration is used.

The first successful paid invoice marks the application trial as converted.

The SaaS application should not treat the Checkout redirect alone as proof of payment. Refresh billing state from StudioKristian after the webhook has been processed.

## 10. Stripe Webhooks

Stripe sends webhooks only to StudioKristian:

```http
POST /api/webhooks/stripe
```

The SaaS application does not receive or process Stripe webhooks directly.

For local development:

```bash
stripe listen --forward-to 127.0.0.1:8000/api/webhooks/stripe
```

The Stripe signing secret printed by Stripe CLI must be configured in StudioKristian's environment.

Processed events are stored idempotently. Replaying the same Stripe event does not create duplicate local processing.

## 11. Local Integration Sequence

Use this order for local setup:

1. Run migrations:

```bash
php artisan migrate
```

2. Start StudioKristian:

```bash
php artisan serve
```

3. Start the frontend asset server if needed:

```bash
npm run dev
```

4. Start Stripe forwarding:

```bash
stripe listen --forward-to 127.0.0.1:8000/api/webhooks/stripe
```

5. Create a SaaS Project and mark it as SaaS.
6. Create its plan and prices in the StudioKristian admin UI.
7. Issue one project credential.
8. Issue one customer credential for a test Company.
9. Call `GET /api/v1/billing/plans`.
10. Call `GET /api/v1/billing/customer/subscriptions`.
11. Call `POST /api/v1/billing/checkout` with a test price ID.
12. Complete Checkout using Stripe test data.
13. Call the customer billing-state endpoint again after webhooks arrive.

## 12. Common Errors

### `401 Unauthorized`

The `Authorization: Bearer` project token is missing.

### `403 Forbidden`

The token is invalid, revoked, not associated with a SaaS Project, or the customer token belongs to another Project.

### `404 Not Found`

The requested internal plan price does not exist.

### `422 Unprocessable Entity`

Common causes:

- Price is inactive
- Plan is inactive
- Price belongs to another SaaS Project
- Price has no synchronized Stripe Price ID
- Invalid HTTPS return URL
- Invalid request body

### Empty plans response

Check that:

- The project credential belongs to the intended Project
- The Project has `is_saas = true`
- The plan is active
- At least one price is active

## 13. Production Rules

- Store both credentials in server-side secret storage.
- Never expose the project credential in browser code.
- Never expose Stripe IDs to the SaaS frontend.
- Never accept `company_id` from a customer-facing billing request.
- Never accept `stripe_price_id` from a customer-facing billing request.
- Always use HTTPS.
- Always send an idempotency key for Checkout.
- Treat Checkout redirects as unconfirmed until billing state updates.
- Let StudioKristian process Stripe webhooks.
- Keep application trial logic separate from Stripe subscription logic.
