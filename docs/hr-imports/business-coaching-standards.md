# Business Coaching Standards Reference

This document maps business coaching standards into the approved HR structure. These standards are reference data only. They do not create a visible parent menu and must be consumed by tests, checklists, KPI, planner, and OKR/TMO.

Database source:

- `business_standards`
- `business_standard_items`

Seed source:

- `database/seeds/seed_key_business_coaching_standards.php`

Admin run path:

- `admin/evaluation-builder.php` → «همگام‌سازی استانداردهای کوچینگ کسب‌وکار»

## Seed groups

| Group | Items | Used by |
|---|---:|---|
| `customer_types` | 13 | Tests, KPI, planner, OKR |
| `customer_journey` | 11 | KPI, planner, OKR |
| `sales_script` | 8 | Tests, duties, checklists, KPI, planner |
| `fab` | 4 | Tests, duties, checklists, KPI |
| `listening` | 5 | Tests, duties, KPI |
| `objections` | 8 | Duties, checklists, planner, KPI |
| `voip_call_review_ready` | 9 | KPI, planner, OKR, reports |
| `after_sales_support` | 7 | Planner, KPI, OKR, reports |
| `referral` | 5 | Planner, KPI, OKR |
| `financial_reporting` | 11 | KPI, OKR, reports |
| `bsf` | 6 | KPI, OKR |
| `sop_5s` | 9 | Duties, checklists, planner, KPI, OKR |
| `behavior` | 7 | Tests, duties, KPI |

Total: 13 groups and 103 items.

## Standards content

### customer_types

lead, new_customer, repeat_customer, loyal_customer, price_sensitive, value_oriented, bulk_organizational, unsatisfied, hesitant, indifferent, referral_customer, campaign_customer, walk_in_customer.

### customer_journey

Stages: before_purchase, during_purchase, after_purchase.

Channels: Instagram, Website, Phone, WhatsApp, In-person restaurant visit, Delivery, B2B / organizational, Referral.

### sales_script

Script is a conversation roadmap, not fixed memorized text. It starts with questions, requires listening before presenting, uses customer language, avoids unnecessary technical terms, ends with a clear CTA, and improves continuously.

### fab

Feature, Advantage, Benefit. Customer-facing conversation should start with Benefit.

### listening

Ask open questions, ask then stay silent, take notes, reflect back, and do not interrupt.

### objections

price, time, warranty, trust, bad experience, competitor comparison, hesitation, unclear need.

### voip_call_review_ready

Phone number display, call recording, call report by employee, CRM link, follow-up scheduling, multi-user queue, auto response, call quality score, script compliance score.

No VoIP module is built in this phase. These rows only prepare data and future integration points.

### after_sales_support

Complaint registration, issue category, resolution time, resolved/unresolved, follow-up after resolution, customer satisfaction after resolution, repeat purchase after complaint.

### referral

Referred customer, referrer customer, referral channel, referral follow-up, referral conversion.

### financial_reporting

Monthly revenue, cost by category, cash flow, bank balance, cashbox balance, receivables, payables, profit margin, campaign cost, ROAS, CAC.

### bsf

Formula: Lead x Conversion Rate x Purchase Count x Average Purchase x Profit Margin.

Items: lead_count, conversion_rate, purchase_count, average_purchase_value, profit_margin.

### sop_5s

SOP execution, cleanliness, order, discipline, hygiene, branch readiness, issue reporting, corrective action, 5S audit.

### behavior

Responsibility, ownership, discipline, teamwork, customer orientation, calm communication, professional complaint handling.

## Validation

- Seed can be run repeatedly because rows use stable `standard_key` and `item_key` values with upsert semantics.
- Running the seed twice must keep the row count at 13 groups and 103 items.
- No Business Standards menu item should be added.
- No public website route, CSS, API, or analytics table should change.
