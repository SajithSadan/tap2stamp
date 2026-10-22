# Monetisation plan — trial → paid (to do later)

Status: **phases 1-2 built**. Phase 1 (2026-10-12): health score, segments, ladder, trial end,
pipeline stage. Phase 2 (2026-10-13): notes, tags, follow-ups, timeline, Follow-ups page, AI
weekly briefing + "Brief me" (see CLAUDE.md). Phase 3 (billing, trial end) needs the decisions
at the bottom.
Context: ~150 shops registered on a 1-month free trial. Goal: see who is really using
Tada Tap, help the ones who are stuck, and convert the healthy ones to paid plans.

Principle: **rules decide, AI explains.** Shop health is scored with fixed, explainable rules
on data we already have. AI (Gemini) only writes the summaries, suggestions and drafts.

---

## 1. Usage analysis (rules, no AI)

### Activation ladder — where each shop is stuck

| # | Step | Signal we already have |
|---|---|---|
| 1 | Signed up | `users` row |
| 2 | Shop set up | shop exists (Users page `setup`: `live` / `business` / `not_started`) |
| 3 | Ready to stamp | staff member added / device approved, or owner scanner used |
| 4 | First stamp | first `stamp_logs` row |
| 5 | Traction | 10+ customers joined (`customer_shop_cards`) |
| 6 | Loyalty working | customers returning (second-visit rate, `ShopInsights`) |
| 7 | Value delivered | first reward redeemed (`stamp_logs.action_type = reward_redeemed`) |
| 8 | Habit | stamped on most days of the last 14 |

### Health score (0–100) — inputs

- Days active out of the last 14
- Trend: this week vs last week
- New customers
- Return rate
- Rewards redeemed
- Owner's last login (activity log `auth.*`)
- Staff set up (`staff_members`, `staff_devices`)
- Counter display / QR in place (`orders`, `qr_codes.shop_id`)
- Extras used: menu, reviews, WhatsApp campaigns
- Welcomed on WhatsApp: `users.welcome_sent_at` + `whatsapp.welcome_sent` / `whatsapp.chat_opened`
  activity log entries (built 2026-10-12) - did a welcome / chat lead to a first stamp?
- Type of business: `shops.industry` (built 2026-10-12) - compare health and conversion
  by industry, and tailor offers / messages per industry.
- QR found / downloaded: `shops.qr_page_viewed_at`, `qr_downloaded_at`, `qr_downloads`,
  `qr_download_failures` + `qr.*` activity log entries (built 2026-10-12). For shops that
  print their own QR, "not opened" = may not have found it, "opened / failed" = needs help.

### Segments

- 🔴 **Never started**: signed up, no stamps
- 🟠 **Stalled**: stamped once, then stopped
- 🟡 **Trickling**: stamping, but few customers come back
- 🟢 **Healthy**: ready to convert
- ⚫ **Declining**: was healthy, now dropping. This is the biggest churn risk.

Compute in SQL with grouped queries (same style as `ShopInsights` / admin dashboard). No new
tracking needed.

---

## 2. Light CRM inside the admin panel

Build in-house (not HubSpot/Zoho). The value is in the usage data, which already lives here.
Syncing it to an external tool every day adds work and gives little back at ~150 shops.

- **Pipeline per shop**
  - Stages: Trial → Activated → Upgrade offered → Paid / Lost
  - Fields: `trial_ends_at`, `lost_reason`
- **Timeline per shop**, one feed of:
  - usage milestones
  - admin notes
  - calls and WhatsApp contacts

  Most of it already exists in `activity_logs`.
- **Follow-ups / tasks**
  - e.g. "Call Bean There on Thursday".
  - A **Today** list sorted by urgency, with trials ending within 7 days at the top.
- **Tags + notes**: e.g. "owner not techy", "needs display", "price objection".
- **Shops grid**
  - New columns: Health, Stage, Trial ends.
  - New filters on them.
  - Keep the existing WhatsApp button for one-tap contact.

