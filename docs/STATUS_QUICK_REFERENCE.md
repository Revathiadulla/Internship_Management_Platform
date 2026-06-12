# Status System Quick Reference

## 6 Core Statuses

| Status | Color | Icon | Meaning | Next Actions |
|--------|-------|------|---------|--------------|
| **Applied** | Slate | `send` | Application submitted | HR reviews application |
| **Test Completed** | Purple | `quiz` | Assessment done | HR evaluates test results |
| **HR Round** | Orange | `manage_search` | Under HR review | HR approves or rejects |
| **HOD Approved** | Cyan | `verified` | Department approved (Pursuing only) | Student can start |
| **Selected** | Green | `check_circle` | Final approval | Student can start internship |
| **Rejected** | Red | `cancel` | Not successful | Terminal status |

---

## Workflow by Education Status

### Pursuing Students (5 stages)
```
1. Applied
2. Test Completed
3. HR Round
4. HOD Approved
5. Selected
```

### Passed Out Students (4 stages)
```
1. Applied
2. Test Completed
3. HR Round
4. Selected (skips HOD)
```

---

## Status Progression Rules

### Valid Transitions
```
Applied → Test Completed → HR Round → HOD Approved → Selected
                                   ↘ Rejected (any stage)
```

### Invalid Transitions (Prevented)
- ❌ Applied → Selected (cannot skip stages)
- ❌ Applied → HOD Approved (must go through HR Round)
- ❌ Test Completed → Selected (must go through HR Round)
- ❌ Rejected → Any status (terminal)

---

## Color Codes (Tailwind CSS)

### Badge Classes
```css
Applied:        bg-slate-100 text-slate-700 border-slate-200
Test Completed: bg-purple-100 text-purple-700 border-purple-200
HR Round:       bg-orange-100 text-orange-700 border-orange-200
HOD Approved:   bg-cyan-100 text-cyan-700 border-cyan-200
Selected:       bg-emerald-100 text-emerald-700 border-emerald-200
Rejected:       bg-red-100 text-red-700 border-red-200
```

### Circle Classes (Timeline)
```css
Applied:        bg-slate-500 text-white
Test Completed: bg-purple-500 text-white
HR Round:       bg-orange-500 text-white
HOD Approved:   bg-cyan-500 text-white
Selected:       bg-emerald-500 text-white
Rejected:       bg-red-500 text-white
```

---

## Material Icons

```
Applied:        send
Test Completed: quiz
HR Round:       manage_search
HOD Approved:   verified
Selected:       check_circle
Rejected:       cancel
```

---

## Timeline Indicators

| Symbol | Meaning | Visual |
|--------|---------|--------|
| ✓ | Completed | Green checkmark in colored circle |
| ● | Current | Pulsing colored circle with ring |
| ○ | Pending | Gray circle, faded |

---

## Database Fields

### `internship_applications` table
```sql
status VARCHAR(50)              -- Current status
education_status VARCHAR(20)    -- 'Pursuing' or 'Passed Out'
applied_date TIMESTAMP          -- Application submission date
```

### `application_status_history` table
```sql
application_id INT              -- FK to internship_applications
old_status VARCHAR(50)          -- Previous status (NULL for initial)
new_status VARCHAR(50)          -- New status
updated_by_role VARCHAR(50)     -- 'HR', 'HOD', 'Admin', etc.
updated_by_name VARCHAR(100)    -- Name of updater
notes TEXT                      -- Optional notes
created_at TIMESTAMP            -- When status changed
```

---

## PHP Functions

### Status Utilities (`status_utils.php`)
```php
getStatusBadgeClass($status)           // Returns Tailwind classes
getStatusIcon($status)                 // Returns Material icon name
getWorkflowSteps($education_status)    // Returns workflow array
getCurrentStepIndex($status, $steps)   // Returns current position
formatTimestamp($timestamp)            // Returns relative time
```

### Timeline Rendering (`application_status_timeline.php`)
```php
renderStatusTimeline($app_id, $conn)   // Renders complete timeline UI
```

---

## HR Dashboard Actions

### Update Status
```php
POST /update_application_status.php
{
    "application_id": 123,
    "new_status": "HR Round",
    "notes": "Strong candidate, moving forward"
}
```

### Valid Status Updates by Role
- **HR:** Can update to any status except HOD Approved
- **HOD:** Can update to HOD Approved or Rejected
- **Admin:** Can update to any status

---

## Student Actions by Status

| Status | Student Can |
|--------|-------------|
| Applied | View status, wait for test |
| Test Completed | View results, wait for HR |
| HR Round | View status, wait for decision |
| HOD Approved | **Start internship** |
| Selected | **Start internship** |
| Rejected | View reason, apply to other internships |

---

## Notification Triggers

Status changes trigger notifications:
```
Applied → Test Completed:
  "Your test has been recorded. HR will review your application."

Test Completed → HR Round:
  "Your application is now under HR review."

HR Round → HOD Approved:
  "Your application has been forwarded to HOD for approval."

HR Round → Selected:
  "Congratulations! You have been selected. You can now start your internship."

Any → Rejected:
  "Unfortunately, your application was not successful at this time."
```

---

## Common Queries

### Get all applications in HR Round
```sql
SELECT * FROM internship_applications 
WHERE status = 'HR Round' 
ORDER BY applied_date ASC;
```

### Get applications awaiting HOD approval
```sql
SELECT * FROM internship_applications 
WHERE status = 'HR Round' 
  AND education_status = 'Pursuing'
ORDER BY applied_date ASC;
```

### Get selected applications ready to start
```sql
SELECT * FROM internship_applications 
WHERE status IN ('Selected', 'HOD Approved')
ORDER BY applied_date DESC;
```

### Get status history for an application
```sql
SELECT * FROM application_status_history 
WHERE application_id = 123 
ORDER BY created_at DESC;
```

---

## Troubleshooting

### Issue: Status not updating
**Check:**
1. User role has permission
2. Status transition is valid
3. Database connection is active
4. `application_status_history` table exists

### Issue: Timeline not showing
**Check:**
1. `application_status_timeline.php` is included
2. `status_utils.php` is included
3. Application ID is valid
4. Database query returns results

### Issue: Wrong workflow showing
**Check:**
1. `education_status` field is set correctly
2. Workflow logic in `getWorkflowSteps()` is correct
3. Status is in the workflow array

---

## Testing Commands

### Check syntax
```bash
C:\xampp\php\php.exe -l student_applications.php
C:\xampp\php\php.exe -l application_status_timeline.php
C:\xampp\php\php.exe -l status_utils.php
```

### Test status update
```sql
-- Update status manually
UPDATE internship_applications 
SET status = 'HR Round' 
WHERE id = 123;

-- Insert history record
INSERT INTO application_status_history 
(application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
VALUES (123, 'Test Completed', 'HR Round', 'HR', 'Test User', 'Manual test update');
```

---

## Quick Tips

1. **Always use the 6 core statuses** - Don't create custom statuses
2. **Check education_status** - Determines if HOD stage is needed
3. **Add notes** - Help students understand status changes
4. **Use timeline component** - Don't build custom status displays
5. **Validate transitions** - Prevent invalid status jumps
6. **Test mobile view** - Ensure responsive design works
7. **Check colors** - Use the defined color palette

---

**Last Updated:** May 19, 2026
**Version:** 2.0
