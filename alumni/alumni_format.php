<?php
// Fetch user data from users table
$stmt = $conn->prepare("
    SELECT 
        u.user_id, 
        CONCAT(
            u.first_name, 
            IF(u.middle_name IS NOT NULL AND u.middle_name != '', CONCAT(' ', u.middle_name), ''),
            ' ',
            u.last_name,
            IF(u.suffix IS NOT NULL AND u.suffix != '', CONCAT(' ', u.suffix), '')
        ) as official_name,
        u.email, u.role,
        ap.photo_path, ap.contact_number, ap.employment_status, 
        ap.submission_status, ap.submitted_at, ap.last_profile_update
    FROM users u
    LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id
    WHERE u.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: [];
$stmt->close();

// Build full name - FIXED LOGIC
$full_name = 'Alumni';
if (!empty($profile['official_name'])) {
    $full_name = trim($profile['official_name']);
}

$user_email = $profile['email'] ?? '';
$photo_path = $profile['photo_path'] ?? null;

// Fetch notifications data
$notif_count = 0;
$notifications = [];

// Check for profile submission status
if ($profile) {
    $submission_status = $profile['submission_status'] ?? 'Not Submitted';
    $submitted_at = $profile['submitted_at'] ?? null;
    
    switch ($submission_status) {
        case 'Pending':
            $notifications[] = [
                'title' => 'Profile Under Review',
                'message' => 'Your profile submission is currently being reviewed by administrators.',
                'timestamp' => $submitted_at,
                'type' => 'warning'
            ];
            $notif_count++;
            break;
            
        case 'Approved':
            $notifications[] = [
                'title' => 'Profile Approved',
                'message' => 'Your alumni profile has been approved and is now active.',
                'timestamp' => $submitted_at,
                'type' => 'success'
            ];
            break;
            
        case 'Rejected':
            $notifications[] = [
                'title' => 'Profile Requires Updates',
                'message' => 'Your profile submission needs additional information. Please review and resubmit.',
                'timestamp' => $submitted_at,
                'type' => 'error'
            ];
            $notif_count++;
            break;
            
        case 'Not Submitted':
        default:
            $notifications[] = [
                'title' => 'Profile Setup Required',
                'message' => 'Please complete your alumni profile to access all features.',
                'timestamp' => null,
                'type' => 'info'
            ];
            $notif_count++;
            break;
    }
    
    // Check if profile needs completion (based on your dashboard logic)
    $has_basic_info = !empty($profile['contact_number']) && !empty($profile['employment_status']);
    $needs_completion = !$has_basic_info || empty($profile['photo_path']);
    
    if ($needs_completion && $submission_status === 'Not Submitted') {
        $notifications[] = [
            'title' => 'Complete Your Profile',
            'message' => 'Your profile is incomplete. Please fill in all required information.',
            'timestamp' => null,
            'type' => 'info'
        ];
        $notif_count++;
    }
} else {
    // No profile data at all
    $notifications[] = [
        'title' => 'Complete Your Profile',
        'message' => 'Welcome! Please set up your alumni profile to get started.',
        'timestamp' => null,
        'type' => 'info'
    ];
    $notif_count++;
}

$page_title = $page_title ?? "Alumni Page";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Alumni Tracking System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #033803ff;
            --forest-green: #013501ff;
            --lime-green: #015301ff;
            --sea-green: #004200ff;
            --light-bg: #014707ff;
            --dark-text: #1C1C1C;
        }
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--forest-green) 100%);
        }
        .card-hover { 
            transition: all 0.3s ease; 
        }
        .sidebar-item {
            transition: all 0.3s ease;
            color: #fff;
            padding-left: 14px;
        }
        .sidebar-item:hover {
            background: rgba(50, 205, 50, 0.1);
            border-left: 4px solid var(--lime-green);
        }
        .sidebar-item.active {
            background: rgba(34, 139, 34, 0.3);
            border-left: 4px solid var(--lime-green);
            padding-left: 10px;
        }
        .profile-avatar {
            background: linear-gradient(135deg, var(--lime-green) 0%, var(--sea-green) 100%);
        }
        .stats-card {
            background-color: white;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .sidebar-wrapper {
            height: 100vh;
            overflow-y: auto;
            position: sticky;
            top: 0;
        }
        .sidebar-wrapper::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        .hidden { 
            display: none; 
        }
        #profileUpdateModal {
            opacity: 0;
            transition: opacity 0.5s;
        }
        #profileUpdateModal.show { 
            opacity: 1; 
        }
        /* Sidebar Profile */
        .sidebar-profile {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 1rem;
        }
        .sidebar-profile-avatar {
            width: 128px;
            height: 128px;
            border: 4px solid rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 42px;
            color: white;
            background: linear-gradient(135deg, var(--lime-green) 0%, var(--sea-green) 100%);
            text-transform: uppercase;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        .sidebar-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Very thin dark green line below header */
        .header-accent-line {
            height: 2px;
            background-color: #022502; /* Very dark green */
            width: 100%;
        }

        /* Notification styles */
        .notification-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Notification type colors */
        .notification-success {
            border-left: 4px solid #10B981;
        }
        .notification-warning {
            border-left: 4px solid #F59E0B;
        }
        .notification-error {
            border-left: 4px solid #EF4444;
        }
        .notification-info {
            border-left: 4px solid #3B82F6;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-72 gradient-bg text-white flex-shrink-0">
        <div class="sidebar-wrapper flex flex-col justify-between">
            <div class="p-6">
                <!-- Logo -->
                <div class="flex items-center space-x-3 mb-10">
                    <div class="w-12 h-12 rounded-xl bg-white bg-opacity-20 flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-2xl"></i>
                    </div>
                    <h2 class="font-bold text-2xl">Alumni Portal</h2>
                </div>

                <!-- User Profile -->
                <div class="sidebar-profile pb-6 mb-6">
                    <div class="flex flex-col items-center text-center space-y-4">
                        <div class="sidebar-profile-avatar">
                            <?php
                            if ($photo_path && file_exists("../" . $photo_path)) {
                                $photo_url = "../" . htmlspecialchars($photo_path);
                                $photo_url .= '?v=' . filemtime("../" . $photo_path);
                                echo '<img src="' . $photo_url . '" alt="Profile" class="w-full h-full object-cover">';
                            } else {
                                $initials = 'AL';
                                if ($full_name !== 'Alumni') {
                                    $parts = array_filter(explode(' ', $full_name));
                                    $initials = '';
                                    foreach ($parts as $part) {
                                        $initials .= strtoupper(substr(trim($part), 0, 1));
                                    }
                                    $initials = substr($initials, 0, 2);
                                }
                                echo '<div class="w-full h-full flex items-center justify-center text-5xl font-bold text-white">' . htmlspecialchars($initials) . '</div>';
                            }
                            ?>
                        </div>
                        <div class="w-full">
                            <h3 class="font-bold text-lg truncate"><?php echo htmlspecialchars($full_name); ?></h3>
                            <p class="text-sm text-gray-200 truncate"><?php echo htmlspecialchars($user_email); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="space-y-2">
                    <a href="alumni_dashboard.php" class="sidebar-item <?php echo ($active_page ?? '') === 'dashboard' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-tachometer-alt w-5" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="alumni_profile.php" class="sidebar-item <?php echo ($active_page ?? '') === 'profile' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                        <i class="fas fa-user w-5" aria-hidden="true"></i>
                        <span>Profile Management</span>
                    </a>
                </nav>
            </div>

            <!-- Logout Section -->
            <div class="p-6">
                <hr class="border-gray-400 my-6">
                <a href="../login/logout.php" class="flex items-center space-x-3 text-white hover:text-red-500 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-sign-out-alt text-xl" aria-hidden="true"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-100 py-3 px-6 flex items-center justify-between sticky top-0 z-40">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900">
                    <?php echo ($active_page ?? '') === 'profile' ? 'Profile Management' : 'Dashboard Overview'; ?>
                </h1>
                <p class="text-sm text-gray-600 mt-1">
                    <?php if (($active_page ?? '') === 'profile'): ?>
                        Review and update your personal and academic information.
                    <?php else: ?>
                        Welcome back, <span class="font-semibold text-green-700"><?php echo htmlspecialchars($full_name); ?></span>! All alumni records, notifications, and status information are accessible through this dashboard.
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Header Actions -->
            <div class="flex items-center gap-3">
                <!-- Notifications -->
                <div class="relative">
                    <button id="notificationBtn" class="relative p-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                        <i class="fas fa-bell text-lg text-gray-700"></i>
                        <?php if ($notif_count > 0): ?>
                            <span id="notificationBadge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full border-2 border-white text-xs text-white flex items-center justify-center notification-badge font-semibold">
                                <?php echo $notif_count; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div id="notifPopup" class="absolute right-0 mt-2 w-96 bg-white rounded-xl shadow-xl border border-gray-200 hidden z-50 transform transition-all duration-200 ease-in-out">
                        <div class="p-4 border-b border-gray-200 font-semibold text-gray-800 flex justify-between items-center text-sm bg-gray-50 rounded-t-xl">
                            <span>Notifications</span>
                            <?php if ($notif_count > 0): ?>
                                <button id="markReadBtn" class="text-xs text-blue-600 hover:text-blue-800 hover:underline font-medium">Mark all as read</button>
                            <?php endif; ?>
                        </div>
                        <div class="max-h-96 overflow-y-auto text-sm">
                            <?php if (empty($notifications)): ?>
                                <div class="p-6 text-center text-gray-500">
                                    <i class="fas fa-bell-slash text-2xl mb-3 text-gray-400"></i>
                                    <p class="text-gray-600">No notifications</p>
                                    <p class="text-xs text-gray-500 mt-1">You're all caught up!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $index => $notification): ?>
                                    <div class="p-4 hover:bg-gray-50 border-b border-gray-100 notification-item notification-<?php echo $notification['type']; ?>">
                                        <div class="flex items-start space-x-3">
                                            <div class="flex-shrink-0 mt-1">
                                                <?php if ($notification['type'] === 'success'): ?>
                                                    <i class="fas fa-check-circle text-green-500 text-sm"></i>
                                                <?php elseif ($notification['type'] === 'warning'): ?>
                                                    <i class="fas fa-exclamation-triangle text-yellow-500 text-sm"></i>
                                                <?php elseif ($notification['type'] === 'error'): ?>
                                                    <i class="fas fa-times-circle text-red-500 text-sm"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-info-circle text-blue-500 text-sm"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1">
                                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($notification['title']); ?></p>
                                                <p class="text-gray-600 mt-1 text-sm"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <?php if ($notification['timestamp']): ?>
                                                    <p class="text-xs text-gray-500 mt-2">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <?php echo date('M j, Y g:i A', strtotime($notification['timestamp'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 border-t border-gray-200 bg-gray-50 rounded-b-xl text-center">
                            <a href="alumni_profile.php" class="text-xs text-green-600 hover:text-green-800 font-medium">
                                <i class="fas fa-cog mr-1"></i>Manage Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Help Button -->
                <button id="helpButton" class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-medium text-sm rounded-lg shadow-md hover:shadow-lg transition-all focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                    <i class="fas fa-question-circle text-sm"></i>
                    <span>Help</span>
                </button>
            </div>
        </div>

        <!-- Thin dark green accent line below header -->
        <div class="header-accent-line"></div>

        <!-- Main Content -->
        <main class="flex-1 p-5 overflow-hidden">
            <?php echo $page_content ?? ''; ?>
        </main>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Notification functionality
        const notifButton = document.getElementById('notificationBtn');
        const notifPopup = document.getElementById('notifPopup');
        const notifBadge = document.getElementById('notificationBadge');
        
        console.log('Notification button:', notifButton);
        console.log('Notification popup:', notifPopup);
        
        // Function to close popup
        function closePopup() {
            if (notifPopup && notifPopup.classList.contains('open')) {
                notifPopup.classList.remove('open');
            }
        }

        // Notification button click handler
        if (notifButton && notifPopup) {
            notifButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Notification button clicked');
                
                // Toggle the 'open' class
                if (notifPopup.classList.contains('open')) {
                    notifPopup.classList.remove('open');
                } else {
                    notifPopup.classList.add('open');
                }
            });

            // Close popup when clicking outside
            document.addEventListener('click', function(e) {
                if (!notifButton.contains(e.target) && !notifPopup.contains(e.target)) {
                    closePopup();
                }
            });

            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closePopup();
                }
            });

            // Mark all as read functionality
            const markReadBtn = document.getElementById('markReadBtn');
            if (markReadBtn) {
                markReadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Mark all as read clicked');
                    
                    // Hide notification badge
                    if (notifBadge) {
                        notifBadge.style.display = 'none';
                    }
                    
                    // Hide mark all as read button
                    markReadBtn.style.display = 'none';
                    
                    // Add visual feedback for read notifications
                    const notificationItems = document.querySelectorAll('.notification-item');
                    notificationItems.forEach(item => {
                        item.style.opacity = '0.6';
                        item.style.backgroundColor = '#f9fafb';
                    });
                    
                    // Show confirmation
                    const notificationHeader = notifPopup.querySelector('.p-4.border-b');
                    if (notificationHeader) {
                        const originalContent = notificationHeader.innerHTML;
                        notificationHeader.innerHTML = '<span class="text-green-600"><i class="fas fa-check mr-2"></i>All notifications marked as read</span>';
                        
                        setTimeout(() => {
                            notificationHeader.innerHTML = originalContent;
                            closePopup();
                        }, 1500);
                    } else {
                        setTimeout(() => {
                            closePopup();
                        }, 1000);
                    }
                });
            }
        } else {
            console.error('Notification elements not found:', {
                button: notifButton,
                popup: notifPopup
            });
        }

        // Prevent popup close when clicking inside popup
        if (notifPopup) {
            notifPopup.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });
