# Implementation Plan for Clickable Profile Avatar and Modal

## Goal
- Make the avatar circle in the top‑right corner of `student_dashboard.php` clickable.
- Show a modal panel with profile information and actions.
- Provide an edit form inside the modal for allowed fields (phone, skills, photo, resume, internship preferences).
- Ensure smooth animation, accessibility, and preserve current UI aesthetics.

## Files to Modify / Add
1. **`student_dashboard.php`**
   - Wrap avatar area with a button (`id="profile-avatar-btn"`).
   - Add a hidden modal container (`id="profile-modal"`) containing:
     - Header with avatar & name.
     - Read‑only fields (full name, email, college, course, Aadhaar/PAN status).
     - Editable inputs for phone, skills, photo upload, resume upload, internship preferences.
     - Action buttons: Save Changes, Cancel.
     - Profile actions list (Edit Profile – same modal, Change Password link, View Resume link, Logout button).
   - Add an overlay element (`id="profile-modal-overlay"`).
   - Include the new JS file: `<script src="js/profile.js"></script>`.
   - Add minimal CSS for modal/overlay (or extend existing stylesheet).

2. **`js/profile.js`** (already created)
   - Update to target `profile-modal` and overlay IDs.
   - Add event listeners for opening/closing the modal.
   - Handle Escape key and overlay click.
   - Submit the edit form via `fetch('update_profile.php')` with `FormData`.
   - Show toast/alert on success/failure.

3. **`update_profile.php`** (new file)
   - Verify session and CSRF token (basic check).
   - Accept `POST` fields: `phone`, `skills`, `internship_preferences` and file uploads `profile_image`, `resume`.
   - Validate and move uploaded files to `uploads/` directory.
   - Update the `student_profiles` table for the allowed columns.
   - Return JSON `{success:true, message:"Profile updated."}` or error info.

4. **`css/style.css`** (or existing stylesheet)
   - Add modal overlay style (`fixed inset-0 bg-black/30 hidden`), modal animation (`opacity`, `scale`), and ensure it matches the portal’s glassmorphism/gradient theme.
   - Add hover effect for avatar (`transform: scale(1.05)`).

## Open Questions (User Review Required)
- **File upload paths**: Should uploaded profile images/resumes be stored under `uploads/` or a dedicated folder? Confirm directory.
- **Internship preferences field**: Do we store it as a JSON string, CSV, or separate table? Clarify DB schema.
- **Existing `profile-dropdown` element**: Do we replace it entirely with the new modal, or keep it for legacy navigation?
- **CSRF protection**: Is there an existing token mechanism we should reuse?
- **Success toast UI**: Use existing toast component (already present) or simple `alert`?

## Verification Plan
- Manual test: Load dashboard, click avatar → modal appears with animation.
- Edit phone number, upload a new profile image, click Save → data updates in DB, modal closes, page reload shows new info.
- Click Cancel → modal closes with no changes.
- Verify that non‑editable fields are read‑only.
- Ensure logout button still works and redirects to login.
- Test on desktop & mobile viewports for responsiveness.

## Risks & Mitigations
- **File upload security**: Restrict mime types and size limits.
- **SQL injection**: Use prepared statements for updates.
- **UI clash**: Ensure modal z‑index is higher than sidebar.

---
*Please review the open questions and approve the plan so we can proceed with the implementation.*
