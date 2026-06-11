# Appointment Booking System

A backend REST API system built with Laravel 12 that allows patients to book appointments with doctors. The system handles doctor availability scheduling, slot generation, appointment booking with concurrency protection, cancellations, rescheduling, and queue-based notifications.

---

## Table of Contents

- [Tech Stack](#tech-stack)
- [Setup Instructions](#setup-instructions)
- [Database Schema](#database-schema)
- [Design Decisions](#design-decisions)
- [API Reference](#api-reference)
- [Edge Cases Handled](#edge-cases-handled)
- [Performance Considerations](#performance-considerations)
- [Scaling the System](#scaling-the-system)
- [Optional Enhancements Implemented](#optional-enhancements-implemented)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.2+ |
| Database | MySQL 8.0 |
| Queue Driver | Database (switchable to Redis) |
| Mail | Log driver (simulated) |
| Code Style | Laravel Pint (PSR-12) |

---

## Setup Instructions

### 1. Clone the repository

```bash
git clone https://github.com/darshan-99/appointment-booking.git
cd appointment-booking
```

### 2. Install dependencies

```bash
composer install
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=appointment_booking
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Run migrations

```bash
php artisan migrate
```

### 5. Seed the database

```bash
php artisan db:seed
```

This creates:
- 200 doctors with randomised specializations
- 50 patients

### 6. Start queue worker

```bash
php artisan queue:work
```

> Emails are simulated via the `log` driver. Check `storage/logs/laravel.log` to see outgoing notification content.

### 7. Start the server

```bash
php artisan serve
```

Base URL: `http://localhost:8000/api`

---

## Database Schema

### `doctors`
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| name | string | Doctor's full name |
| email | string (unique) | Contact email |
| phone | string (nullable) | Contact number |
| specialization | string | Medical specialization |
| created_at / updated_at | timestamp | |

### `patients`
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| name | string | Patient's full name |
| email | string (unique) | Contact email |
| phone | string (nullable) | Contact number |
| created_at / updated_at | timestamp | |

### `doctor_availabilities`
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| doctor_id | foreignId | References doctors |
| date | date | Availability date |
| start_time | time | Window start |
| end_time | time | Window end |
| slot_duration | tinyint | Duration per slot in minutes |
| created_at / updated_at | timestamp | |

**Unique constraint:** `(doctor_id, date, start_time)` — allows multiple windows per day, prevents duplicate windows.

### `slots`
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| doctor_availability_id | foreignId | References doctor_availabilities |
| doctor_id | foreignId | References doctors |
| date | date | Slot date |
| start_time | time | Slot start |
| end_time | time | Slot end |
| status | enum | `available`, `booked` |
| created_at / updated_at | timestamp | |

**Index:** `(doctor_id, date, status)` — optimised for available slot lookups.

### `appointments`
| Column | Type | Description |
|---|---|---|
| id | bigint | Primary key |
| reference_number | string (unique) | e.g. `APT-XK92LMQZ` |
| patient_id | foreignId | References patients |
| doctor_id | foreignId | References doctors |
| slot_id | foreignId | References slots |
| status | enum | `booked`, `cancelled`, `rescheduled` |
| cancellation_reason | text (nullable) | Populated on cancellation |
| created_at / updated_at | timestamp | |

### `notifications` (Laravel native)
Laravel's built-in polymorphic notifications table. Stores all `database` channel notification records per patient.

---

## Design Decisions

### 1. Separate `doctors` and `patients` tables
Doctors and patients are distinct domain entities with different attributes and fundamentally different system relationships — doctors own availability schedules, patients own appointments. A shared `users` table with roles would add unnecessary complexity since authentication is out of scope for this system.

### 2. Pre-generated `slots` table
When a doctor defines an availability window, individual slots are pre-generated and persisted immediately. This enables **row-level locking** (`lockForUpdate`) during concurrent booking attempts, eliminating race conditions without application-level complexity. The alternative — calculating slots on the fly and checking appointments — would require complex unique constraint handling and is far more vulnerable to concurrency issues at scale.

### 3. Concurrency handled via database-level row locking
```
DB::transaction → Slot::lockForUpdate()->findOrFail($id)
```
When two simultaneous booking requests arrive for the same slot, the database lock ensures only one proceeds. The second request waits, then reads the updated `booked` status and returns a `409 Conflict` response. No application-level mutex or cache locking required.

### 4. Non-divisible availability windows are rejected
If a doctor sets a window of 14:00–17:00 (180 mins) with a slot duration of 120 mins, there would be 60 mins of dead time at the end that cannot form a slot but would block the overlap check for that period. To prevent this ambiguity, the system rejects availability requests where the window duration is not evenly divisible by the slot duration. This forces clean, predictable slot generation.

### 5. Multiple availability windows per day
A doctor can define multiple non-overlapping windows on the same day (e.g. 09:00–13:00 and 17:00–20:00). The overlap check validates against actual effective slot end times, not just the raw `end_time` field.

### 6. Queue-based notifications via Laravel's native Notification system
Notifications implement `ShouldQueue` and are dispatched asynchronously after booking, cancellation, and rescheduling. Using Laravel's built-in `Notifiable` trait and `notifications` table keeps the implementation idiomatic and avoids a custom notifications table. Both `database` and `mail` channels are used — mail is simulated via the `log` driver.

### 7. Service layer pattern
All business logic lives in `AvailabilityService` and `AppointmentService`. Controllers are thin — they validate input via Form Requests, delegate to services, and return JSON responses. This keeps code testable and maintainable.

### 8. PHP 8.2 Enums for status fields
`SlotStatus` and `AppointmentStatus` are backed PHP enums cast directly on their models. This eliminates magic strings throughout the codebase and provides type safety on status transitions.

---

## API Reference

All requests must include:
```
Accept: application/json
Content-Type: application/json
```

All responses are JSON and follow a consistent envelope:

```json
{
  "success": true,
  "message": "Human-readable status message.",
  "data": { },
  "errors": null
}
```

On failure the shape is identical with `success: false`, `data: null`, and `errors` populated for validation failures:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "data": null,
  "errors": {
    "slot_id": ["The selected slot id is invalid."]
  }
}
```

| Status | Meaning |
|---|---|
| `200` | OK |
| `201` | Resource created |
| `404` | Resource not found |
| `409` | Conflict (e.g. slot already booked) |
| `422` | Validation / business-rule error |
| `500` | Unexpected server error (details are logged, not exposed) |

---

### Doctor Availability

#### Create Availability Window

```
POST /api/doctors/{doctor_id}/availability
```

**Route Parameters:**
| Parameter | Type | Description |
|---|---|---|
| doctor_id | integer | ID of the doctor |

**Request Body:**
```json
{
  "date": "2026-07-01",
  "start_time": "09:00",
  "end_time": "13:00",
  "slot_duration": 30
}
```

| Field | Type | Rules |
|---|---|---|
| date | string (Y-m-d) | Required, today or future |
| start_time | string (H:i) | Required |
| end_time | string (H:i) | Required, must be after start_time |
| slot_duration | integer | Required, 5–120 mins, window must be evenly divisible |

**Success Response `201`:**
```json
{
  "success": true,
  "message": "Availability created successfully.",
  "data": {
    "availability": {
      "id": 1,
      "doctor_id": 1,
      "date": "2026-07-01",
      "start_time": "09:00:00",
      "end_time": "13:00:00",
      "slot_duration": 30,
      "slots": [
        { "id": 1, "start_time": "09:00:00", "end_time": "09:30:00", "status": "available" },
        { "id": 2, "start_time": "09:30:00", "end_time": "10:00:00", "status": "available" }
      ]
    }
  },
  "errors": null
}
```

**Error Responses:**
- `422` — Validation failed (past date, non-divisible window, overlapping window)

```json
{
  "success": false,
  "message": "This availability window overlaps with an existing schedule.",
  "data": null,
  "errors": null
}
```

---

#### Get Doctor Availabilities

```
GET /api/doctors/{doctor_id}/availability
```

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Doctor availabilities retrieved successfully.",
  "data": {
    "doctor": { "id": 1, "name": "Dr. John Smith", "specialization": "Cardiologist" },
    "availabilities": [
      {
        "id": 1,
        "date": "2026-07-01",
        "start_time": "09:00:00",
        "end_time": "13:00:00",
        "slot_duration": 30
      }
    ]
  },
  "errors": null
}
```

---

#### Get Available Slots

```
GET /api/doctors/{doctor_id}/slots?date=2026-07-01
```

**Query Parameters:**
| Parameter | Type | Rules |
|---|---|---|
| date | string (Y-m-d) | Required, today or future |

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Available slots retrieved successfully.",
  "data": {
    "doctor": { "id": 1, "name": "Dr. John Smith", "specialization": "Cardiologist" },
    "date": "2026-07-01",
    "slots": [
      { "id": 1, "start_time": "09:00:00", "end_time": "09:30:00", "status": "available" },
      { "id": 2, "start_time": "09:30:00", "end_time": "10:00:00", "status": "available" }
    ]
  },
  "errors": null
}
```

> When querying today's date, past time slots are automatically excluded from results.

---

### Appointments

#### Book Appointment

```
POST /api/appointments
```

**Request Body:**
```json
{
  "patient_id": 1,
  "slot_id": 3
}
```

| Field | Type | Rules |
|---|---|---|
| patient_id | integer | Required, must exist in patients table |
| slot_id | integer | Required, must exist in slots table |

**Success Response `201`:**
```json
{
  "success": true,
  "message": "Appointment booked successfully.",
  "data": {
    "appointment": {
      "id": 1,
      "reference_number": "APT-XK92LMQZ",
      "status": "booked",
      "patient": { "id": 1, "name": "Jane Doe" },
      "doctor": { "id": 1, "name": "Dr. John Smith" },
      "slot": {
        "id": 3,
        "date": "2026-07-01",
        "start_time": "10:00:00",
        "end_time": "10:30:00"
      }
    }
  },
  "errors": null
}
```

**Error Responses:**
- `409` — Slot already booked
- `422` — Slot is in the past, invalid patient or slot ID

```json
{
  "success": false,
  "message": "This slot has already been booked.",
  "data": null,
  "errors": null
}
```

---

#### Cancel Appointment

```
POST /api/appointments/{appointment_id}/cancel
```

**Route Parameters:**
| Parameter | Type | Description |
|---|---|---|
| appointment_id | integer | ID of the appointment |

**Request Body:**
```json
{
  "cancellation_reason": "Patient has a scheduling conflict."
}
```

| Field | Type | Rules |
|---|---|---|
| cancellation_reason | string | Required, max 500 chars |

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Appointment cancelled successfully.",
  "data": {
    "appointment": {
      "id": 1,
      "reference_number": "APT-XK92LMQZ",
      "status": "cancelled",
      "cancellation_reason": "Patient has a scheduling conflict.",
      "slot": { "id": 3, "status": "available" }
    }
  },
  "errors": null
}
```

**Error Responses:**
- `422` — Appointment is already cancelled

---

#### Reschedule Appointment

```
POST /api/appointments/{appointment_id}/reschedule
```

**Route Parameters:**
| Parameter | Type | Description |
|---|---|---|
| appointment_id | integer | ID of the appointment |

**Request Body:**
```json
{
  "new_slot_id": 7
}
```

| Field | Type | Rules |
|---|---|---|
| new_slot_id | integer | Required, must exist in slots table |

**Success Response `200`:**
```json
{
  "success": true,
  "message": "Appointment rescheduled successfully.",
  "data": {
    "appointment": {
      "id": 1,
      "reference_number": "APT-XK92LMQZ",
      "status": "rescheduled",
      "doctor": { "id": 1, "name": "Dr. John Smith" },
      "slot": {
        "id": 7,
        "date": "2026-07-05",
        "start_time": "11:00:00",
        "end_time": "11:30:00"
      }
    }
  },
  "errors": null
}
```

**Error Responses:**
- `409` — New slot is already booked
- `422` — Cannot reschedule a cancelled appointment, new slot is in the past

---

## Edge Cases Handled

| Scenario | Handling |
|---|---|
| Double booking same slot | Row-level `lockForUpdate` inside DB transaction — second request gets `409` |
| Booking a past slot | Checked against current date and time — returns `422` |
| Overlapping availability windows | Query checks existing windows for time overlap — returns `422` |
| Non-divisible availability window | Validated in Form Request before any DB writes — returns `422` |
| Cancelling an already cancelled appointment | Status check before processing — returns `422` |
| Rescheduling a cancelled appointment | Status check before processing — returns `422` |
| Rescheduling to an already booked slot | Row-level lock + status check — returns `409` |
| Invalid doctor / patient / slot ID | Laravel model binding + `exists` validation rules — returns `404` / `422` |
| Querying past dates for available slots | `after_or_equal:today` validation on date param — returns `422` |
| Today's slots already passed | Time filter applied only when querying today's date |

---

## Performance Considerations

### Indexes
The following indexes are in place for high-read queries:

- `slots (doctor_id, date, status)` — primary query for available slot lookup
- `appointments (patient_id, status)` — patient appointment history
- `appointments (reference_number)` — direct reference lookups

### Bulk slot insertion
Slots are inserted using `Slot::insert([...])` (a single bulk query) rather than individual `create()` calls per slot. For a doctor with an 8-hour window and 15-min slots, this means 1 query instead of 32.

### Async notifications
All notifications are queued via Laravel's queue system. Booking API response time is not affected by email dispatch. At 10,000 bookings/day, this prevents notification processing from becoming a bottleneck on the API layer.

### Queue driver
Currently using the `database` driver for simplicity. For production, switch to **Redis** for significantly better queue throughput.

---

## Scaling the System

### At 200 doctors / 10,000 bookings per day

**Database**
- Add read replicas — slot availability queries (heavy read) can route to replicas while writes go to primary
- Partition the `slots` table by `date` — queries are almost always date-scoped
- Archive old appointments and slots periodically to keep table sizes manageable

**Caching**
- Cache available slots per `(doctor_id, date)` using Redis with a short TTL (30–60 seconds)
- Invalidate cache on slot status change (booking, cancellation, reschedule)
- This dramatically reduces DB reads for the "view available slots" endpoint which is the highest-traffic route

**Queue**
- Switch `QUEUE_CONNECTION` from `database` to `redis`
- Run multiple queue workers across servers for parallel notification processing
- Use Laravel Horizon for queue monitoring and auto-scaling workers

**API Layer**
- Add rate limiting per patient/IP on the booking endpoint to prevent abuse
- Deploy behind a load balancer with multiple Laravel instances — the app is stateless so horizontal scaling is straightforward

**Future considerations**
- If booking volume grows further, consider an event-driven architecture using Laravel Events and Listeners to decouple booking logic from side effects (notifications, audit logs)
- Slot availability could move to an in-memory store (Redis) entirely for sub-millisecond reads, with MySQL as the source of truth

---

## Optional Enhancements Implemented

- ✅ **Slot validation** — Non-divisible windows rejected before any DB writes
- ✅ **Multiple availability windows per day** — Doctors can set morning and evening windows
- ✅ **Queue-based notifications** — Both `database` and `mail` channels via `ShouldQueue`
- ✅ **Enum-backed status fields** — PHP 8.1 enums on `SlotStatus` and `AppointmentStatus`
- ✅ **Service layer** — Business logic cleanly separated from controllers
- ✅ **Type hints** — Full PHP 8.2 return types and property types throughout
- ✅ **Bulk slot insertion** — Single query for slot generation regardless of window size