</script>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<!-- Leaflet JavaScript -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map;
let marker;
let selectedLatLng;
let debounceTimer;
let geocodingInProgress = false;

// Initialize map
function initMap() {
    // Try to get current location, fallback to default
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const defaultCenter = [position.coords.latitude, position.coords.longitude];
                setupMap(defaultCenter, 13);
            },
            () => {
                // Default center (Philippines) if location access denied
                const defaultCenter = [12.8797, 121.7740];
                setupMap(defaultCenter, 6);
            }
        );
    } else {
        const defaultCenter = [12.8797, 121.7740];
        setupMap(defaultCenter, 6);
    }
}

function setupMap(center, zoom) {
    map = L.map('address-map').setView(center, zoom);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Add scale control
    L.control.scale().addTo(map);
    
    // Click event on map
    map.on('click', function(e) {
        selectedLatLng = e.latlng;
        updateMarker(selectedLatLng);
        reverseGeocode(selectedLatLng.lat, selectedLatLng.lng);
    });
    
    // Load existing address if any
    loadExistingAddress();
}

// Update marker position
function updateMarker(latlng) {
    if (marker) {
        map.removeLayer(marker);
    }
    marker = L.marker(latlng).addTo(map)
        .bindPopup('Selected Location')
        .openPopup();
    
    // Center map on marker with smooth animation
    map.flyTo(latlng, 15, {
        duration: 0.5
    });
    
    document.getElementById('latitude').value = latlng.lat.toFixed(6);
    document.getElementById('longitude').value = latlng.lng.toFixed(6);
    
    // Validate address after marker update
    validateAddress();
}

