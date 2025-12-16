<?php
// Database configuration
$host = 'localhost';
$dbname = 'alumni_populated';
$username = 'root';
$password = '';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected successfully\n";
    echo "Starting to populate database...\n";
    
    // Disable foreign key checks temporarily for faster insertion
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Clear existing data (optional - comment out if you want to keep existing data)
    echo "Clearing existing data...\n";
    $pdo->exec("TRUNCATE TABLE alumni_activity_log");
    $pdo->exec("TRUNCATE TABLE alumni_documents");
    $pdo->exec("TRUNCATE TABLE alumni_profile");
    $pdo->exec("TRUNCATE TABLE education_info");
    $pdo->exec("TRUNCATE TABLE employment_info");
    $pdo->exec("TRUNCATE TABLE update_log");
    $pdo->exec("TRUNCATE TABLE alumni_address");
    $pdo->exec("TRUNCATE TABLE users");
    $pdo->exec("TRUNCATE TABLE job_titles");
    
    // Re-enable foreign key checks for normal operations
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // 1. First, populate job_titles lookup table
    echo "Populating job_titles lookup table...\n";
    $jobTitles = [
        'Software Developer',
        'Software Engineer',
        'Web Developer',
        'Frontend Developer',
        'Backend Developer',
        'Full Stack Developer',
        'Mobile App Developer',
        'DevOps Engineer',
        'Systems Administrator',
        'Network Engineer',
        'Database Administrator',
        'Data Analyst',
        'Data Scientist',
        'IT Support Specialist',
        'IT Project Manager',
        'Quality Assurance Engineer',
        'UX/UI Designer',
        'Business Analyst',
        'Accountant',
        'Financial Analyst',
        'Marketing Specialist',
        'Sales Representative',
        'Human Resources Specialist',
        'Operations Manager',
        'Customer Service Representative',
        'Teacher/Instructor',
        'Nurse',
        'Engineer (Civil, Electrical, Mechanical)',
        'Architect',
        'Graphic Designer',
        'Content Writer',
        'Social Media Manager',
        'Entrepreneur/Business Owner',
        'Research Assistant',
        'Administrative Assistant'
    ];
    
    foreach ($jobTitles as $title) {
        $stmt = $pdo->prepare("INSERT INTO job_titles (title) VALUES (?)");
        $stmt->execute([$title]);
    }
    echo "Inserted " . count($jobTitles) . " job titles\n";
    
    // 2. Create a default admin user
    echo "Creating admin user...\n";
    $stmt = $pdo->prepare("
        INSERT INTO users 
        (email, password, role, first_name, last_name, student_id, 
         date_of_birth, gender, program, batch_year, citizenship, civil_status, 
         contact_number, created_at)
        VALUES 
        (?, ?, 'admin', 'Admin', 'User', 'ADMIN001', '1990-01-01', 
         'Male', 'Administration', 2010, 'FILIPINO', 'Single', 
         '09123456789', NOW())
    ");
    $stmt->execute([
        'admin@alumnitracking.edu.ph',
        password_hash('admin123', PASSWORD_DEFAULT)
    ]);
    $adminId = $pdo->lastInsertId();
    echo "Admin created with ID: $adminId\n";
    
    // 3. Create submission_status record for admin control
    echo "Creating submission_status record...\n";
    $stmt = $pdo->prepare("
        INSERT INTO submission_status 
        (is_open, manual_override, open_date, close_date, employment_submission_open)
        VALUES 
        (0, 0, NULL, NULL, 0)
    ");
    $stmt->execute();
    echo "Submission status record created\n";
    
    // 4. Now populate alumni data
    echo "\nStarting alumni population...\n";
    
    // Disable foreign key checks again for bulk insertion
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Sample data arrays
    $firstNames = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Carmen', 'Antonio', 'Isabel', 
                   'Ramon', 'Sofia', 'Miguel', 'Elena', 'Carlos', 'Rosa', 'Francisco', 'Teresa',
                   'Ricardo', 'Gabriela', 'Fernando', 'Patricia', 'Roberto', 'Lourdes', 
                   'Eduardo', 'Mercedes', 'Alfredo', 'Consuelo', 'Manuel', 'Dolores'];
    
    $lastNames = ['Dela Cruz', 'Santos', 'Reyes', 'Gonzales', 'Ramos', 'Aquino', 'Mendoza', 
                  'Torres', 'Fernandez', 'Garcia', 'Bautista', 'Villanueva', 'Castro', 'Romero',
                  'Lopez', 'Martinez', 'Rivera', 'Ocampo', 'Silva', 'Cruz', 'Morales', 'Chavez'];
    
    $middleInitials = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];
    
    $cities = ['Manila', 'Quezon City', 'Makati', 'Pasig', 'Taguig', 'Mandaluyong', 
               'Marikina', 'Paranaque', 'Las Pinas', 'Muntinlupa', 'Valenzuela', 'Caloocan',
               'Malabon', 'Navotas', 'San Juan', 'Pasay'];
    
    $provinces = ['Metro Manila', 'Bulacan', 'Cavite', 'Laguna', 'Rizal', 'Batangas', 
                  'Pampanga', 'Tarlac', 'Nueva Ecija', 'Pangasinan'];
    
    $programs = [
        'Bachelor of Science in Computer Science',
        'Bachelor of Science in Information Technology',
        'Bachelor of Science in Business Administration',
        'Bachelor of Science in Accountancy',
        'Bachelor of Science in Civil Engineering',
        'Bachelor of Science in Electrical Engineering',
        'Bachelor of Science in Mechanical Engineering',
        'Bachelor of Elementary Education',
        'Bachelor of Secondary Education',
        'Bachelor of Science in Nursing',
        'Bachelor of Arts in Psychology',
        'Bachelor of Science in Hospitality Management',
        'Bachelor of Science in Tourism Management'
    ];
    
    $companies = ['Accenture', 'IBM', 'Oracle', 'Microsoft', 'Google', 'Amazon', 
                  'Shopee', 'Lazada', 'PLDT', 'Globe Telecom', 'SM Investments', 
                  'Ayala Corporation', 'San Miguel Corporation', 'Jollibee Foods Corporation',
                  'Megaworld Corporation', 'Bank of the Philippine Islands', 'Metrobank',
                  'Philam Life', 'Manulife', 'Sun Life Financial'];
    
    // Get the inserted job title IDs
    $jobTitleIds = $pdo->query("SELECT job_title_id FROM job_titles")->fetchAll(PDO::FETCH_COLUMN);
    
    // Define batch years
    $batchYears = [2020, 2021, 2022];
    $totalAlumni = 0;
    
    foreach ($batchYears as $batchYear) {
        echo "\nProcessing batch $batchYear (2000 records)...\n";
        
        for ($i = 1; $i <= 2000; $i++) {
            // Generate user data
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $middleName = $middleInitials[array_rand($middleInitials)] . '.';
            $email = strtolower($firstName[0] . $lastName . $i . $batchYear . '@alumni.edu.ph');
            $email = str_replace([' ', "'"], '', $email); // Remove spaces and apostrophes
            $studentId = 'STU' . $batchYear . str_pad($i, 4, '0', STR_PAD_LEFT);
            
            // Random dates
            $dobYear = rand(1995, 2000);
            $dobMonth = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
            $dobDay = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            $dob = "$dobYear-$dobMonth-$dobDay";
            
            $createdAt = date('Y-m-d H:i:s', mt_rand(
                strtotime($batchYear . '-06-01 00:00:00'),
                strtotime($batchYear . '-06-30 23:59:59')
            ));
            
            // Insert into users table
            $stmt = $pdo->prepare("
                INSERT INTO users 
                (email, password, role, first_name, last_name, middle_name, student_id, 
                 date_of_birth, gender, program, batch_year, citizenship, civil_status, 
                 contact_number, created_at)
                VALUES 
                (?, ?, 'alumni', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $gender = (rand(1, 2) == 1) ? 'Male' : 'Female';
            $civilStatuses = ['Single', 'Married', 'Single', 'Single', 'Married']; // Weighted for more singles
            $civilStatus = $civilStatuses[array_rand($civilStatuses)];
            $program = $programs[array_rand($programs)];
            $contact = '09' . rand(100000000, 999999999);
            
            $stmt->execute([
                $email,
                password_hash('alumni123', PASSWORD_DEFAULT),
                $firstName,
                $lastName,
                $middleName,
                $studentId,
                $dob,
                $gender,
                $program,
                $batchYear,
                'FILIPINO',
                $civilStatus,
                $contact,
                $createdAt
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // Insert into alumni_address
            $city = $cities[array_rand($cities)];
            $province = $provinces[array_rand($provinces)];
            $streetNumber = rand(1, 999);
            $streetNames = ['Rizal', 'Bonifacio', 'Mabini', 'Luna', 'Burgos', 'Gomez', 'Jacinto', 'Aguinaldo'];
            $streetName = $streetNames[array_rand($streetNames)];
            $street = "$streetNumber $streetName Street";
            
            $stmt = $pdo->prepare("
                INSERT INTO alumni_address 
                (user_id, city, state_province, street, country, created_at, updated_at)
                VALUES 
                (?, ?, ?, ?, 'Philippines', ?, ?)
            ");
            
            $addressCreated = date('Y-m-d H:i:s', strtotime($createdAt . ' +1 day'));
            $stmt->execute([$userId, $city, $province, $street, $addressCreated, $addressCreated]);
            
            // Insert into alumni_profile
            $employmentStatuses = ['Employed', 'Employed', 'Employed', 'Self-Employed', 'Unemployed', 'Student'];
            $employmentStatus = $employmentStatuses[array_rand($employmentStatuses)];
            
            $stmt = $pdo->prepare("
                INSERT INTO alumni_profile 
                (user_id, employment_status, photo_path, last_profile_update, submitted_at)
                VALUES 
                (?, ?, ?, ?, ?)
            ");
            
            $profileDate = date('Y-m-d H:i:s', strtotime($createdAt . ' +2 days'));
            $stmt->execute([
                $userId, 
                $employmentStatus, 
                '/uploads/profiles/default_avatar.png', 
                $profileDate, 
                $profileDate
            ]);
            
            // Insert education info
            $stmt = $pdo->prepare("
                INSERT INTO education_info 
                (user_id, school_name, degree_pursued, start_year, end_year)
                VALUES 
                (?, ?, ?, ?, ?)
            ");
            
            $startYear = $batchYear - 4;
            $schools = [
                'University of the Philippines',
                'Ateneo de Manila University',
                'De La Salle University',
                'University of Santo Tomas',
                'Far Eastern University',
                'University of the East',
                'Polytechnic University of the Philippines',
                'Technological University of the Philippines'
            ];
            
            $school = $schools[array_rand($schools)];
            $stmt->execute([$userId, $school, $program, $startYear, $batchYear]);
            
            // Insert employment info (if employed or self-employed)
            if (in_array($employmentStatus, ['Employed', 'Self-Employed'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO employment_info 
                    (user_id, job_title_id, company_name, salary_range, business_type, company_address)
                    VALUES 
                    (?, ?, ?, ?, ?, ?)
                ");
                
                $salaryRanges = [
                    'Below ₱10,000',
                    '₱10,000–₱20,000',
                    '₱20,000–₱30,000',
                    '₱30,000–₱40,000',
                    '₱40,000–₱50,000',
                    'Above ₱50,000'
                ];
                
                // Weight salary ranges based on batch year (newer graduates get lower ranges)
                $salaryIndex = rand(0, min(2 + ($batchYear - 2020), 5)); // Graduates from earlier years get higher potential salaries
                
                $company = $companies[array_rand($companies)];
                $businessTypes = [
                    'Information Technology',
                    'Finance and Banking',
                    'Retail and E-commerce',
                    'Telecommunications',
                    'Real Estate',
                    'Food and Beverage',
                    'Education',
                    'Healthcare',
                    'Manufacturing',
                    'Hospitality and Tourism'
                ];
                
                $businessType = $businessTypes[array_rand($businessTypes)];
                
                $stmt->execute([
                    $userId,
                    $jobTitleIds[array_rand($jobTitleIds)],
                    $company,
                    $salaryRanges[$salaryIndex],
                    $businessType,
                    $city . ', ' . $province . ', Philippines'
                ]);
            }
            
            // Insert alumni documents (random 1-3 documents per user)
            $docTypes = ['COR', 'COE', 'B_CERT'];
            $numDocs = rand(1, 3);
            $statuses = ['Pending', 'Approved', 'Approved', 'Approved']; // Weighted towards approved
            
            for ($d = 0; $d < $numDocs; $d++) {
                $docType = $docTypes[array_rand($docTypes)];
                $status = $statuses[array_rand($statuses)];
                
                $stmt = $pdo->prepare("
                    INSERT INTO alumni_documents 
                    (user_id, document_type, file_path, document_status, rejected_at)
                    VALUES 
                    (?, ?, ?, ?, ?)
                ");
                
                $rejectedAt = ($status == 'Rejected') ? date('Y-m-d H:i:s', strtotime($createdAt . ' +7 days')) : NULL;
                $stmt->execute([
                    $userId,
                    $docType,
                    '/uploads/documents/' . strtolower($docType) . '_' . $userId . '.pdf',
                    $status,
                    $rejectedAt
                ]);
            }
            
            // Insert activity log (1-3 random activities)
            $actions = [
                ['Profile Created', 'Initial profile creation'],
                ['Document Uploaded', 'Uploaded supporting documents'],
                ['Profile Updated', 'Updated personal information'],
                ['Login', 'Logged into the system'],
                ['Employment Info Updated', 'Updated employment details'],
                ['Education Info Updated', 'Updated educational background']
            ];
            
            $numActivities = rand(1, 3);
            for ($a = 0; $a < $numActivities; $a++) {
                $action = $actions[array_rand($actions)];
                $activityDate = date('Y-m-d H:i:s', strtotime($createdAt . ' +' . rand(0, 30) . ' days'));
                
                $stmt = $pdo->prepare("
                    INSERT INTO alumni_activity_log 
                    (user_id, action_type, description, created_at)
                    VALUES 
                    (?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $userId,
                    $action[0],
                    $action[1] . ' by ' . $firstName . ' ' . $lastName,
                    $activityDate
                ]);
            }
            
            // Insert update log (admin actions)
            if (rand(1, 10) <= 3) { // 30% chance of having an admin update
                $updateTypes = ['update', 'approve', 'reject'];
                $updateType = $updateTypes[array_rand($updateTypes)];
                
                $updateDetails = [
                    'update' => 'Updated user profile information',
                    'approve' => 'Approved document submission',
                    'reject' => 'Rejected document with reason: Incomplete information'
                ];
                
                $stmt = $pdo->prepare("
                    INSERT INTO update_log 
                    (updated_by, updated_id, update_type, update_details, updated_at)
                    VALUES 
                    (?, ?, ?, ?, ?)
                ");
                
                $updateDate = date('Y-m-d H:i:s', strtotime($createdAt . ' +' . rand(5, 60) . ' days'));
                $stmt->execute([
                    $adminId,
                    $userId,
                    $updateType,
                    $updateDetails[$updateType],
                    $updateDate
                ]);
            }
            
            $totalAlumni++;
            
            // Show progress
            if ($i % 500 == 0) {
                echo "  Processed $i alumni for batch $batchYear...\n";
            }
        }
        
        echo "Completed batch $batchYear\n";
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Final summary
    echo "\n========================================\n";
    echo "DATABASE POPULATION COMPLETED SUCCESSFULLY!\n";
    echo "========================================\n";
    echo "Total alumni created: $totalAlumni\n";
    echo "Batches processed: " . count($batchYears) . " (2020, 2021, 2022)\n";
    echo "Alumni per batch: 2000\n";
    echo "Job titles inserted: " . count($jobTitles) . "\n";
    echo "Admin user created: admin@alumnitracking.edu.ph / admin123\n";
    echo "Default alumni password: alumni123\n";
    echo "\nSubmission Status:\n";
    echo "- Profile submission: CLOSED (is_open = 0)\n";
    echo "- Employment submission: CLOSED (employment_submission_open = 0)\n";
    echo "- Admin can toggle these in the submission_status table\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>