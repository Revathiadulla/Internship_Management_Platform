# Application Status Tracking System - Implementation Summary

## Overview
A comprehensive application workflow system with dynamic status handling, timeline UI, status history tracking, and role-based updates for the Internship Management Platform (IMP).

## Features Implemented

### 1. Database Schema
**File:** `db.php`
- Created `application_status_history` table with the following columns:
  - `id` (Primary Key)
  - `application_id` (Foreign Key to internship_applications)
  - `old_status` (Previous status)
  - `new_status` (Updated status)
  - `updated_by_role` (HR, Coordinator, Admin)
  - `updated_by_name` (Name of the person who updated)
  - `notes` (Optional notes about the status change)
  - `created_at` (Timestamp)

### 2. Status Update Handler
**File:** `update_application_status.php`
- Role-based access control (HR, Coordinator, Admin only)
- Validates status transitions
- Implements conditional workflow logic:
  - **Pursuing students:** Applied → Test Completed → HR Round → HR Approved → HOD Approval Pending → HOD Approved → Selected
  - **Passed Out students:** Applied → Test Completed → HR Round → HR Approved → Selected (skips HOD steps)
- Automatically logs all status changes to history table
- Returns JSON response for AJAX calls

### 3. Status Utilities
**File:** `status_utils.php`
- `getStatusBadgeClass($status)` - Returns Tailwind CSS classes for status badges
- `getStatusIcon($status)` - Returns Material Symbols icon name for each status
- `getWorkflowSteps($education_status)` - Returns workflow steps based on education status
- `getCurrentStepIndex($current_status, $workflow_steps)` - Finds current position in workflow
- `formatTimestamp($timestamp)` - Formats timestamps as relative time (e.g., "2 hours ago")

### 4. Status Timeline Component
**File:** `application_status_timeline.php`
- Reusable PHP function: `renderStatusTimeline($application_id, $conn)`
- Visual workflow progress bar with color-coded nodes
- Shows completed, active, and pending steps
- Displays full status history with timestamps
- Responsive design with smooth animations
- Material Design icons and color scheme

### 5. Student Status View Page
**File:** `view_application_status.php`
- Dedicated page for viewing detailed application status
- Shows internship details card
- Renders full status timeline
- Displays application details (education status, reason, skills)
- Accessible from "View Status" button in My Applications

### 6. HR Applications Management
**File:** `hr_applications.php`
- Comprehensive table view of all applications
- Inline status update dropdowns
- Filters HOD statuses for Passed Out students automatically
- Real-time AJAX status updates
- Toast notifications for success/error feedback
- View timeline and details buttons for each application

### 7. Updated Student Applications Page
**File:** `student_applications.php` (modified)
- Added "View Status" button to each application row
- Links to dedicated status view page
- Maintains existing functionality (View Details, Start Test, etc.)

## Status Workflow

### Base Statuses
1. **Applied** - Initial status when student submits application
2. **Test Completed** - After student completes assessment
3. **HR Round** - During HR review/interview
4. **HR Approved** - HR has approved the candidate
5. **HOD Approval Pending** - Waiting for HOD approval (Pursuing only)
6. **HOD Approved** - HOD has approved (Pursuing only)
7. **Selected** - Final selection
8. **Rejected** - Application rejected

### Optional Additional Statuses
- **Under Review** - Application is being reviewed
- **Interview Scheduled** - Interview has been scheduled
- **Offer Sent** - Offer letter sent to candidate
- **Onboarding Completed** - Candidate has completed onboarding

## Conditional Logic

### Pursuing Students
- **Must go through HOD approval** after HR approval
- Workflow: Applied → Test → HR Round → HR Approved → **HOD Approval Pending** → **HOD Approved** → Selected

### Passed Out Students
- **Skip HOD approval entirely**
- Workflow: Applied → Test → HR Round → HR Approved → Selected

## UI/UX Features

### Status Timeline
- **Progress Bar:** Visual representation of workflow completion
- **Color-Coded Nodes:**
  - Green (Completed): ✓ check icon
  - Blue (Active): → arrow icon, pulsing animation
  - Gray (Pending): status icon, reduced opacity
- **Status History:** Chronological list of all status changes with:
  - Old status → New status transition
  - Timestamp (relative time)
  - Updated by (role/name)
  - Optional notes

### Status Badges
- **Green:** Selected, HOD Approved, Onboarding Completed, Offer Sent
- **Blue:** Applied, Test Completed, HR Round, Under Review
- **Amber:** HR Approved, HOD Approval Pending, Interview Scheduled
- **Red:** Rejected
- **Gray:** Default/unknown statuses