// Geocode address (search)
async function geocodeAddress(address) {
    if (geocodingInProgress) return;
    geocodingInProgress = true;
    
    try {
        showValidation('Searching for address...', 'info');
        
        const response = await fetch(`../api/geocode.php?action=geocode&address=${encodeURIComponent(address)}`);
        const results = await response.json();
        
        if (results && results.length > 0) {
            const firstResult = results[0];
            const lat = parseFloat(firstResult.lat);
            const lon = parseFloat(firstResult.lon);
            
            selectedLatLng = L.latLng(lat, lon);
            updateMarker(selectedLatLng);
            populateAddressFields(firstResult);
            showValidation('Address found and located on map!', 'success');
        } else {
            showValidation('Address not found. Please try a more specific address.', 'error');
        }
    } catch (error) {
        console.error('Geocoding error:', error);
        showValidation('Error searching address. Please try again.', 'error');
    } finally {
        geocodingInProgress = false;
    }
}

// Reverse geocode (coordinates to address)
async function reverseGeocode(lat, lon) {
    try {
        const response = await fetch(`../api/geocode.php?action=reverse&lat=${lat}&lon=${lon}&zoom=18`);
        const result = await response.json();
        
        if (result && result.address) {
            populateAddressFields(result);
            showValidation('Address updated from map location!', 'success');
        }
    } catch (error) {
        console.error('Reverse geocoding error:', error);
        showValidation('Could not get address details for this location.', 'warning');
    }
}

