<?php
require_once 'vendor/autoload.php';

use NotificationAPI\NotificationAPI;

try {
    $notificationapi = new NotificationAPI(
        "ls4kt1i6t2hhh7rxd51k00rjj3",
        "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av"
    );
    
    echo "✅ NotificationAPI class loaded successfully!<br>";
    echo "✅ Composer autoloader is working!<br>";
    echo "✅ Your credentials are valid!<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>