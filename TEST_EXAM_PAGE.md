# Testing the Exam Page

## How to Test the Exam/Test Functionality

### Step 1: Login as a Student
1. Open your browser and go to: `http://localhost/IMP/login.html`
2. Login with your student credentials

### Step 2: View Your Applications
1. After login, you'll be redirected to the student dashboard
2. Click on **"My Applications"** in the sidebar
3. Or go directly to: `http://localhost/IMP/student_applications.php`

### Step 3: Start the Test
1. Find an application with status **"Applied"**
2. You should see:
   - A deadline warning box showing the countdown timer
   - A **"Start Test"** button (purple/blue)
3. Click the **"Start Test"** button

### Step 4: Take the Test
1. The test page will open: `http://localhost/IMP/student_test.php?app_id=X`
2. You'll see:
   - Test header with internship title
   - Domain-specific questions (4 questions)
   - Multiple choice options
   - Submit button at the bottom
3. Answer all 4 questions
4. Click **"Submit Assessment"**

### Step 5: View Results
1. After submission, you'll be redirected back to **My Applications**
2. You should see a success message: "Assessment Completed! Score: X/4"
3. The application status will now show **"Test Completed"**
4. The **"Start Test"** button will change to **"View Result"**

### Step 6: Check Timeline
1. Click **"View Details"** on the application
2. You'll see the full status timeline
3. The timeline will show:
   - ✓ Applied (completed)
   - ● Test Completed (current stage) with submitted timestamp
   - ○ HR Round (pending)
   - ○ HOD Approved (pending - if Pursuing)
   - ○ Selected (pending)

## Direct Test URLs

### If you already have an application ID (e.g., app_id = 5):
```
http://localhost/IMP/student_test.php?app_id=5
```

### To view application status:
```
http://localhost/IMP/view_application_status.php?app_id=5
```

### To view all applications:
```
http://localhost/IMP/student_applications.php
```

## Test Scenarios to Verify

### Scenario 1: Test Within Deadline
1. Apply for an internship
2. Immediately click "Start Test"
3. Complete the test
4. Verify status changes to "Test Completed"
5. Verify test_submitted_date is recorded

### Scenario 2: Test Deadline Warning
1. Apply for an internship
2. Wait (or manually adjust applied_date in database to be 46 hours ago)
3. Check that countdown shows "2h 0m" remaining
4. Verify warning box shows amber color

### Scenario 3: Test Deadline Expired
1. Apply for an internship
2. Manually adjust applied_date in database to be 49 hours ago:
   ```sql
   UPDATE internship_applications 
   SET applied_date = DATE_SUB(NOW(), INTERVAL 49 HOUR) 
   WHERE id = X;
   ```
3. Refresh the applications page
4. Verify "Test Expired" button shows (disabled)
5. Verify red warning box appears

### Scenario 4: View Test Result
1. Complete a test
2. Click "View Result" button
3. Verify modal shows:
   - Score (X/4)
   - Percentage
   - Questions and answers breakdown

## Database Queries for Testing

### Check application data:
```sql
SELECT id, internship_id, status, test_status, test_score, 
       test_submitted_date, applied_date,
       TIMESTAMPDIFF(HOUR, applied_date, NOW()) as hours_since_applied,
       TIMESTAMPDIFF(HOUR, applied_date, DATE_ADD(applied_date, INTERVAL 48 HOUR)) as deadline_hours
FROM internship_applications 
WHERE user_id = YOUR_USER_ID
ORDER BY applied_date DESC;
```

### Check status history:
```sql
SELECT * FROM application_status_history 
WHERE application_id = YOUR_APP_ID 
ORDER BY created_at DESC;
```

### Manually set test deadline to expire soon (for testing):
```sql
-- Set applied_date to 47 hours ago (1 hour remaining)
UPDATE internship_applications 
SET applied_date = DATE_SUB(NOW(), INTERVAL 47 HOUR) 
WHERE id = YOUR_APP_ID;

-- Set applied_date to 49 hours ago (expired)
UPDATE internship_applications 
SET applied_date = DATE_SUB(NOW(), INTERVAL 49 HOUR) 
WHERE id = YOUR_APP_ID;

-- Reset to current time
UPDATE internship_applications 
SET applied_date = NOW() 
WHERE id = YOUR_APP_ID;
```

## Expected Test Questions by Domain

### Frontend Development:
- HTML5 tags
- CSS box-sizing
- React useState hook
- JavaScript array methods

### Data Science:
- Pandas library
- SQL HAVING clause
- SQL TRUNCATE command
- pandas.dropna() function

### UI/UX Design:
- UX definition
- Design hierarchy principle
- Wireframe definition
- Figma usage

### Backend Development:
- HTTP 201 status code
- SQL prepared statements
- npm definition
- MongoDB database type

### General Aptitude (default):
- Binary Search Tree complexity
- HTTPS protocol
- Operating System kernel
- Stack data structure

## Troubleshooting

### Issue: "Start Test" button doesn't work
**Solution:** 
- Check that Apache and MySQL are running
- Verify the application exists in database
- Check browser console for JavaScript errors
- Verify the URL has correct app_id parameter

### Issue: Test doesn't submit
**Solution:**
- Check that all 4 questions are answered
- Verify MySQL connection in db.php
- Check PHP error logs: `C:\xampp\apache\logs\error.log`

### Issue: Countdown timer not showing
**Solution:**
- Verify applied_date is set in database
- Check that status is "Applied"
- Verify test_status is "Pending"
- Refresh the page

### Issue: Status not updating after test
**Solution:**
- Check that test_submitted_date column exists
- Verify application_status_history table exists
- Check MySQL error logs
- Verify user_id matches in session

## Browser Developer Tools

### Check Console for Errors:
1. Press F12 to open Developer Tools
2. Go to Console tab
3. Look for any JavaScript errors

### Check Network Requests:
1. Press F12 to open Developer Tools
2. Go to Network tab
3. Submit the test
4. Check the POST request to student_test.php
5. Verify response is 302 redirect

### Check Session Data:
1. Press F12 to open Developer Tools
2. Go to Application tab (Chrome) or Storage tab (Firefox)
3. Check Cookies for session data
4. Verify user_id is set

## Success Indicators

✅ **Test page loads correctly** with 4 questions
✅ **All questions are domain-specific** based on internship title
✅ **Submit button works** and redirects to applications page
✅ **Status updates** from "Applied" to "Test Completed"
✅ **test_submitted_date** is recorded in database
✅ **Status history** logs the change with score
✅ **Countdown timer** shows correct time remaining
✅ **Deadline warning** appears with correct color
✅ **Button changes** from "Start Test" to "View Result"
✅ **Timeline shows** test completion with timestamp

## Next Steps After Testing

1. Test with different domains (Frontend, Backend, Data Science, UI/UX)
2. Verify deadline expiration behavior
3. Test on mobile devices
4. Check email notifications (if implemented)
5. Test with multiple students simultaneously
6. Verify HR can see test scores
7. Test the complete workflow: Apply → Test → HR Round → HOD → Selected