// Populate address fields from geocoding result
function populateAddressFields(data) {
    const address = data.address || data;
    
    document.getElementById('address-line1').value = address.road || address.house_number ? 
        `${address.house_number || ''} ${address.road || ''}`.trim() : '';
    document.getElementById('address-line2').value = address.neighbourhood || address.suburb || '';
    document.getElementById('city').value = address.city || address.town || address.village || address.county || '';
    document.getElementById('state-province').value = address.state || address.region || '';
    document.getElementById('country').value = address.country || '';
    document.getElementById('postal-code').value = address.postcode || '';
    
    // Validate after populating
    validateAddress();
}

// Load existing address data
function loadExistingAddress() {
    const existingLat = document.getElementById('latitude').value;
    const existingLng = document.getElementById('longitude').value;
    
    if (existingLat && existingLng) {
        const latLng = L.latLng(parseFloat(existingLat), parseFloat(existingLng));
        updateMarker(latLng);
    }
}

// Validate address completeness
function validateAddress() {
    const requiredFields = ['address-line1', 'city', 'state-province', 'country'];
    let missingFields = [];
    
    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (!field || !field.value.trim()) {
            missingFields.push(fieldId.replace('-', ' '));
        }
    });
    
    if (missingFields.length > 0) {
        showValidation(`Missing required fields: ${missingFields.join(', ')}`, 'error');
        return false;
    }
    
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    
    if (!lat || !lng) {
        showValidation('Please select a location on the map', 'warning');
        return false;
    }
    
    showValidation('Address is complete and ready!', 'success');
    return true;
}

