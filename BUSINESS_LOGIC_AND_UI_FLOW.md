# Mess Meal & Expense Management — Business Logic & UI/UX Blueprint

This document details the complete domain logic, mathematical formulas, state machine flows, micro-caching lifecycle, and mobile app UI/UX user flows finalized for the Mess Meal & Expense Management system.

---

## 🏛️ Part 1: Domain Overview & Core Architectural Rules

### 1.1 Mess Household Structure
- **Members**: A shared living arrangement of 8 active roommates (`User` models where `is_active = true`).
- **Roles**: All active roommates are equal peers. Any roommate can log bazaar expenses, opt in/out of meals, trigger emergency day-off toggles, or review the mess ledger. A single active member acts as the monthly manager.
- **Timezone**: Strictly **`Asia/Dhaka`** (`UTC+6`). All daily cutoffs and calendar computations evaluate against `Asia/Dhaka` local time.
- **Financial Precision**: Monetary amounts are stored in smallest units or integers (fixed-point arithmetic multiplied by $1,000,000$ in intermediate calculation tables to eliminate floating-point drift).

---

## 🧮 Part 2: Business Logic & Mathematical Formulas

```
                                  ┌───────────────────────────┐
                                  │   Total Monthly Expenses   │
                                  └─────────────┬─────────────┘
                                                │
                       ┌────────────────────────┴────────────────────────┐
                       ▼                                                 ▼
        ┌─────────────────────────────┐                   ┌─────────────────────────────┐
        │        Bazar Expense        │                   │     Group Utility Expense   │
        │   (Groceries, Meat, Fish)   │                   │ (Cook salary, Wi-Fi, Gas)   │
        └──────────────┬──────────────┘                   └──────────────┬──────────────┘
                       │                                                 │
          ┌────────────┴────────────┐                                    │
          ▼                         ▼                                    ▼
┌───────────────────┐     ┌───────────────────┐                ┌───────────────────┐
│ Breakfast Expense │     │   Meal Expense    │                │  Group Share / n  │
│  (BC × Unit Price)│     │   (Bazar - BE)    │                │  (Divided by 8)   │
└───────────────────┘     └─────────┬─────────┘                └───────────────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │      Meal Rate      │
                         │   (Meal Exp / DM)   │
                         └─────────────────────┘
```

### 2.1 Month Lifecycle & Automatic Seeding
1. **1st Day of the Month Creation**:
   - On the 1st day of a new month, `Month::ongoing()` is invoked via an atomic cache lock: `Cache::lock('month:ongoing', 10)`.
   - When a `Month` model is created, a model event immediately seeds the entire month's meal entries:
     - Iterates through all days $1 \dots \text{End of Month}$ ($28, 29, 30, \text{ or } 31$ days).
     - For every active member, creates a pre-populated `Meal` row:
       - `breakfast = 0` (Opt-in by default)
       - `lunch = 2` on Fridays (Friday Feast default), `lunch = 1` on Saturday–Thursday
       - `dinner = 1` on all days
       - `has_logged = false`

### 2.2 Meal Cutoff & Quick-Action Rules
The app provides **one-tap silent toggles**:
- **Breakfast Opt-In ($0 \rightarrow 1$)**:
  - **No time cutoff**: Can be toggled at any time while the month is open.
  - Can opt in if current `breakfast == 0`.
  - Silent (no notification dispatched).
- **Lunch Opt-Out ($\text{lunch} \rightarrow 0$)**:
  - Valid strictly before **5:00 AM** on the meal date.
  - Silent.
- **Dinner Opt-Out ($\text{dinner} \rightarrow 0$)**:
  - Valid strictly before **2:20 PM** (14:20) on the meal date.
  - Silent.

