# Mess Meal & Expense Management System — API Specification & Architecture Blueprint

This document defines the complete REST API contract, business rules, payloads, rate limits, caching policies, and error handling for the Mess Meal & Expense Management backend application.

---

## 🏗️ Architecture & Core Rules

1. **Authentication & Guards**:
   - `auth:sanctum` protects all private endpoints.
   - User must be `is_active = true` to authenticate and perform operations.
   - Revocation of push device tokens is supported on logout.
2. **Timezone & Time-Aware Cutoffs**:
   - Application runs on **`Asia/Dhaka`** (`UTC+6`).
   - Cutoff times:
     - **Breakfast Opt-in**: **No time cutoff** (open one-tap toggle $0 \rightarrow 1$ anytime while month is open).
     - **Lunch Opt-out**: Before **5:00 AM** on meal date.
     - **Dinner Opt-out**: Before **2:20 PM** (14:20) on meal date.
   - All comparisons use 24-hour exact comparisons (`setTime(5, 0, 0)` and `setTime(14, 20, 0)`).
3. **Micro-Caching & Reactive Invalidation**:
   - High-traffic aggregate endpoints are cached for short windows to eliminate N+1 DB strain during peak hours:
     - `meals:today_summary:{date}`: **15 seconds**
     - `meals:today_members:{date}`: **15 seconds**
     - `meals:my_month:{user_id}:{month_id}`: **30 seconds**
     - `meals:sheet:{month_id}`: **30 seconds**
     - `month_live_summary:{month_id}`: **30 seconds**
   - **Active Invalidation**: Any meal edit, batch update, or expense mutation immediately evicts all related caches via `MealService::invalidateMealCaches` and `ExpenseService`.
4. **Idempotency**:
   - Financial mutations (`expenses.store`, `expenses.adjust`), month status mutations (`months.close`, `months.reopen`), and batch meal edits (`meals.update`, `meals.day-tally`, `meals.day-off`, `meals.date-range-off`) use the `idempotent` middleware.
   - Cache key is strictly scoped by User ID, HTTP Method, and Path: `idempotency:{uid}:{method}:{path}:{key}`.
   - Clients send `X-Idempotency-Key: <UUID>`. Cached response is replayed on identical keys with header `X-Idempotent-Replay: true`.
5. **Rate Limiting**:
   - Inline throttles declared per route:
     - Auth login: `throttle:login` (5 attempts / min)
     - Month close: `throttle:3,1` (3 requests / min)
     - Month reopen: `throttle:5,1` (5 requests / min)
     - Broadcast announcement: `throttle:3,1` (3 requests / min)
     - Date range meal off: `throttle:5,1` (5 requests / min)
     - Day tally & Day off: `throttle:10,1` (10 requests / min)
     - Single meal edits & quick actions: `throttle:30,1` (30 requests / min)
6. **Audit Logging & Push Notifications**:
   - All manual meal edits, mass updates, and expense adjustments write an immutable record to `audit_logs`.
   - Notifications are dispatched simultaneously to Expo Push Notification tokens and in-app database notifications.
7. **Database Transactions**:
   - All write mutations run inside `DB::transaction()` with atomic concurrency locks (`Month::ongoing()` uses `Cache::lock('month:ongoing', 10)`).

---

## 📑 Complete API Routes Matrix (38 Routes)