// Show validation message
function showValidation(message, type) {
    const container = document.getElementById('address-validation');
    const messageEl = document.getElementById('address-validation-message');
    const textEl = document.getElementById('validation-text');
    
    if (!container || !messageEl || !textEl) return;
    
    // Clear previous classes
    messageEl.className = 'flex items-center p-3 rounded-md';
    
    // Add type-specific classes
    switch(type) {
        case 'success':
            messageEl.classList.add('bg-green-100', 'text-green-800', 'border', 'border-green-200');
            break;
        case 'error':
            messageEl.classList.add('bg-red-100', 'text-red-800', 'border', 'border-red-200');
            break;
        case 'warning':
            messageEl.classList.add('bg-yellow-100', 'text-yellow-800', 'border', 'border-yellow-200');
            break;
        case 'info':
            messageEl.classList.add('bg-blue-100', 'text-blue-800', 'border', 'border-blue-200');
            break;
    }
    
    textEl.textContent = message;
    container.classList.remove('hidden');
    
    // Auto-hide success messages after 3 seconds
    if (type === 'success') {
        setTimeout(() => {
            container.classList.add('hidden');
        }, 3000);
    }
}

// Geocode from form fields (two-way sync)
async function geocodeFromForm() {
    const addressLine1 = document.getElementById('address-line1').value.trim();
    const city = document.getElementById('city').value.trim();
    const state = document.getElementById('state-province').value.trim();
    const country = document.getElementById('country').value.trim();
    
    if (!addressLine1 || !city || !state || !country) {
        showValidation('Please fill in required address fields first', 'warning');
        return;
    }
    
    const address = `${addressLine1}, ${city}, ${state}, ${country}`;
    const postalCode = document.getElementById('postal-code').value.trim();
    const fullAddress = postalCode ? `${address}, ${postalCode}` : address;
    
    await geocodeAddress(fullAddress);
}