Possible tables: `shop_notes`, `shop_tasks` (due date, done_at, assigned admin),
`shops.trial_ends_at`, `shops.plan`, `shops.crm_stage`, `shops.lost_reason`.
Everything must go through `Navigation`, `TenantIsolationTest` (admin-only) and the activity log as usual.

---

## 3. Where AI helps

1. **Weekly admin briefing**: "12 shops need you this week, and why". Each shop comes with
   one suggested action, for example:
   > "Chai Point: 40 customers, but nobody has reached a reward. Their card needs 12 stamps,
   > so suggest 8."
2. **Call prep**: a "Brief me" button on a shop. It gives a 5-line summary of the shop's
   numbers, trend, notes and likely objections.
3. **Message drafts**: personalised WhatsApp / email drafts per segment, in English or
   Malayalam. The admin reviews each one and sends it via the existing wa.me button.
4. **Owner's "Your month" report**: the strongest conversion tool. AI writes it from real
   numbers, for example:
   > "In your free month: 84 customers joined, 23 came back, 6 rewards earned, your
   > busiest time is Saturday 11am."

   Show it on the owner Overview and send it ~5 days before the trial ends.
5. **Classify notes / lost reasons**: after a month this shows *why* shops don't convert
   (price, staff forget, few repeat customers…).

Rules for AI:
- Send Gemini **aggregates per shop only**: never customer names or phones.
- Every new Gemini caller must call `AiUsage::record()` (new feature keys in
  `AiUsageLog::FEATURES`).
- AI only drafts and explains. It never changes data or sends messages on its own.

---

## 4. Playbook per segment

| Segment | Action |
|---|---|
| Never started | Offer a 10-minute setup call. Use **View as owner → Allow changes** to set up staff and the card for them. |
| Stalled | The QR is probably not visible, or staff forget. Push the counter display / downloadable QR, and do a staff PIN refresher. |
| Trickling | Fix the card: fewer stamps or a better reward. Show them the Insights page. |
| Healthy | Make the upgrade offer with the "Your month" report. Offer an early-bird price for paying before the trial ends. |
| Declining | Personal call within 48 hours. |

---

## 5. Gaps before anyone can pay

- **Subscription billing doesn't exist yet**: we only have one-off orders.
  - UK: Stripe Billing subscriptions (through `StripeGateway`).
  - India: Razorpay, or manual bank transfer using the existing "awaiting payment" pattern.
  - Needs `trial_ends_at` + plan per shop.
- **When a trial ends, don't hard-block straight away.** A block punishes the shop's
  customers and loses goodwill. Suggested sequence:
  1. Grace period with a banner on the owner dashboard.
  2. Read-only: cards still visible, no new stamps.
  3. Block (existing `blocked_at`) only as the last step.
- **One month is short for a loyalty product.** Most customers won't have earned a reward by
  day 30, so the owner hasn't seen the payoff yet. Consider an **activation-based extension**
  ("you're doing well, have 2 more weeks") for shops that are activated but not yet converted.
- **Fix the copy.** Some places still say "first year free" (e.g. the product description and
  the landing page plans), but the offer is 1 month. Make them match before asking anyone to pay.

---

## 6. Suggested order

1. **Week 1** ✅ built 2026-10-12:
   - health score + segments
   - Health / Stage / Trial ends columns and filters on the Shops grid

   This alone shows where the 150 stand.
2. **Week 2** ✅ built 2026-10-13:
   - CRM notes, follow-ups, timeline
   - AI weekly briefing + "Brief me" call prep
3. **Week 3**:
   - subscription billing + trial-end handling (plans, per-plan trial, paid-until and the
     "pause scanning" switch built 2026-10-20; online payment still to do)
   - owner "Your month" value report
4. **Later**: automatic nudges. Hostinger hPanel cron can run `php artisan schedule:run`;
   no queue worker is needed.

---

## Decisions needed before building

- [ ] Price per country (UK £ / India ₹ / others), monthly vs yearly
- [ ] What happens at trial end: grace period length → read-only → block?
- [ ] Do active-but-unconverted shops get a trial extension? How long?
- [ ] Payment provider for India (Razorpay vs manual bank transfer)
- [ ] Who on the team works the CRM follow-ups
