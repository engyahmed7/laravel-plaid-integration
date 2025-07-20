<?php

return [
    'client_id' => env('PLAID_CLIENT_ID'),
    'secret' => env('PLAID_SECRET'),
    'env' => env('PLAID_ENV', 'sandbox'),
];