// Debounced geocoding for field changes
function debounceGeocode() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        if (validateAddress()) {
            geocodeFromForm();
        }
    }, 1500); // Wait 1.5 seconds after last keystroke
}

// Use current location
function useCurrentLocation() {
    if (navigator.geolocation) {
        showValidation('Getting your current location...', 'info');
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                selectedLatLng = L.latLng(lat, lon);
                updateMarker(selectedLatLng);
                reverseGeocode(lat, lon);
            },
            (error) => {
                showValidation('Could not get your location. Please allow location access.', 'error');
                console.error('Geolocation error:', error);
            },
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            }
        );
    } else {
        showValidation('Geolocation is not supported by your browser', 'error');
    }
}

// Clear address fields
function clearAddressFields() {
    document.getElementById('address-search').value = '';
    document.getElementById('address-line1').value = '';
    document.getElementById('address-line2').value = '';
    document.getElementById('city').value = '';
    document.getElementById('state-province').value = '';
    document.getElementById('country').value = '';
    document.getElementById('postal-code').value = '';
    document.getElementById('latitude').value = '';
    document.getElementById('longitude').value = '';
    
    if (marker) {
        map.removeLayer(marker);
        marker = null;
    }
    
    showValidation('Address fields cleared', 'info');
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    
    // Search button
    document.getElementById('search-address-btn').addEventListener('click', function() {
        const address = document.getElementById('address-search').value;
        if (address) {
            geocodeAddress(address);
        }
    });
    
    // Search field enter key
    document.getElementById('address-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const address = this.value;
            if (address) {
                geocodeAddress(address);
            }
        }
    });
    
    // Clear button
    document.getElementById('clear-address-btn').addEventListener('click', clearAddressFields);
    
    // Current location button
    document.getElementById('use-current-location-btn').addEventListener('click', useCurrentLocation);
    
    // Address field changes trigger geocoding
    const addressFields = document.querySelectorAll('.address-field');
    addressFields.forEach(field => {
        field.addEventListener('input', debounceGeocode);
    });
    
    // Modal open event - reinitialize map if needed
    const modal = document.getElementById('profileUpdateModal');
    if (modal) {
        // Reinitialize map when modal opens
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (!mutation.target.classList.contains('hidden')) {
                    // Small delay to ensure DOM is ready
                    setTimeout(() => {
                        if (!map || map._container && !map._container.clientHeight) {
                            initMap();
                        } else {
                            // Refresh map view
                            map.invalidateSize();
                        }
                    }, 100);
                }
            });
        });
        
        observer.observe(modal, {
            attributes: true,
            attributeFilter: ['class']
        });
    }
});

// Address validation before form submission
document.getElementById('alumniProfileForm')?.addEventListener('submit', function(event) {
    if (!validateAddress()) {
        event.preventDefault();
        showValidation('Please complete the address information and select a location on the map', 'error');
        return false;
    }
    return true;
});
</script>

</body>
</html>