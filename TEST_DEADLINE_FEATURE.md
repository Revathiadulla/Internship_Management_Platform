# 48-Hour Test Deadline Feature

## Overview
Students must complete their assessment test within 48 hours of applying for an internship. The system displays a countdown timer and automatically disables the test after the deadline expires.

---

## ✨ Features Implemented

### 1. 48-Hour Deadline Rule
- **Starts:** When application is submitted (status = "Applied")
- **Duration:** 48 hours (2 days)
- **Calculation:** Applied date/time + 48 hours
- **Enforcement:** Automatic - no manual intervention needed

### 2. Countdown Timer
- **Display:** Hours and minutes remaining
- **Format:** "Time left: 47h 30m"
- **Color Coding:**
  - Green/Amber: More than 6 hours left
  - Red: Less than 6 hours left
- **Updates:** Real-time on page refresh

### 3. Test Status Badges
- **Pending:** Amber badge with clock icon
- **Completed:** Green badge with checkmark icon
- **Expired:** Red badge with cancel icon

### 4. Deadline Warning Box
- **Location:** Below internship title in card
- **Shows:**
  - Warning message
  - Countdown timer
  - Deadline date and time
- **Colors:**
  - Amber: Test pending, time remaining
  - Red: Test expired

---

## 🎯 Button Logic

### Start Test Button
```
IF status = "Applied" 
   AND test_status ≠ "Completed" 
   AND within 48 hours:
   
   SHOW: [ Start Test ] (Purple, enabled)
```

### Test Expired Button
```
IF status = "Applied" 
   AND test_status ≠ "Completed" 
   AND 48 hours passed:
   
   SHOW: [ Test Expired ] (Gray, disabled)
```

### View Result Button
```
IF test_status = "Completed":
   
   SHOW: [ View Result ] (Green, enabled)
```

---

## 📊 Visual Display

### Card Layout with Deadline Warning

```
┌──────────────────────────────────────────────────────────┐
│  [R]  React.js Developer                                 │
│  ⏱️  3 months  📍 Remote                                  │
│                                                          │
│  ┌────────────────────────────────────────────────┐     │
│  │ ⏰ Assessment Test Required                    │     │
│  │ Complete your test within 48 hours.            │     │
│  │ ⏱️ Time left: 47h 30m                          │     │
│  │ Deadline: May 21, 2026 at 10:30 AM            │     │
│  └────────────────────────────────────────────────┘     │
│                                                          │
│              Current Status                              │
│              [ 📤 Applied ]                              │
│              Applied on May 19, 2026                     │
│                                                          │
│              Test Status                                 │
│              [ ⏳ Pending ]                              │
│                                                          │
│  [ View Details ]  [ Start Test ]                        │
└──────────────────────────────────────────────────────────┘
```

### Expired State

```
┌──────────────────────────────────────────────────────────┐
│  [R]  React.js Developer                                 │
│  ⏱️  3 months  📍 Remote                                  │
│                                                          │
│  ┌────────────────────────────────────────────────┐     │
│  │ ❌ Test Deadline Expired                       │     │
│  │ The 48-hour test window has expired.           │     │
│  │ Please contact HR.                             │     │
│  └────────────────────────────────────────────────┘     │
│                                                          │
│              Current Status                              │
│              [ 📤 Applied ]                              │
│              Applied on May 19, 2026                     │
│                                                          │
│              Test Status                                 │
│              [ ❌ Expired ]                              │
│                                                          │
│  [ View Details ]  [ Test Expired ]                      │
└──────────────────────────────────────────────────────────┘
```

### Completed State

```
┌──────────────────────────────────────────────────────────┐
│  [R]  React.js Developer                                 │
│  ⏱️  3 months  📍 Remote                                  │
│                                                          │
│              Current Status                              │
│              [ 📤 Applied ]                              │
│              Applied on May 19, 2026                     │
│                                                          │
│              Test Status                                 │
│              [ ✓ Completed ]                             │
│                                                          │
│  [ View Details ]  [ View Result ]                       │
└──────────────────────────────────────────────────────────┘
```

---

## 🔢 Calculation Logic

### PHP Code
```php
// Calculate deadline (48 hours from application)
$applied_time = strtotime($app['applied_date']);
$deadline_time = $applied_time + (48 * 60 * 60); // 48 hours
$current_time = time();
$time_remaining = $deadline_time - $current_time;
$is_deadline_expired = ($time_remaining <= 0);

// Calculate hours and minutes
$hours_left = floor($time_remaining / 3600);
$minutes_left = floor(($time_remaining % 3600) / 60);
```

### Example Calculation
```
Applied: May 19, 2026 at 10:30 AM
Deadline: May 21, 2026 at 10:30 AM (48 hours later)
Current: May 20, 2026 at 11:00 AM

Time Remaining:
- Total seconds: 84,600
- Hours: 23h
- Minutes: 30m

Display: "Time left: 23h 30m"
```

---

## 🎨 Color Coding

### Warning Box Colors

**Pending (Time Remaining)**
```css
Background: bg-amber-50
Border: border-amber-200
Text: text-amber-600
Icon: text-amber-600
```

**Expired**
```css
Background: bg-red-50
Border: border-red-200
Text: text-red-600
Icon: text-red-600
```

**Urgent (< 6 hours)**
```css
Timer Text: text-red-600 (instead of amber)
```

### Status Badge Colors

**Pending**
```css
Background: bg-amber-50
Text: text-amber-700
Border: border-amber-200
Icon: pending
```

**Completed**
```css
Background: bg-emerald-50
Text: text-emerald-700
Border: border-emerald-200
Icon: check_circle
```

