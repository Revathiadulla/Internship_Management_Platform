# Fix for "Field 'id' doesn't have a default value" Error

## Problem
When saving/generating questions, the application throws the error:
```
Field 'id' doesn't have a default value
```

This occurs because INSERT queries don't manually insert `id` values (which is correct), but the database tables don't have `AUTO_INCREMENT` defined on their `id` columns.

## Root Cause
The tables may have been created with an `id INT PRIMARY KEY` column without the `AUTO_INCREMENT` keyword, causing MySQL to require an explicit value when inserting.

## Solution Implemented

### 1. Updated Database Schema (ensure_extended_schema.php)
Added automatic checks and fixes for the `id` column AUTO_INCREMENT status in these tables:
- `test_questions`
- `subtype_tests`
- `subtype_test_questions`
- `question_bank`

The fix automatically:
- Checks if the `id` column exists
- Detects if AUTO_INCREMENT is missing
- Drops and recreates the PRIMARY KEY if needed
- Applies AUTO_INCREMENT modification

### 2. Fixed INSERT Queries
Verified that INSERT queries in the following files do NOT manually insert `id`:
- `coordinator_generate_test.php` - ✓ Correct (no id in INSERT)
- `coordinator_internships.php` - ✓ Correct (no id in INSERT)
- `ensure_extended_schema.php` - ✓ Correct (no id in INSERT)

### 3. Fixed Old Utility Scripts
Updated deprecated scripts in the `scripts/` folder:
- `seed_subtype_test.php` - Fixed column mismatch
- `add_question_bank_and_questions.php` - Removed non-existent `question_bank_id` column
- `add_questions.php` - Removed non-existent `question_bank_id` column

## How to Apply the Fix

### Option 1: Automatic Fix (Recommended)
The fix will automatically apply when you:
1. Load any page that includes `ensure_extended_schema.php`
2. Load `coordinator_generate_test.php` (has its own migration checks)
3. Load `coordinator_internships.php` (has its own migration checks)

### Option 2: Manual Fix
Visit the utility page to manually verify and apply fixes:
```
http://localhost/IMP/fix_auto_increment.php
```

This page will:
- Check each table for AUTO_INCREMENT status
- Display current column definitions
- Apply fixes as needed
- Show a summary of what was fixed

### Option 3: Direct Database Fixes
If you need to manually fix the tables, use these SQL commands:

```sql
-- Fix test_questions table
ALTER TABLE test_questions DROP PRIMARY KEY;
ALTER TABLE test_questions ADD PRIMARY KEY (id);
ALTER TABLE test_questions MODIFY id INT NOT NULL AUTO_INCREMENT;

-- Fix subtype_tests table
ALTER TABLE subtype_tests DROP PRIMARY KEY;
ALTER TABLE subtype_tests ADD PRIMARY KEY (id);
ALTER TABLE subtype_tests MODIFY id INT NOT NULL AUTO_INCREMENT;

-- Fix subtype_test_questions table
ALTER TABLE subtype_test_questions DROP PRIMARY KEY;
ALTER TABLE subtype_test_questions ADD PRIMARY KEY (id);
ALTER TABLE subtype_test_questions MODIFY id INT NOT NULL AUTO_INCREMENT;

-- Fix question_bank table
ALTER TABLE question_bank DROP PRIMARY KEY;
ALTER TABLE question_bank ADD PRIMARY KEY (id);
ALTER TABLE question_bank MODIFY id INT NOT NULL AUTO_INCREMENT;
```

## Testing the Fix

After applying the fix:

1. Go to **Coordinator → Generate Test**
2. Select a project type/subtype
3. Add questions manually or generate them
4. Click **Save Test**
5. The test should save successfully without errors

## Files Modified
- `/ensure_extended_schema.php` - Added AUTO_INCREMENT fix logic
- `/fix_auto_increment.php` - Created utility script for manual fixes
- `/scripts/seed_subtype_test.php` - Fixed column schema
- `/scripts/add_question_bank_and_questions.php` - Fixed column schema
- `/scripts/add_questions.php` - Fixed column schema

## Verification Steps

To verify the fix worked:

1. Check that tables have AUTO_INCREMENT:
   ```sql
   SHOW COLUMNS FROM test_questions;
   SHOW COLUMNS FROM subtype_tests;
   SHOW COLUMNS FROM subtype_test_questions;
   SHOW COLUMNS FROM question_bank;
   ```
   Look for "auto_increment" in the "Extra" column

2. Test INSERT operation:
   ```sql
   INSERT INTO test_questions (internship_id, question_text, option_a, option_b, option_c, option_d, correct_option)
   VALUES (1, 'Test Q1', 'A', 'B', 'C', 'D', 'A');
   ```
   Should succeed without requiring an id value

## Related Error Messages
This fix also resolves similar errors on other tables that might have the same issue.
