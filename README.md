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