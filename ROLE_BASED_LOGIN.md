# Role-Based Login System - Implementation Guide

## Overview
The IMP (Internship Management Platform) implements a comprehensive role-based authentication and authorization system that redirects users to their appropriate dashboards based on their assigned role.

## Supported Roles

### 1. **Student**
- **Role Value:** `student` (case-insensitive)
- **Login Flow:**
  1. User logs in with email/password
  2. System checks if student profile exists
  3. **If profile exists:** Redirect to `student_dashboard.php`
  4. **If no profile:** Redirect to `student_profile_form.php` (onboarding)
- **Dashboard Features:**
  - Browse internships
  - Apply for internships
  - Track application status
  - View status timeline
  - Daily logs (if active intern)
  - Profile management

### 2. **HR (Human Resources)**
- **Role Value:** `hr` (case-insensitive)
- **Redirect:** `hr_applications.php`
- **Dashboard Features:**
  - View all applications
  - Update application status
  - Filter candidates
  - Approve/reject applications
  - Status tracking
  - Export reports

### 3. **Coordinator**
- **Role Value:** `coordinator` (case-insensitive)
- **Redirect:** `coordinator_dashboard.php`
- **Dashboard Features:**
  - Manage internships
  - Track all candidates
  - Monitor daily logs
  - Project management
  - Team management
  - Generate reports

### 4. **Mentor**
- **Role Value:** `mentor` (case-insensitive)
- **Redirect:** `mentor_dashboard.php`
- **Dashboard Features:**
  - View assigned interns
  - Provide feedback
  - Track progress
  - Review daily logs
  - Approve tasks

### 5. **Company**
- **Role Value:** `company` (case-insensitive)
- **Redirect:** `company_dashboard.php`
- **Dashboard Features:**
  - Post internship opportunities
  - Browse talent pool
  - Manage hiring pipeline
  - View applications
  - Company profile management

### 6. **Admin**
- **Role Value:** `admin` (case-insensitive)
- **Redirect:** `admin_dashboard.php`
- **Dashboard Features:**
  - Full system access
  - User management
  - Role assignment
  - System configuration
  - Analytics and reports

## Login Flow Diagram

```
User Login (email + password)
        ↓
Verify Credentials
        ↓
    Valid? ──No──→ Error: "Invalid email or password"
        ↓ Yes
Set Session Variables:
  - user_id
  - full_name
  - email
  - role
        ↓
Check Role (case-insensitive)
        ↓
┌───────┴───────┬──────────┬────────────┬─────────┬─────────┬───────┐
│               │          │            │         │         │       │
Student      HR    Coordinator   Mentor   Company   Admin   Other
│               │          │            │         │         │       │
Check Profile   │          │            │         │         │       │
│               │          │            │         │         │       │
Profile?        │          │            │         │         │       │
│               │          │            │         │         │       │
Yes    No       │          │            │         │         │       │
│      │        │          │            │         │         │       │
│      │        │          │            │         │         │       │
Dashboard  Onboarding     Dashboards...                    Default
```

## Implementation Code

### Login Handler (login.php)

```php
if (password_verify($password, $user['password'])) {
    // Set session variables
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];

    // Role-based redirection (case-insensitive)
    $role = strtolower($user['role']);
    
    if ($role == "student") {
        // Check if student has completed profile
        $check_sql = "SELECT id FROM student_profiles WHERE user_id = '{$user['id']}'";
        $check_result = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_result) > 0) {
            header("Location: student_dashboard.php");
        } else {
            header("Location: student_profile_form.php");
        }
    } elseif ($role == "hr") {
        header("Location: hr_applications.php");
    } elseif ($role == "coordinator") {
        header("Location: coordinator_dashboard.php");
    } elseif ($role == "mentor") {
        header("Location: mentor_dashboard.php");
    } elseif ($role == "company") {
        header("Location: company_dashboard.php");
    } elseif ($role == "admin") {
        header("Location: admin_dashboard.php");
    } else {
        // Default fallback for unknown roles
        header("Location: student_dashboard.php");
    }
    exit();
}
```

## Session Variables

After successful login, the following session variables are set:

| Variable | Description | Example |
|----------|-------------|---------|
| `$_SESSION['user_id']` | Unique user ID | `123` |
| `$_SESSION['full_name']` | User's full name | `"John Doe"` |
| `$_SESSION['email']` | User's email | `"john@example.com"` |
| `$_SESSION['role']` | User's role | `"student"` |

## Protected Pages

All dashboard pages should include session checks:

```php
<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Optional: Check specific role
if ($_SESSION['role'] !== 'student') {
    header("Location: login.php?error=Unauthorized");
    exit();
}
?>
```

## Database Schema