| Area | Method | Endpoint | Name | Middleware / Protections |
| :--- | :--- | :--- | :--- | :--- |
| **Auth** | `POST` | `/api/auth/login` | `auth.login` | `throttle:login` |
| | `POST` | `/api/auth/logout` | `auth.logout` | `auth:sanctum` |
| | `GET` | `/api/auth/me` | `auth.me` | `auth:sanctum` |
| **Tokens** | `POST` | `/api/device-tokens` | `device-tokens.store` | `auth:sanctum` |
| | `DELETE` | `/api/device-tokens` | `device-tokens.destroy` | `auth:sanctum` |
| **Members** | `GET` | `/api/members` | `members.index` | `auth:sanctum` |
| **Audit Logs**| `GET` | `/api/audit-logs` | `audit-logs.index` | `auth:sanctum` |
| **Expenses** | `GET` | `/api/expenses` | `expenses.index` | `auth:sanctum` |
| | `GET` | `/api/expenses/summary` | `expenses.summary` | `auth:sanctum` |
| | `GET` | `/api/expenses/{expense}` | `expenses.show` | `auth:sanctum` |
| | `POST` | `/api/expenses` | `expenses.store` | `auth:sanctum`, `idempotent` |
| | `POST` | `/api/expenses/{expense}/adjust` | `expenses.adjust` | `auth:sanctum`, `idempotent` |
| **Months** | `GET` | `/api/months` | `months.index` | `auth:sanctum` |
| | `GET` | `/api/months/current` | `months.current` | `auth:sanctum` |
| | `GET` | `/api/months/{month}/live-summary` | `months.live-summary` | `auth:sanctum`, 30s cache |
| | `GET` | `/api/months/{month}/results` | `months.results` | `auth:sanctum` |
| | `POST` | `/api/months/{month}/close` | `months.close` | `auth:sanctum`, `idempotent`, `throttle:3,1` |
| | `POST` | `/api/months/{month}/reopen` | `months.reopen` | `auth:sanctum`, `idempotent`, `throttle:5,1` |
| | `PATCH` | `/api/months/{month}/breakfast-price` | `months.breakfast-price` | `auth:sanctum`, `throttle:10,1` |
| **Meals** | `GET` | `/api/meals/my-today` | `meals.my-today` | `auth:sanctum` |
| | `GET` | `/api/meals/today-summary` | `meals.today-summary` | `auth:sanctum`, 15s cache |
| | `GET` | `/api/meals/today-members` | `meals.today-members` | `auth:sanctum`, 15s cache |
| | `GET` | `/api/meals/today` | `meals.today` | `auth:sanctum` |
| | `GET` | `/api/meals/my-month` | `meals.my-month` | `auth:sanctum`, 30s cache |
| | `GET` | `/api/meals/sheet` | `meals.sheet` | `auth:sanctum`, 30s cache |
| | `GET` | `/api/meals/by-date/{date}` | `meals.by-date` | `auth:sanctum` |
| | `GET` | `/api/meals/{meal}/history` | `meals.history` | `auth:sanctum` |
| | `POST` | `/api/meals/{meal}/opt-in-breakfast` | `meals.opt-in-breakfast` | `auth:sanctum`, `throttle:30,1` |
| | `POST` | `/api/meals/{meal}/opt-out-lunch` | `meals.opt-out-lunch` | `auth:sanctum`, `throttle:30,1` |
| | `POST` | `/api/meals/{meal}/opt-out-dinner` | `meals.opt-out-dinner` | `auth:sanctum`, `throttle:30,1` |
| | `PATCH` | `/api/meals/{meal}` | `meals.update` | `auth:sanctum`, `idempotent`, `throttle:30,1` |
| | `POST` | `/api/meals/day-tally` | `meals.day-tally` | `auth:sanctum`, `idempotent`, `throttle:10,1` |
| | `POST` | `/api/meals/day-off` | `meals.day-off` | `auth:sanctum`, `idempotent`, `throttle:10,1` |
| | `POST` | `/api/meals/date-range-off` | `meals.date-range-off` | `auth:sanctum`, `idempotent`, `throttle:5,1` |
| **Notifications** | `GET` | `/api/notifications` | `notifications.index` | `auth:sanctum` |
| | `PATCH` | `/api/notifications/{id}/read` | `notifications.read` | `auth:sanctum` |
| | `POST` | `/api/notifications/read-all` | `notifications.read-all` | `auth:sanctum`, `throttle:10,1` |
| | `POST` | `/api/notifications/broadcast` | `notifications.broadcast` | `auth:sanctum`, `throttle:3,1` |

---

## 📡 Detailed Endpoint Contracts

### 1. Authentication & Tokens

#### `POST /api/auth/login`
- **Rate Limit**: 5 attempts / min (`throttle:login`)
- **Body**:
  ```json
  {
    "email": "user@example.com",
    "password": "password123",
    "device_name": "Pixel 8",
    "device_token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]"
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "message": "Login successful.",
    "token": "1|sanctum_plain_text_token...",
    "user": {
      "id": 1,
      "name": "Alice",
      "email": "alice@example.com",
      "is_active": true,
      "created_at": "2026-10-01T00:00:00.000000Z"
    }
  }
  ```

