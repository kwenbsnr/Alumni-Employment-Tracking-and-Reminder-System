```
📁 Annotated Directory Structure

├─ admin/                                 # Admin module
│  ├─ admin_dashboard.php          # Admin dashboard
│  ├─ alumni_management.php        # Batch cards display with search/filter features
│  ├─ edit_alumni.php              # Future dev; frontend for editing alumni details
│  ├─ get_documents.php            # For admin document viewing
│  ├─ update_alumni.php            # Backend logic for edit_alumni.php
│  ├─ update_status.php            # Backend logic; handles the approval/rejection and notif
│  ├─ admin_format.php             # Admin header and sidebar layout
│  ├─ batch_alumni.php             # Alumni list for a given batch and management actions
│  ├─ get_alumni_details.php       # Hover preview of alumni details
│  ├─ activity_log.php             # Tracks admin actions and activity history
│  ├─ check_paths.php              # Temp debugging utility 
│  └─ admin_format.css             # Admin header and sidebar styles
│
├─ alumni/                               # Alumni module
│  ├─ alumni_dashboard.php        # Alumni dashboard
│  ├─ alumni_format.php           # Alumni header and sidebar layout
│  ├─ alumni_profile.php          # Profile management page
│  ├─ update_profile.php          # Backend logic
│  └─ alumni_format.css           # Alumni header and sidebar styles
```


**Alumni Module Bug Report**


[FIXED NA ANG EVERYTHING] - MARIAN

🔴 Critical
____________________

1. **[FIXED]** Submission clearing issue:
When a rejected profile is resubmitted, previously entered details appear in the form, but clicking submit clears all data and reopens the form incorrectly. The form should reset automatically and allow smooth resubmission.

2. **[FIXED]** "Employed & Student" submission issue:
If a user selects "Employed & Student" in the employment status, the form submits successfully but does not store data in the employment_status column of the alumni_profile table and does not add a row in the alumni_documents table. Additionally, no data is displayed in the dashboard cards.

🟠 High Priority
______________________

1. **[FIXED]** Start year vs. graduation year logic:
If a user is a "Student" or “Employed & Student,” check that start year is later than graduation year. Additionally, the graduation year must be later than the start year.
 
2. **[FIXED]** Yellow rejection card display:
Rejection cards must appear in the dashboard, not only in the proceeding tab. It should match the style of the “Complete Your Profile” card.

🟡 Medium Priority 
______________________

1. **[FIXED]** Alumni data display:
After a successful submission, the Employment/Academic Details cards must be displayed on the dashboard using the same UI style as existing cards, positioned below the existing cards for consistency. Currently, these cards are displayed only in the Profile Management tab.

2. **[FIXED]** Profile completion card display logic:
If profile is rejected, completion card must display 0% instead of 100%. 

🟢 Low Priority 
______________________

1. **[FIXED]** Successful submission display issue:
Successful submission must appear on the dashboard like the “Complete Your Profile” card, but the color should be green.

2. Start & end year display:
If "Student" or "Employed & Student" is selected, start & end year values must display correctly in the Employment/Academic Details card after successful submission. 

3. Profile completion card status:
The profile completion card stat must display “Complete” when the form has been submitted successfully. Currently "Incomplete" ghapon ang display bsag submission done.

**[FIXED]** Apostrophe handling:
Employment/Academic Details display cards after successful submission have issues with apostrophe rendering. 

4. **[FIXED]** Header bar scroll issue: The header bar must remain fixed and not be scrollable. 
______________________
______________________
______________________

**Admin Module Bug Report**

🟢 Low Priority 
______________________

1. Sidebar and Header Bar Improvements: Currently, both have a fixed height and are scrollable.

2. Recent Activity Log Page Refinement.
  
3. General UI Refinement.

4. ang admin inig human approve/reject, dapat stay lng sa page & d mu redirect sa batch display page.

5. if mag approve ang admin dapat naay "undo approve" instead of directly showing the "reject" button.




Summary of Changes Implemented

- Submissions now close and open based on the admin toggle or scheduled dates.

- When submissions are closed, alumni cannot submit or edit their profiles.

- No data gets deleted—all approved, pending, and rejected submissions remain stored.

- Admin can still review, approve, reject, or revisit any existing profile at any time.

- The open/close feature now works only as a control for new entries, without affecting existing records.

NEW UPDATE - MARIAN
No Info Entered
- Can submit when the submission is open. Can’t if closed. 

Pending Profiles
- Can edit profile for as long as sub is open. 
- When closed, edit is not available.

Approved Profiles
- Cannot edit even if open/closed. 
- Can edit again after 6 months. 

Rejected Profiles
- Can edit if not closed (just like pending cases).

What i did: 

1. Changed the user and alumni profile sql. 
2. School Information Integration (working 100% and reflects on admin) 
3. Fixed the completion status. 
4. Changed most of the admin codes (fixed the errors from the sql update) 
5. Updated the user profile hover on admin (100% working and data fetched) 
6. Changed revert into undo as it is misleading (when undo is clicked, submission stat becomes pending again.) 
7. Added an open/close submission with date customization and 100% reflects on the alumni. 
8. Removed the quick actions. 
9. Enhanced most of the guis. 

WHAT TO FIX: 
1. Generate Report 
2. Notification button on both alumni and admin. 
3. Recent activity on the alumni.
4. Rejected Profile rejection message need to fixing. 

and idk…