### Users Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'hr', 'coordinator', 'mentor', 'company', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Student Profiles Table
```sql
CREATE TABLE student_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(15),
    college_name VARCHAR(150),
    course VARCHAR(100),
    skills TEXT,
    resume_file VARCHAR(255),
    -- ... other fields
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Security Features

### 1. **Password Hashing**
- Uses `password_hash()` with bcrypt algorithm
- Verified with `password_verify()`
- Never stores plain text passwords

### 2. **Session Management**
- Session variables set after authentication
- Session checked on every protected page
- Logout clears all session data

### 3. **SQL Injection Prevention**
- Should use prepared statements (recommended upgrade)
- Input validation on all forms
- Escaping user input

### 4. **Role Validation**
- Case-insensitive role checking
- Default fallback for unknown roles
- Role stored in session for quick access

## Error Handling

### Login Errors
- **Invalid Credentials:** `login.php?error=Invalid+email+or+password`
- **Account Not Found:** Same message (security best practice)
- **Unauthorized Access:** `login.php?error=Unauthorized`

### Success Messages
- **Registration Success:** `login.php?success=Account+created+successfully`
- **Password Reset:** `login.php?success=Password+updated`

## Testing Checklist

### Student Login
- [ ] Login with student credentials
- [ ] Verify redirect to dashboard (if profile exists)
- [ ] Verify redirect to profile form (if no profile)
- [ ] Check session variables are set
- [ ] Test logout functionality

### HR Login
- [ ] Login with HR credentials
- [ ] Verify redirect to `hr_applications.php`
- [ ] Check access to application management
- [ ] Test status update functionality

### Coordinator Login
- [ ] Login with coordinator credentials
- [ ] Verify redirect to coordinator dashboard
- [ ] Check access to all management features

### Mentor Login
- [ ] Login with mentor credentials
- [ ] Verify redirect to mentor dashboard
- [ ] Check access to assigned interns

### Company Login
- [ ] Login with company credentials
- [ ] Verify redirect to company dashboard
- [ ] Check posting and browsing features

### Admin Login
- [ ] Login with admin credentials
- [ ] Verify redirect to admin dashboard
- [ ] Check full system access

### Error Cases
- [ ] Test with invalid email
- [ ] Test with wrong password
- [ ] Test with non-existent account
- [ ] Test session expiration
- [ ] Test unauthorized page access

## Dashboard URLs

| Role | Dashboard URL | Description |
|------|---------------|-------------|
| Student | `student_dashboard.php` | Student workspace |
| Student (New) | `student_profile_form.php` | Profile onboarding |
| HR | `hr_applications.php` | Application management |
| Coordinator | `coordinator_dashboard.php` | Coordination console |
| Mentor | `mentor_dashboard.php` | Mentor workspace |
| Company | `company_dashboard.php` | Company portal |
| Admin | `admin_dashboard.php` | Admin panel |

## Adding New Roles

To add a new role:

1. **Update Database:**
```sql
ALTER TABLE users MODIFY role ENUM('student', 'hr', 'coordinator', 'mentor', 'company', 'admin', 'new_role');
```

2. **Update Login Handler:**
```php
elseif ($role == "new_role") {
    header("Location: new_role_dashboard.php");
}
```

3. **Create Dashboard:**
- Create `new_role_dashboard.php`
- Add session checks
- Implement role-specific features

4. **Update Documentation:**
- Add role to this document
- Update testing checklist
- Document dashboard features

## Best Practices

### 1. **Always Check Sessions**
```php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
```

### 2. **Use Role Constants**
```php
define('ROLE_STUDENT', 'student');
define('ROLE_HR', 'hr');
define('ROLE_COORDINATOR', 'coordinator');
// ... etc
```

### 3. **Centralized Auth Check**
Create `auth_check.php`:
```php
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
```

Include in protected pages:
```php
<?php include "auth_check.php"; ?>
```

### 4. **Logout Functionality**
Create `logout.php`:
```php
<?php
session_start();
session_unset();
session_destroy();
header("Location: login.php?success=Logged+out+successfully");
exit();
?>
```

## Troubleshooting

### Issue: Redirect Loop
**Cause:** Session not being set properly  
**Solution:** Check `session_start()` is called before any output

### Issue: Role Not Recognized
**Cause:** Case sensitivity or typo  
**Solution:** Use `strtolower()` for comparison

### Issue: Student Stuck in Onboarding
**Cause:** Profile not created in database  
**Solution:** Check `student_profiles` table for user_id

### Issue: Unauthorized Access
**Cause:** Session expired or not set  
**Solution:** Add session checks to all protected pages

## Future Enhancements

1. **Remember Me Functionality**
   - Store encrypted token in cookie
   - Auto-login on return visit

2. **Two-Factor Authentication**
   - SMS or email verification
   - TOTP authenticator app support

3. **Role Permissions Matrix**
   - Granular permissions per role
   - Feature-level access control

4. **Activity Logging**
   - Track login attempts
   - Log role changes
   - Audit trail for security

5. **Password Reset Flow**
   - Email verification
   - Secure token generation
   - Time-limited reset links

---

**Last Updated:** May 19, 2026  
**Version:** 1.0  
**Platform:** IMP (Internship Management Platform)