#### `POST /api/auth/logout`
- **Headers**: `Authorization: Bearer <token>`
- **Body** *(Optional)*: `{ "device_token": "ExponentPushToken[...]" }`
- **Response (200 OK)**: `{ "message": "Successfully logged out." }`

#### `GET /api/auth/me`
- **Headers**: `Authorization: Bearer <token>`
- **Response (200 OK)**: `{ "data": { "id": 1, "name": "Alice", "email": "alice@example.com", "is_active": true, "created_at": "..." } }`

#### `POST /api/device-tokens`
- **Body**: `{ "token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]" }`
- **Validation**: Regex check for `/^Expo(nent)?PushToken\[[a-zA-Z0-9_\-]+\]$/` or raw token string.
- **Response (200 OK)**: `{ "message": "Device token registered successfully.", "device_token": { "id": 1, "token": "...", "user_id": 1 } }`

#### `DELETE /api/device-tokens`
- **Body**: `{ "token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]" }`
- **Response (200 OK)**: `{ "message": "Device token unregistered successfully." }`

---

### 2. Members & Audit Logs

#### `GET /api/members`
- **Response (200 OK)**:
  ```json
  {
    "data": [
      { "id": 1, "name": "Alice", "email": "alice@example.com", "is_active": true, "created_at": "..." },
      { "id": 2, "name": "Bob", "email": "bob@example.com", "is_active": true, "created_at": "..." }
    ]
  }
  ```