### 2.3 Manual Edits, Notes & Guest Meals
- **Every single manual edit** (`PATCH /api/meals/{meal}`) and batch operation strictly requires a **`note`** (minimum 2 characters).
- Changing counts at any time (e.g., reducing Friday lunch from 2 to 1, adding guest meals, or editing someone else's count) is a manual edit that creates an immutable `audit_log` and dispatches a notification.
- **Notification Routing**:
  - **Guest Meal Alert** (`MealCountIncreasedNotification`): Triggered when a user increases their own meal count (e.g. brother visiting for lunch).
  - **Own Meal Edit** (`OwnMealEditedNotification`): Triggered when a user alters their own meals (e.g. Friday lunch 2 to 1).
  - **Peer Meal Edit** (`MemberMealEditedNotification`): Triggered when a roommate or manager adjusts another member's meal count.

### 2.4 Manager Batch Operations
- **Day Tally (`POST /api/meals/day-tally`)**: Sets identical counts for all active roommates for a single day with a mandatory audit note.
- **Day Off (`POST /api/meals/day-off`)**: Sets all active roommates' counts to $(0, 0, 0)$ for an emergency day (e.g. cook sick).
- **Date Range Off (`POST /api/meals/date-range-off`)**: Sets $(0, 0, 0)$ across multiple days (e.g. Eid vacation). Blocked if any month in the range is closed.

### 2.5 Multi-Payer Expense Splits
- An expense can have **multiple contributors** paying arbitrary portions.
- **Constraint**: $\text{Total Amount} = \sum_{i=1}^{k} \text{Contribution}_i$.
- **Categorization**:
  - `is_grouped = false`: **Bazar Expense** (edible supplies included in meal rate calculation).
  - `is_grouped = true`: **Group Utility Expense** (shared equally among all 8 active roommates).
- **Compensating Adjustments**:
  - Instead of destructive edits, corrections create additive adjustment records (`parent_id = expense.id`) that adjust the amount positively or negatively with an immutable reason.

### 2.6 Month Final Calculation & Balance Settlement

$$\text{Breakfast Expense (BE)} = \text{Total Breakfast Count (BC)} \times \text{Breakfast Unit Price}$$

$$\text{Meal Expense (ME)} = \text{Total Bazar Expense} - \text{Breakfast Expense}$$

$$\text{Meal Rate} = \frac{\text{Meal Expense (ME)}}{\text{Total Day Meals (Lunch + Dinner)}}$$

$$\text{Group Share per Person} = \frac{\text{Total Grouped Utility Expense}}{\text{Active Member Count (8)}}$$

$$\text{Member Expense}_i = (\text{BC}_i \times \text{Breakfast Unit Price}) + (\text{DM}_i \times \text{Meal Rate}) + \text{Group Share per Person}$$

$$\text{Net Balance}_i = \text{Member Expense}_i - \text{Total Member Contribution}_i$$

- **Positive Balance ($> 0$)**: Member **owes** money to the mess fund.
- **Negative Balance ($< 0$)**: Member receives a **refund** from the mess fund.
- **Zero-Sum Balance**: $\sum_{i=1}^{n} \text{Net Balance}_i = 0$.

### 2.7 6-Hour Reopening Grace Window
- After closing a month via `POST /api/months/{month}/close`, an audit log and notification are generated.
- A **6-Hour Grace Window** is enforced (`now() <= closed_at + 6 hours`).
- If an expense or meal error is discovered within 6 hours, any member can call `POST /api/months/{month}/reopen` to restore open state and purge the cached results.
- After 6 hours, the month is permanently immutable.

---

## ⚡ Part 3: Caching & Concurrency Architecture

```
User Action / Mutation ──► Invalidate Caches
                             ├── meals:today_summary:{date}
                             ├── meals:today_members:{date}
                             ├── meals:sheet:{month_id}
                             ├── meals:my_month:{user_id}:{month_id}
                             └── month_live_summary:{month_id}
```

| Cache Key | TTL | Purpose | Invalidation Triggers |
| :--- | :--- | :--- | :--- |
| `meals:today_summary:{date}` | 15s | Instant aggregate counts for hero widget | Any meal edit, opt-in/out, day tally, day off |
| `meals:today_members:{date}` | 15s | Roommate meal list on home screen | Any meal edit, opt-in/out |
| `meals:my_month:{uid}:{mid}` | 30s | User personal 31-day breakdown | Any meal edit for that user |
| `meals:sheet:{month_id}` | 30s | Full 31 Day $\times$ 8 Member matrix | Any meal edit in that month |
| `month_live_summary:{month_id}` | 30s | Real-time live rate & balances preview | Any meal edit or expense added/adjusted |
| `idempotency:{uid}:{key}` | 24h | Eliminates duplicate network submissions | Natural expiration after 24h |

---

## 📱 Part 4: Finalized Mobile App UI & Navigation Flow

The mobile client is structured around 5 primary bottom navigation tabs with dedicated sub-sheets:

```
┌────────────────────────────────────────────────────────────────────────┐
│                          TAB NAVIGATION BAR                            │
├──────────────┬──────────────┬──────────────┬─────────────┬─────────────┤
│   🏠 Today   │  📅 My Month │   📊 Sheet   │  💰 Expense │  ⚙️ Ledger  │
└──────────────┴──────────────┴──────────────┴─────────────┴─────────────┘
```

---

### Screen 1: 🏠 Today Hero Screen (Home)

```
┌────────────────────────────────────────────────────────┐
│  MessApp • Thursday, Oct 1                     🔔 (3)  │
├────────────────────────────────────────────────────────┤
│  ⚡ CUTOFF COUNTDOWN BANNER                            │
│  "Dinner cutoff in 42m (2:20 PM)"                      │
├────────────────────────────────────────────────────────┤
│  👤 MY MEALS TODAY                                     │
│  ┌─────────────────┬─────────────────┬──────────────┐  │
│  │   🥐 Breakfast  │    🍛 Lunch     │   🍲 Dinner  │  │
│  │       [ 0 ]     │      [ 1 ]      │     [ 1 ]    │  │
│  │   (+ Opt In)    │   (x Opt Out)   │  (x Opt Out) │  │
│  └─────────────────┴─────────────────┴──────────────┘  │
│  [ ✏️ Edit with Note / Add Guests ]                    │
├────────────────────────────────────────────────────────┤
│  📊 TODAY'S MESS TALLY (19 Meals Total)                │
│  • Breakfast: 2 headcount (2 units)                    │
│  • Lunch:     8 headcount (9 units)                    │
│  • Dinner:    8 headcount (8 units)                    │
├────────────────────────────────────────────────────────┤
│  👥 ROOMMATES TODAY                                    │
│  • Alice:    🥐 0  🍛 1  🍲 1 (Total: 2)               │
│  • Bob:      🥐 1  🍛 2  🍲 1 (Total: 4) 🟢 Guest      │
│  • Charlie:  🥐 0  🍛 1  🍲 1 (Total: 2)               │
│  • ...                                                 │
└────────────────────────────────────────────────────────┘
```

#### User Interactions & Gestures:
- **Instant Toggle**: Tapping `+ Opt In` on Breakfast or `x Opt Out` on Lunch/Dinner triggers an **optimistic UI state flip** and calls the silent API endpoint with background sync.
- **Micro-haptic Feedback**: Medium haptic on successful toggle; error shake animation if cutoff passed.
- **Manual Edit Bottom Sheet**: Tapping `Edit with Note` slides up a numeric picker for breakfast, lunch, dinner, and a required note text box.
- **Pull-to-Refresh**: Invalidates React Query cache and re-fetches `my-today` and `today-summary`.

---

### Screen 2: 📅 My Month Screen (Personal Breakdown)

```
┌────────────────────────────────────────────────────────┐
│  📅 My October Meals                         October ▼ │
├────────────────────────────────────────────────────────┤
│  📈 SUMMARY STATS CARD                                 │
│  Total: 67 Meals  │  🥐 5  │  🍛 31  │  🍲 31          │
│  Estimated Food Cost: ৳ 3,050                          │
├────────────────────────────────────────────────────────┤
│  DAILY CALENDAR BREAKDOWN (Scrollable)                 │
│  ┌──────────────────────────────────────────────────┐  │
│  │ Day 1 • Thursday        🥐 0  🍛 1  🍲 1  = 2    │  │
│  │ Day 2 • Friday (Feast)  🥐 0  🍛 2  🍲 1  = 3    │  │
│  │ Day 3 • Saturday        🥐 1  🍛 1  🍲 1  = 3    │  │
│  │ Day 4 • Sunday          🥐 0  🍛 0  🍲 0  = 0 🔴 │  │
│  │ ...                                              │  │
│  │ Day 31 • Saturday       🥐 0  🍛 1  🍲 1  = 2    │  │
│  └──────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────┘
```

#### Key Elements:
- Friday rows highlighted with subtle primary tint (Feast Day with 2 lunch default).
- Zero-meal days highlighted with a soft muted badge.
- Tapping any row opens the day's historical edit log.

---

### Screen 3: 📊 Full Mess Sheet (31 Day Rows $\times$ 8 Member Columns)

```
┌────────────────────────────────────────────────────────┐
│  📊 October Matrix Sheet                     October ▼ │
├────────────────────────────────────────────────────────┤
│  Sticky Header:                                        │
│  Day │ Ali │ Bob │ Cha │ Dav │ Eve │ Fra │ Geo │ Tot  │
├──────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┤
│  1   │  2  │  3  │  2  │  2  │  2  │  2  │  3  │ 18   │
│  2*  │  3  │  3  │  3  │  3  │  3  │  3  │  3  │ 24   │
│  3   │  2  │  2  │  2  │  2  │  2  │  2  │  2  │ 16   │
│ ...  │ ... │ ... │ ... │ ... │ ... │ ... │ ... │ ...  │
│ 31   │  2  │  2  │  2  │  2  │  2  │  2  │  2  │ 16   │
├──────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┤
│ TOT  │ 62  │ 68  │ 60  │ 64  │ 62  │ 60  │ 65  │ 520  │
└──────┴─────┴─────┴─────┴─────┴─────┴─────┴─────┴─────┘
```

#### Performance & UI Design:
- **Rows as Days ($1 \dots 31$)**: Vertical scroll matches natural mobile thumb scrolling.
- **Horizontal Sticky Columns**: Roommate column headers pin to the top; Day numbers pin to the left.
- **Cell Drill-Down**: Tapping any cell reveals the `(Breakfast, Lunch, Dinner)` breakdown modal for that member on that day.

---

### Screen 4: 💰 Expenses Feed & Multi-Payer Creation Modal

```
┌────────────────────────────────────────────────────────┐
│  💰 October Expenses                           [ + Add ]
├────────────────────────────────────────────────────────┤
│  [ All (৳28,000) ]  [ Bazar (৳24,000) ]  [ Utility (৳4k) ]
├────────────────────────────────────────────────────────┤
│  FEED LIST                                             │
│  ┌──────────────────────────────────────────────────┐  │
│  │ 🥩 Meat & Fish Bazar                   ৳ 3,000   │  │
│  │ Oct 5 • Paid by Alice (৳2k) & Bob (৳1k)          │  │
│  │ "Bought together from Karwan Bazar"              │  │
│  ├──────────────────────────────────────────────────┤  │
│  │ ⚡ Wi-Fi & Electricity (Utility)        ৳ 2,400   │  │
│  │ Oct 2 • Paid by Dave (৳2,400)                    │  │
│  └──────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────┘
```

#### Multi-Payer Entry Modal:
1. **Payer Selection Chips**: User toggles which roommates contributed to this purchase.
2. **Split Auto-Balance**: Entering Alice's amount automatically computes the remaining balance for Bob, or allows manual multi-split with inline sum validation ($\sum \text{contributions} == \text{total}$).
3. **Adjustment Dialog**: Long-pressing an expense opens the compensating adjustment drawer.

---

### Screen 5: ⚙️ Month Settlement & Live Rate Estimator

```
┌────────────────────────────────────────────────────────┐
│  ⚙️ October 2026 Settlement                 🟢 OPEN    │
├────────────────────────────────────────────────────────┤
│  📈 REAL-TIME PROJECTED ESTIMATOR                      │
│  • Estimated Meal Rate:   ৳ 47.50 / meal               │
│  • Breakfast Unit Price:  ৳ 20.00 / breakfast          │
│  • Group Share / Person:  ৳ 500.00                     │
│  • Total Bazar Expense:   ৳ 24,000                     │
├────────────────────────────────────────────────────────┤
│  👥 MEMBER BALANCES                                    │
│  ┌──────────────────────────────────────────────────┐  │
│  │ 🟢 Alice:  Refund ৳ 450 (Paid ৳4k, Exp ৳3,550)   │  │
│  │ 🔴 Bob:    Owes   ৳ 320 (Paid ৳3k, Exp ৳3,320)   │  │
│  │ 🟢 Dave:   Refund ৳ 120 (Paid ৳3.5k, Exp ৳3,380) │  │
│  └──────────────────────────────────────────────────┘  │
├────────────────────────────────────────────────────────┤
│  [ 🔒 Close Month & Finalize Settlements ]             │
└────────────────────────────────────────────────────────┘
```

#### Month Lifecycle Actions:
- **Close Month**: Shows a summary review modal. Once submitted, displays a **6-hour countdown banner** with a `Reopen Month` action.
- **Closed State**: Disables editing, freezes live calculations, and presents the final immutable ledger with CSV/PDF export options.

---

## 🔒 Part 5: Error Handling & Resilience Matrix

| Error Scenario | HTTP Status | Client UI Handling |
| :--- | :--- | :--- |
| Attempting opt-out after cutoff time | `400 Bad Request` | Shows alert: *"Cutoff time has passed (5:00 AM for lunch). Please use manual edit with note."* |
| Attempting to opt in/out for another user | `400 Bad Request` | Blocked on UI; returns clear error message if bypassed. |
| Negative meal counts or missing note | `422 Unprocessable` | Highlights input fields with validation error text. |
| Multi-payer contributions do not sum to total | `422 Unprocessable` | Prevents submit button click; displays remaining unpaid delta. |
| Reopening month after 6 hours | `400 Bad Request` | Displays modal: *"Reopening grace window has expired."* |
| Double submission on slow connection | `200 OK` (Replay) | Replays cached idempotent response without duplicating records (`X-Idempotent-Replay: true`). |
| Rate limit exceeded | `429 Too Many Requests` | Disables button with a retry timer toast (e.g. *"Please wait 30 seconds"*). |
