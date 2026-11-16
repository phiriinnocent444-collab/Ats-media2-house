<?php
// ATS media house - configuration. Edit values here (XAMPP defaults shown).
return [
  'db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'ats_media_house', 'user' => 'root', 'pass' => ''],
  // Needed for admin operations (list/update/delete bookings, edit services).
  // Send it as the HTTP header  X-Admin-Token. Admin endpoints stay locked until you change it.
  'admin_token' => 'change_me_to_a_long_random_string',
  'airtel' => [
    'env'           => 'UAT', // switch to 'PROD' only after Airtel approves you
    'base_uat'      => 'https://openapiuat.airtel.africa',
    'base_prod'     => 'https://openapi.airtel.africa',
    'client_id'     => 'your_client_id_here',
    'client_secret' => 'your_client_secret_here',
    'country'       => 'ZM',
    'currency'      => 'ZMW',
  ],
];
