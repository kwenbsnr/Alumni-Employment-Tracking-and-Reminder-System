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
│  ├─ check_paths.php              # Temp debugging utility file
│  ├─ all_alumni.php               # All alumni
│  └─ admin_format.css             # Admin header and sidebar styles
│
├─ alumni/                               # Alumni module
│  ├─ alumni_dashboard.php        # Alumni dashboard
│  ├─ alumni_format.php           # Alumni header and sidebar layout
│  ├─ alumni_profile.php          # Profile management page
│  ├─ update_profile.php          # Backend logic
│  └─ alumni_format.css           # Alumni header and sidebar styles
```
____________________
# WHAT TO FIX: 
### 1. Generate Report 
### 2. Rejected Profile rejection message.
### 3. Recent activity on the alumni.
##
## ALUMNI:
### 1. Apostrophe handling 
### 2. Start & end year display
### 3. idk if dis is meant to be there. everytime the "Help" button is clicked, an alert msg appears bef the actual modal.
### 4. modal shadow naay line above.
### 5. idk. redundant dboard buttons on cards??
##
## ADMIN:
### 1. Unnecessary program field in the hover since da system is exclusively for da bsit prog.
### 2. Rejected card display
### 3. Incorrect DB fetching query for batch.
### 4. Must automatically display batch cards if grad is manipulated in the db - same for the graph.
### 5. Total alumni display cards must be the total 'role===alumni' in the db.
### 6. we can add active alumni card for those nka submit.
### 7. dark text when chart is hovered, para clear the text display.
##
## DB:
### 1. filepath must be surname + filenm for clarity.
### 2. it would be better perhaps if middle name is a separate column , since full midname is needed in reality, not only the initials man. 
### 3. same for the entire name field, just to make the db atomic as well, thus satisfying 1NF.
### 4. redudant name field - users.name & alumni_profile.name.
____________________
# TO IMPLEMENT (if time allows 🙏):
### 1. Worldwide address 
### 2. In-app notif functionality for both alumni and admin. 
________________________________________
____________________

Summary of Changes Implemented
- Submissions now close and open based on the admin toggle or scheduled dates.

- When submissions are closed, alumni cannot submit or edit their profiles.

- No data gets deleted—all approved, pending, and rejected submissions remain stored.

- Admin can still review, approve, reject, or revisit any existing profile at any time.

- The open/close feature now works only as a control for new entries, without affecting existing records.

____________________
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

____________________ 

1. Changed the user and alumni profile sql. 
2. School Information Integration (working 100% and reflects on admin) 
3. Fixed the completion status. 
4. Changed most of the admin codes (fixed the errors from the sql update) 
5. Updated the user profile hover on admin (100% working and data fetched) 
6. Added an open/close submission with date customization and 100% reflects on the alumni. 