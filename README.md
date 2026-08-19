---

The **Alumni Employment Tracking and Reminder System** is a web-based platform designed to help academic institutions track alumni employment status, manage required document submissions, and send automated reminders.

It supports **Admins** and **Alumni**, providing structured management of profiles, employment records, and document submissions. The system includes **automated notifications**, including **Gmail notifications**, for approvals, rejections, resubmissions, and scheduled reminders. The current implementation is designed exclusively for BSIT alumni.

---

## Key Features

### Alumni Module

* Secure login and profile management
* Employment information submission and updates
* Upload documents (COR, COE, Business Certificate)
* Notification alerts (in-system + Gmail) for approvals, rejections, and reminders
* View submission status and history

### Admin Module

* Dashboard with submission monitoring
* Batch-based alumni management
* Review and approve/reject alumni submissions
* Submission scheduling (open/close periods)
* Activity logging for accountability
* Report generation
* Automated notifications for new, updated submissions, and resubmissions after admin rejection

### Notifications

* Real-time in-system notifications
* Gmail notifications for submissions and reminders (includes dynamic alumni name and rejection reason)
* Semi-annual automated reminders
* Manual overrides for submission schedules

---

## Installation and Setup

### 1. Clone the Repository

```bash
git clone https://github.com/kwenbsnr/Alumni-Employment-Tracking-and-Reminder-System.git
```

### 2. Move Project to XAMPP

Place the project folder in:

```
C:\xampp\htdocs\
```

### 3. Import the Database

1. Open **phpMyAdmin**
2. Create a database (`alumni_tracking`)
3. Import the provided SQL file (`config/sql/alumni_tracking(6).sql`)

### 4. Install Dependencies 

```bash
composer install
```

### 5. Start XAMPP Services

* Start **Apache**
* Start **MySQL**

### 6. Access the System

```
http://localhost/Alumni-Employment-Tracking-and-Reminder-System/login/login.php
```

---

## Scheduled Reminders

* Windows Task Scheduler is used to trigger `send_semiannual_updates.bat`
* Sends reminders to alumni who need to update their employment info

---

```## 📁 Project Directory Structure

Alumni-Employment-Tracking-and-Reminder-System/
│  
├─ .vscode/                                   
│
├─ SRS/
│  └─ Software Requirements Specification.pdf
│
├─ admin/                                     # Admin module
│  ├─ activity_log.php                   # Tracks admin actions and activity history
│  ├─ admin_dashboard.php                # Admin dashboard
│  ├─ admin_format.css                   # Admin header and sidebar styles
│  ├─ admin_format.php                   # Admin header and sidebar layout
│  ├─ alumni_management.php              # Batch cards display with search/filter features
│  ├─ batch_alumni.php                   # Alumni list for a given batch and management actions
│  ├─ check_status.php                   # Checks submission status
│  ├─ get_alumni_details.php             # Hover preview of alumni details
│  ├─ get_documents.php                  # For admin document viewing
│  ├─ monitor_submission_status.php      # Monitors submission status across batches
│  ├─ report_generation.php              # Generates reports for alumni submissions
│  ├─ set_schedule.php                   # Set submission schedules (open/close)
│  ├─ submission_review.php              # Review alumni submissions and documents
│  ├─ submission_schedule.php            # Main submission control panel
│  └─ update_status.php                  # Backend logic; handles approval/rejection and notifications
│
├─ alumni/                                    # Alumni module
│  ├─ alumni_dashboard.php               # Alumni dashboard
│  ├─ alumni_employment.php              # Employment information page (frontend)
│  ├─ alumni_format.css                  # Alumni header and sidebar styles
│  ├─ alumni_format.php                  # Alumni header and sidebar layout
│  ├─ alumni_profile.php                 # Profile management page
│  ├─ mark_all_notifications_read.php    # Marks all notifications as read
│  ├─ update_employment.php              # Backend logic for Employment Info
│  └─ update_profile.php                 # Backend logic for Profile management
│
├─ api/                                       # API endpoints
│  └─ notif_temps/                       
│  └─ notification/
│  └─ utils/

├─ config/
│  └─ assets/
│
├─ config/
│  ├─ sql/                                    # SQL dump 
│  ├─ paths.php
│  ├─ pop_alumn.php
│  └─ notification_config.php
│
├─ login/
│  ├─ images/
│  ├─ auth.php
│  ├─ login.css
│  ├─ login.js
│  ├─ login.php
│  └─ logout.php
│
├─ node_modules/                             
├─ tcpdf/          
├─ vendor/
├─ uploads/                                    
│
├─ .gitignore
├─ README.md
├─ cleanup_old_notifications.php
├─ composer.json
├─ composer.lock
├─ connect.php
├─ get_notification_count.php
├─ get_notifications.php
├─ mark_notification_read.php
├─ tailwind.config.js
└─ send_semiannual_updates.bat
```

## Notes

* **Passwords are hashed, but for demonstration purposes the hash is simply the user's first name.** 
* File uploads are organized by document type

---