#### `GET /api/audit-logs`
- **Query Params**: `action`, `user_id`, `auditable_type`, `auditable_id`, `page`, `per_page`
- **Response (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "action": "meal.edit",
        "user": { "id": 1, "name": "Alice", "email": "alice@example.com" },
        "auditable_type": "Meal",
        "auditable_id": 42,
        "before": { "breakfast": 0, "lunch": 1, "dinner": 1 },
        "after": { "breakfast": 1, "lunch": 2, "dinner": 1 },
        "note": "Guest lunch meal for brother",
        "created_at": "2026-10-01T12:00:00.000000Z"
      }
    ],
    "links": { ... },
    "meta": { ... }
  }
  ```

---

### 3. Meals & Daily Operations

#### `GET /api/meals/my-today`
- **Query Params**: `date` (YYYY-MM-DD, optional)
- **Response (200 OK)**:
  ```json
  {
    "data": {
      "server_time": "2026-10-01T04:15:00+06:00",
      "date": "2026-10-01",
      "is_month_closed": false,
      "cutoffs": {
        "breakfast_cutoff": null,
        "lunch_cutoff": "05:00",
        "dinner_cutoff": "14:20",
        "breakfast_opt_in_allowed": true,
        "lunch_opt_out_allowed": true,
        "dinner_opt_out_allowed": true
      },
      "meal": {
        "id": 101,
        "user_id": 1,
        "date": "2026-10-01",
        "breakfast": 0,
        "lunch": 1,
        "dinner": 1,
        "total": 2,
        "has_logged": false,
        "is_editable": true
      }
    }
  }
  ```

#### `GET /api/meals/today-summary`
- **Cache**: 15s micro-cache
- **Query Params**: `date` (YYYY-MM-DD, optional)
- **Response (200 OK)**:
  ```json
  {
    "data": {
      "date": "2026-10-01",
      "breakfast": { "headcount": 2, "units": 2 },
      "lunch": { "headcount": 8, "units": 9 },
      "dinner": { "headcount": 8, "units": 8 },
      "total_units": 19
    }
  }
  ```

#### `GET /api/meals/today-members`
- **Cache**: 15s micro-cache
- **Response (200 OK)**:
  ```json
  {
    "data": [
      {
        "meal_id": 101,
        "user_id": 1,
        "name": "Alice",
        "breakfast": 0,
        "lunch": 1,
        "dinner": 1,
        "total": 2,
        "has_logged": false
      }
    ]
  }
  ```

#### `GET /api/meals/today`
- **Response (200 OK)**: Composite payload combining `server_time`, `date`, `summary`, and `members`.

#### `GET /api/meals/my-month`
- **Cache**: 30s micro-cache
- **Query Params**: `month_id` OR (`year` & `month`)
- **Response (200 OK)**:
  ```json
  {
    "data": {
      "month": { "id": 1, "year": 2026, "month": 10, "is_closed": false },
      "totals": { "breakfast": 5, "lunch": 31, "dinner": 31, "total_meals": 67 },
      "days": [
        {
          "id": 101,
          "date": "2026-10-01",
          "day": 1,
          "day_name": "Thursday",
          "is_friday": false,
          "breakfast": 0,
          "lunch": 1,
          "dinner": 1,
          "total": 2,
          "has_logged": false
        }
      ]
    }
  }
  ```

#### `GET /api/meals/sheet`
- **Cache**: 30s micro-cache
- **Structure**: **31 Day Rows $\times$ 8 Member Columns** (optimized for vertical mobile scrolling).
- **Response (200 OK)**:
  ```json
  {
    "data": {
      "month": { "id": 1, "year": 2026, "month": 10, "is_closed": false, "breakfast_price": 20 },
      "members": [
        { "id": 1, "name": "Alice" },
        { "id": 2, "name": "Bob" }
      ],
      "rows": [
        {
          "date": "2026-10-01",
          "day": 1,
          "day_name": "Thursday",
          "is_friday": false,
          "meals": {
            "1": { "meal_id": 101, "breakfast": 0, "lunch": 1, "dinner": 1, "total": 2 },
            "2": { "meal_id": 102, "breakfast": 1, "lunch": 1, "dinner": 1, "total": 3 }
          },
          "daily_total": 5
        }
      ],
      "member_totals": [
        { "user_id": 1, "name": "Alice", "breakfast": 5, "lunch": 31, "dinner": 31, "total": 67 }
      ],
      "grand_total": 520
    }
  }
  ```

#### `POST /api/meals/{meal}/opt-in-breakfast`
- **Rules**: Must be own meal; current breakfast must be 0; month must be open. No time cutoff. Sets `breakfast = 1`.
- **Response (200 OK)**: Returns updated `MealResource`.

#### `POST /api/meals/{meal}/opt-out-lunch`
- **Rules**: Must be own meal; current time must be $< \text{5:00 AM}$. Sets `lunch = 0`.
- **Response (200 OK)**: Returns updated `MealResource`.

#### `POST /api/meals/{meal}/opt-out-dinner`
- **Rules**: Must be own meal; current time must be $< \text{14:20}$ (2:20 PM). Sets `dinner = 0`.
- **Response (200 OK)**: Returns updated `MealResource`.

#### `PATCH /api/meals/{meal}`
- **Headers**: `X-Idempotency-Key: <UUID>`
- **Body**:
  ```json
  {
    "breakfast": 0,
    "lunch": 3,
    "dinner": 1,
    "note": "2 guest lunch meals for brother"
  }
  ```
- **Notifications**:
  - If editor is owner and single meal increased: `MealCountIncreasedNotification` (Guest meal alert).
  - If editor is owner: `OwnMealEditedNotification`.
  - If editor is another member: `MemberMealEditedNotification` sent to meal owner.

#### `POST /api/meals/day-tally`
- **Headers**: `X-Idempotency-Key: <UUID>`
- **Body**: `{ "date": "2026-10-05", "breakfast": 1, "lunch": 1, "dinner": 1, "note": "Regular tally set for all" }`
- **Response (200 OK)**: `{ "message": "Day meal tally updated successfully.", "affected_count": 8 }`

#### `POST /api/meals/day-off`
- **Headers**: `X-Idempotency-Key: <UUID>`
- **Body**: `{ "date": "2026-10-10", "note": "Eid Mess Closed" }`
- **Response (200 OK)**: `{ "message": "Day meals turned off successfully.", "affected_count": 8 }`

#### `POST /api/meals/date-range-off`
- **Headers**: `X-Idempotency-Key: <UUID>`
- **Body**: `{ "from_date": "2026-10-10", "to_date": "2026-10-14", "note": "Puja holidays" }`
- **Response (200 OK)**: `{ "message": "Date range meals turned off successfully.", "affected_count": 40 }`

---

### 4. Expenses & Multi-Payer Split

#### `GET /api/expenses`
- **Query Params**: `month_id`, `is_grouped` (boolean), `user_id`, `from_date`, `to_date`, `page`, `per_page`
- **Response (200 OK)**: Paginated `ExpenseResource` list with nested `contributions` and `adjustments`.

#### `GET /api/expenses/summary?month_id=...`
- **Response (200 OK)**:
  ```json
  {
    "month_id": 1,
    "total_expense": 25000,
    "bazar_expense": 20000,
    "grouped_expense": 5000,
    "member_contributions": [
      {
        "user_id": 1,
        "name": "Alice",
        "total_paid": 8000,
        "bazar_paid": 6000,
        "grouped_paid": 2000
      }
    ]
  }
  ```

#### `POST /api/expenses`
- **Headers**: `X-Idempotency-Key: <UUID>`
- **Body**:
  ```json
  {
    "month_id": 1,
    "date": "2026-10-05",
    "cause": "Daily Meat & Fish Bazar",
    "note": "Purchased together by Alice & Bob",
    "is_grouped": false,
    "amount": 3000,
    "contributions": [
      { "user_id": 1, "amount": 2000 },
      { "user_id": 2, "amount": 1000 }
    ]
  }
  ```
- **Validation**: `amount` must strictly equal $\sum \text{contributions.amount}$.

#### `POST /api/expenses/{expense}/adjust`
- **Headers**: `X-Idempotency-Key: <UUID>`
- **Body**:
  ```json
  {
    "amount": -200,
    "reason": "Vegetable discount refund from vendor",
    "contributions": [
      { "user_id": 1, "amount": -200 }
    ]
  }
  ```
- **Rule**: Compensating additive adjustment attached via `parent_id`.

---

### 5. Month Lifecycle & Settlements

#### `GET /api/months/current`
- **Response (200 OK)**: Returns or creates ongoing month with atomic lock.

#### `GET /api/months/{month}/live-summary`
- **Cache**: 30s micro-cache
- **Response (200 OK)**:
  ```json
  {
    "month_id": 1,
    "year": 2026,
    "month": 10,
    "is_closed": false,
    "breakfast_price": 20,
    "participant_count": 8,
    "total_bazar_expense": 24000,
    "total_grouped_expense": 4000,
    "total_breakfast_count": 60,
    "total_day_meals": 480,
    "breakfast_expense": 1200,
    "meal_expense": 22800,
    "meal_rate": 47.5,
    "group_expense_per_person": 500,
    "participants": [
      {
        "user_id": 1,
        "name": "Alice",
        "breakfast_count": 10,
        "day_meal_count": 60,
        "total_meals": 70,
        "breakfast_expense": 200,
        "meal_expense": 2850,
        "group_expense": 500,
        "total_expense": 3550,
        "total_contribution": 4000,
        "balance": -450,
        "status": "refund"
      }
    ]
  }
  ```

#### `POST /api/months/{month}/close`
- **Headers**: `X-Idempotency-Key: <UUID>`
- **Response (200 OK)**: Snapshots calculations into `month_results` and `participant_results`. Sets `is_closed = true`, `closed_at = now()`.

#### `POST /api/months/{month}/reopen`
- **Headers**: `X-Idempotency-Key: <UUID>`
- **Rule**: Allowed only within 6 hours of closing (`now() <= closed_at + 6 hours`).
- **Response (200 OK)**: Deletes snapshot result, sets `is_closed = false`.

#### `PATCH /api/months/{month}/breakfast-price`
- **Body**: `{ "breakfast_price": 25 }`
- **Response (200 OK)**: Updates unit breakfast price for the open month.

---

### 6. Notifications

#### `GET /api/notifications`
- **Response (200 OK)**:
  ```json
  {
    "unread_count": 3,
    "data": [
      {
        "id": "uuid-...",
        "type": "OwnMealEditedNotification",
        "data": { "title": "Meal Updated", "message": "Alice updated lunch count to 2.", "severity": "info" },
        "read_at": null,
        "created_at": "2026-10-01T12:00:00.000000Z"
      }
    ]
  }
  ```

#### `PATCH /api/notifications/{id}/read` & `POST /api/notifications/read-all`
- Marks notifications as read.

#### `POST /api/notifications/broadcast`
- **Body**: `{ "title": "Mess Meeting", "message": "Monthly mess meeting at 10 PM in dining room.", "note": "Please bring your bazaar receipts" }`
- **Response (200 OK)**: Broadcasts to all active roommates.
