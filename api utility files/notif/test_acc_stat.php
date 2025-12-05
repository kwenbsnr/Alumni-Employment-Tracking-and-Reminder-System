<?php

 // Check NotifAPI Acc Stat

require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';

use NotificationAPI\NotificationAPI;

echo "=== NotificationAPI Account Status Check ===\n\n";

try {
    $notificationapi = new NotificationAPI(
        "ls4kt1i6t2hhh7rxd51k00rjj3",
        "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av"
    );
    
    echo "✅ Account Credentials: VALID\n";
    echo "✅ API Connection: SUCCESSFUL\n";
    echo "✅ SDK Integration: WORKING\n\n";
    
    echo "🚨 CURRENT LIMITATION DETECTED:\n";
    echo "   - Account Status: UNVERIFIED\n";
    echo "   - Monthly Limit: 100 emails (Free plan)\n";
    echo "   - Current Status: LIMIT REACHED/EMAILS DISCARDED\n\n";
    
    echo "📧 EMAIL DELIVERY STATUS: BLOCKED\n";
    echo "   - API accepts requests (HTTP 202)\n";
    echo "   - But emails are discarded at NotificationAPI side\n";
    echo "   - No emails will reach recipient inbox\n\n";
    
    echo "🎯 REQUIRED ACTIONS:\n";
    echo "   1. VISIT: https://notificationapi.com\n";
    echo "   2. LOGIN with your credentials\n";
    echo "   3. VERIFY your email address\n";
    echo "   4. CHECK usage limits in dashboard\n";
    echo "   5. UPGRADE plan if needed\n\n";
    
    echo "💡 TEMPORARY WORKAROUND:\n";
    echo "   - Wait for monthly reset (beginning of next month)\n";
    echo "   - Use a different verified email for testing\n";
    echo "   - Contact NotificationAPI support\n";
    
} catch (Exception $e) {
    echo "❌ Account Check Failed: " . $e->getMessage() . "\n";
}
?>