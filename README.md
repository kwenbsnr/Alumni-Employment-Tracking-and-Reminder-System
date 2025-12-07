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
│  ├─ all_alumni.php               # All alumni records
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

BY MARIAN:

Notification System Fixes:

- One notification per action - When admin approves/rejects/changes status, only one notification is sent
- No duplicate "Complete Profile" notifications - Fixed double notifications for profile setup
- Simple notification messages - No "Dear" or formal language, just status updates
- Fixed database structure - Added missing "type" column to notifications table
- Submission status notifications - Alumni get notified when submissions open/close
- Proper notification types - Success (approved), Error (rejected), Warning (pending), Info (updates)

Summary of Changes to Generate Report Function:
Summary report, and detailed alumni list with filter out of emp status integration (90%) - on the summary report, the filter out have issues [displays other emp stat column even if not selected]
1. SQL Query Modification:
- Made employment status columns conditional in SELECT statement
- Only includes columns for selected statuses, others set to 0

2. PDF Table Configuration:
- Table headers dynamically built based on selected statuses
- Column widths adjusted to only show selected statuses
- Data keys array only includes keys for selected statuses

3. Data Processing Fixes:
- Added checks for undefined array keys before accessing
- Used null coalescing operator (??) with default values
- Fixed employment rate calculation to use only selected statuses

4. Grand Total Calculation:
- Totals only calculated for selected status columns
- Employment rate based on filtered status data only

Dashbaord:
- just fixed the positioning, layout and everything.

  
# WHAT TO FIX: 
## ADMIN:
### 1. Unnecessary program field in the hover since da system is exclusively for da bsit prog. pero it's fine rpud for me. i mean if we wish to expand to include other deps in da future,dba at least it's there na. (not really a bug though).
### 2. Toast notif upon approval/rej.
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