**Expired**
```css
Background: bg-red-50
Text: text-red-700
Border: border-red-200
Icon: cancel
```

---

## 📱 Responsive Design

### Desktop
- Warning box: Full width below title
- Timer: Inline with message
- Deadline: Small text below timer

### Mobile
- Warning box: Stacks vertically
- Timer: Prominent display
- Deadline: Wraps to new line

---

## 🔄 Workflow Integration

### Application Submission
```
1. Student submits application
2. Status set to: "Applied"
3. Test status set to: "Pending"
4. Applied date recorded
5. 48-hour countdown starts
```

### Test Completion
```
1. Student completes test
2. Test status set to: "Completed"
3. Test submitted date recorded
4. Application status updated to: "Test Completed"
5. Deadline no longer relevant
```

### Deadline Expiration
```
1. 48 hours pass without test completion
2. System automatically detects expiration
3. "Start Test" button disabled
4. "Test Expired" button shown
5. Warning message displayed
```

---

## 📄 View Details Page

### Test Information Display

When user clicks "View Details", show:

```
┌────────────────────────────────────────────────┐
│ Application Timeline                           │
├────────────────────────────────────────────────┤
│                                                │
│ ● Applied                                      │
│   May 19, 2026 at 10:30 AM                    │
│                                                │
│ Test Deadline Information:                     │
│ • Deadline: May 21, 2026 at 10:30 AM          │
│ • Status: Pending                              │
│ • Time Remaining: 47h 30m                      │
│                                                │
│ ○ Test Completed (Pending)                     │
│                                                │
│ ○ HR Round (Pending)                           │
│                                                │
└────────────────────────────────────────────────┘
```

### After Test Completion

```
┌────────────────────────────────────────────────┐
│ Application Timeline                           │
├────────────────────────────────────────────────┤
│                                                │
│ ✓ Applied                                      │
│   May 19, 2026 at 10:30 AM                    │
│                                                │
│ ✓ Test Completed                               │
│   May 20, 2026 at 3:45 PM                     │
│   Submitted within deadline ✓                  │
│                                                │
│ ○ HR Round (Pending)                           │
│                                                │
└────────────────────────────────────────────────┘
```

---

## 🗄️ Database Schema

### New Column Added

```sql
ALTER TABLE internship_applications 
ADD COLUMN test_submitted_date TIMESTAMP NULL DEFAULT NULL 
AFTER test_status;
```

### Relevant Columns
```sql
applied_date          TIMESTAMP    -- When application was submitted
test_status           VARCHAR(50)  -- 'Pending' or 'Completed'
test_submitted_date   TIMESTAMP    -- When test was completed
test_score            INT          -- Test score (0-100)
test_answers          TEXT         -- JSON of answers
```

---

## 🧪 Testing Scenarios

### Scenario 1: Fresh Application
```
Applied: Now
Deadline: Now + 48 hours
Expected: Show countdown, enable "Start Test"
```

### Scenario 2: 6 Hours Left
```
Applied: 42 hours ago
Deadline: 6 hours from now
Expected: Show red countdown, enable "Start Test"
```

### Scenario 3: Deadline Passed
```
Applied: 50 hours ago
Deadline: 2 hours ago
Expected: Show "Expired", disable "Start Test"
```

### Scenario 4: Test Completed
```
Applied: 10 hours ago
Test Completed: 2 hours ago
Expected: Hide countdown, show "View Result"
```

---

## ⚠️ Edge Cases

### 1. Exactly at Deadline
```
Time remaining: 0 seconds
Behavior: Treated as expired
Button: Disabled
```

### 2. Test Completed After Expiry
```
Scenario: Student somehow completes test after deadline
Behavior: Accept submission, update status
Note: Should be prevented by UI, but backend accepts
```

### 3. Multiple Applications
```
Scenario: Student applies to multiple internships
Behavior: Each has independent 48-hour countdown
Display: Separate timer for each application
```

### 4. Timezone Considerations
```
Applied date: Stored in server timezone
Display: Shown in server timezone
Calculation: Uses server time
Note: Consistent across all users
```

---

## 📊 User Experience Flow

### Happy Path
```
1. Student applies → See "Start Test" button
2. See countdown timer → Aware of deadline
3. Click "Start Test" → Complete assessment
4. Submit test → Status changes to "Test Completed"
5. See "View Result" → Check score
```

### Missed Deadline Path
```
1. Student applies → See "Start Test" button
2. Ignore for 48+ hours → Timer expires
3. Return to dashboard → See "Test Expired"
4. Button disabled → Contact HR for extension
```

---

## 🎯 Benefits

### For Students
- ✅ Clear deadline visibility
- ✅ Real-time countdown
- ✅ No confusion about test availability
- ✅ Urgency indicator (color changes)

### For HR
- ✅ Automatic enforcement
- ✅ No manual deadline tracking
- ✅ Clear expired status
- ✅ Consistent policy application

### For System
- ✅ Automated workflow
- ✅ No cron jobs needed
- ✅ Real-time calculation
- ✅ Database-driven logic

---

## 📝 Summary

The 48-hour test deadline feature provides:

1. **Automatic Deadline Enforcement** - No manual tracking needed
2. **Visual Countdown Timer** - Students always know time remaining
3. **Color-Coded Warnings** - Urgency increases as deadline approaches
4. **Disabled State** - Test cannot be started after expiration
5. **Clean UI Integration** - Fits seamlessly into existing design
6. **Mobile Responsive** - Works on all devices
7. **Database Tracked** - All dates recorded for audit

**Result:** A professional, automated test deadline system that ensures timely assessment completion while providing clear feedback to students.

---

**Implemented:** May 19, 2026
**Version:** 2.3
**Status:** Production Ready ✅
