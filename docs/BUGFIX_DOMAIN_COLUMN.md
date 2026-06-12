# Bug Fix: Domain Column Error

## Issue
When clicking "View Details" on an application, the following error appeared:
```
Fatal error: Uncaught mysqli_sql_exception: Unknown column 'i.domain' in 'field list'
```

## Root Cause
The `view_application_status.php` file was trying to fetch a `domain` column from the `internships` table that doesn't exist in the database schema.

## Fix Applied

### File: `view_application_status.php`

**Before (Line 18-23):**
```php
$app_sql = "SELECT a.*, 
                   COALESCE(i.title, a.internship_name) as title,
                   COALESCE(i.duration, '') as duration,
                   COALESCE(i.mode, '') as mode,
                   COALESCE(i.domain, '') as domain  // ❌ This column doesn't exist
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            WHERE a.id = $app_id AND a.user_id = $user_id
            LIMIT 1";
```

**After (Fixed):**
```php
$app_sql = "SELECT a.*, 
                   COALESCE(i.title, a.internship_name) as title,
                   COALESCE(i.duration, '') as duration,
                   COALESCE(i.mode, '') as mode  // ✅ Removed domain column
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            WHERE a.id = $app_id AND a.user_id = $user_id
            LIMIT 1";
```

**Also removed the domain badge display (Line 195-200):**
```php
// ❌ REMOVED THIS:
<?php if (!empty($app['domain'])): ?>
<span class="px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-lg border border-emerald-100">
  <span class="material-symbols-outlined text-[14px] align-middle mr-1">category</span>
  <?php echo htmlspecialchars($app['domain']); ?>
</span>
<?php endif; ?>
```

## Testing

### Verify the fix:
1. Go to: `http://localhost/IMP/student_applications.php`
2. Click "View Details" on any application
3. The page should now load without errors
4. You should see:
   - Gradient header with current status
   - Vertical timeline with stages
   - Application details (without domain badge)
   - Complete history section

### Expected Result:
✅ Page loads successfully
✅ Timeline displays correctly
✅ No SQL errors
✅ All other badges (Duration, Mode) still show

## Prevention

To prevent similar issues in the future:

1. **Always check database schema** before querying columns
2. **Use `SHOW COLUMNS FROM table_name`** to verify column existence
3. **Test queries in phpMyAdmin** before adding to code
4. **Enable error reporting** during development:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

## Related Files
- ✅ `view_application_status.php` - Fixed
- ✅ `student_applications.php` - No changes needed (doesn't use domain)
- ✅ `hr_applications.php` - No changes needed (doesn't use domain)

## Status
🟢 **FIXED** - The "View Details" button now works correctly.

---

**Fixed on:** May 19, 2026
**Tested:** ✅ Working
**Syntax Check:** ✅ Passed
