<?php

namespace App\Notification;

use NotificationAPI\NotificationAPI as OriginalNotificationAPI;

class NotificationAPIWrapper
{
    private $notificationAPI;
    
    public function __construct($clientId, $clientSecret, $baseURL = null)
    {
        // Validate credentials before passing to the original SDK
        if (!$clientId) {
            throw new \InvalidArgumentException('Bad clientId');
        }

        if (!$clientSecret) {
            throw new \InvalidArgumentException('Bad clientSecret');
        }
        
        $this->notificationAPI = new OriginalNotificationAPI($clientId, $clientSecret, $baseURL);
    }
    
    public function __call($method, $arguments)
    {
        if (!method_exists($this->notificationAPI, $method)) {
            throw new \BadMethodCallException("Method $method does not exist");
        }
        
        return call_user_func_array([$this->notificationAPI, $method], $arguments);
    }
    
    // You can also add specific methods if needed
    public function getOriginalInstance()
    {
        return $this->notificationAPI;
    }
}