### HR Dashboard
- **Inline Status Updates:** Dropdown in each row
- **Conditional Options:** HOD statuses hidden for Passed Out students
- **AJAX Updates:** No page reload required
- **Toast Notifications:** Success/error feedback
- **Action Buttons:** View Timeline, View Details

## Security & Permissions

### Role-Based Access Control
- **Students:** Can only view their own application status (read-only)
- **HR:** Can update statuses for all applications
- **Coordinator:** Can update statuses and approve HOD steps
- **Admin:** Full access to all status updates

### Validation
- Validates application ownership for students
- Checks role permissions before allowing updates
- Prevents invalid status transitions
- Enforces conditional workflow rules (Pursuing vs Passed Out)

## Files Created/Modified

### New Files
1. `update_application_status.php` - Status update API handler
2. `status_utils.php` - Helper functions for status management
3. `application_status_timeline.php` - Reusable timeline component
4. `view_application_status.php` - Student status view page
5. `hr_applications.php` - HR applications management dashboard
6. `STATUS_TRACKING_IMPLEMENTATION.md` - This documentation

### Modified Files
1. `db.php` - Added status history table migration
2. `student_applications.php` - Added "View Status" button

## Testing Checklist

### Database
- [ ] Run the application to trigger `db.php` and create `application_status_history` table
- [ ] Verify table structure with `DESCRIBE application_status_history;`

### Student View
- [ ] Login as student
- [ ] Navigate to My Applications
- [ ] Click "View Status" button
- [ ] Verify timeline displays correctly
- [ ] Check workflow progress bar
- [ ] Verify status history (if any updates exist)

### HR View
- [ ] Access `hr_applications.php`
- [ ] Verify all applications are listed
- [ ] Test status update dropdown
- [ ] Confirm HOD options hidden for Passed Out students
- [ ] Update a status and verify:
  - Toast notification appears
  - Page reloads with new status
  - Status badge color updates
  - History entry created

### Workflow Logic
- [ ] Test Pursuing student workflow (includes HOD steps)
- [ ] Test Passed Out student workflow (skips HOD steps)
- [ ] Verify auto-transition: HR Approved → HOD Approval Pending (Pursuing only)
- [ ] Test rejection at various stages

### Status History
- [ ] Update status multiple times
- [ ] View timeline on student page
- [ ] Verify all transitions are logged
- [ ] Check timestamps are formatted correctly
- [ ] Confirm role/name is recorded

## Future Enhancements

### Notifications
- Send email/SMS when status changes
- In-app notifications for students
- HOD notification when approval is pending

### Analytics
- Status conversion funnel
- Average time in each status
- Rejection reasons tracking
- Department-wise approval rates

### Advanced Features
- Bulk status updates
- Status change comments/feedback
- Document attachments per status
- Interview scheduling integration
- Automated status transitions based on test scores

## Technical Notes

### AJAX Implementation
```javascript
// Status update example
const formData = new FormData();
formData.append('application_id', appId);
formData.append('new_status', newStatus);
formData.append('notes', 'Optional notes');

const response = await fetch('update_application_status.php', {
  method: 'POST',
  body: formData
});

const result = await response.json();
// result.success, result.message, result.new_status, result.old_status
```

### Including Timeline Component
```php
<?php
include "application_status_timeline.php";
// Later in your HTML:
renderStatusTimeline($application_id, $conn);
?>
```

### Status Badge Usage
```php
<?php
include "status_utils.php";
$badge_class = getStatusBadgeClass($status);
$icon = getStatusIcon($status);
?>
<span class="<?php echo $badge_class; ?>">
  <span class="material-symbols-outlined"><?php echo $icon; ?></span>
  <?php echo $status; ?>
</span>
```

## Support & Maintenance

### Common Issues
1. **Status not updating:** Check role permissions in session
2. **Timeline not showing:** Verify application_id is valid
3. **HOD steps showing for Passed Out:** Check education_status field
4. **History not logging:** Verify foreign key constraint

### Database Queries
```sql
-- View all status changes for an application
SELECT * FROM application_status_history 
WHERE application_id = ? 
ORDER BY created_at DESC;

-- Count applications by status
SELECT status, COUNT(*) as count 
FROM internship_applications 
GROUP BY status;

-- Find applications stuck in a status for > 7 days
SELECT * FROM internship_applications 
WHERE status = 'HR Round' 
AND applied_date < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

---

**Implementation Date:** May 19, 2026  
**Version:** 1.0  
**Platform:** IMP (Internship Management Platform)  
**Tech Stack:** PHP, MySQL, Tailwind CSS, JavaScript (AJAX)
