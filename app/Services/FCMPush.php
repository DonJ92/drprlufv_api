<?php

namespace App\Services;
use App\User;
use App\Notification;
use DB;

class FCMPush
{
    public function __construct()
    {

    }

    function send($userId, $title, $message,$ref_id = 0) {
        $user = User::where('id', $userId)->get()->first();

        if ($user == null)
            return false;        

        $notification = Notification::create(array(
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'ref_id' => $ref_id
        ));

        $serverKey = getenv("FCM_SERVER_KEY");

        $deviceToken = $user->device_token;

        // Your Firebase Server API Key
        $headers = array('Authorization:key=' . $serverKey, 'Content-Type:application/json');

        $url = 'https://fcm.googleapis.com/fcm/send';
      
        $fields = array(
            'to' => $deviceToken,
            'data' => array(
                'title' => $title,
                'body' => $message,
            ),
            'notification' => array(
                'title' =>$title , 
                'body' => $message, 
                'sound' => 'default', 
                'badge' => '1'
            )
        );
  
        
        // Open curl connection
        $ch = curl_init();
        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        
        $result = curl_exec($ch);

        if ($result === FALSE) {
            return false;
        }
        curl_close($ch);

        return true;
    }
}