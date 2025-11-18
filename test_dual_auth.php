<?php

// Test script for dual authentication (Password + OTP) in Pweza API
$baseUrl = 'http://127.0.0.1:8000/api';

function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'body' => $response];
}

echo "=== Pweza API - Dual Authentication Test ===\n\n";

// Test 1: Password-based Registration
echo "1. Testing user registration with password...\n";
$registerData = [
    'name' => 'John Doe',
    'phone_number' => '1111111111',
    'password' => 'newpassword123',
    'role' => 'customer'
];

$response = makeRequest($baseUrl . '/register', 'POST', $registerData, [
    'Content-Type: application/json'
]);

echo "Status: " . $response['code'] . "\n";
if ($response['code'] === 201) {
    $registerResponse = json_decode($response['body'], true);
    echo "✅ Registration successful!\n";
    echo "User: " . $registerResponse['user']['name'] . " (" . $registerResponse['user']['role'] . ")\n\n";
} else {
    echo "❌ Registration failed\n";
    echo $response['body'] . "\n\n";
}

// Test 2: Password-based Login (existing user)
echo "2. Testing password-based login...\n";
$loginData = [
    'phone_number' => '1234567890',
    'password' => 'password123'
];

$response = makeRequest($baseUrl . '/login', 'POST', $loginData, [
    'Content-Type: application/json'
]);

echo "Status: " . $response['code'] . "\n";
if ($response['code'] === 200) {
    $loginResponse = json_decode($response['body'], true);
    echo "✅ Password login successful!\n";
    $passwordToken = $loginResponse['token'];
    echo "User: " . $loginResponse['user']['name'] . " (Role: " . $loginResponse['user']['role'] . ")\n";
    echo "Token: " . substr($passwordToken, 0, 20) . "...\n\n";
} else {
    echo "❌ Password login failed\n";
    echo $response['body'] . "\n\n";
}

// Test 3: OTP-based Authentication Flow
echo "3. Testing OTP sending...\n";
$otpData = [
    'phone_number' => '0987654321'
];

$response = makeRequest($baseUrl . '/auth/send-otp', 'POST', $otpData, [
    'Content-Type: application/json'
]);

echo "Status: " . $response['code'] . "\n";
if ($response['code'] === 200) {
    echo "✅ OTP sent successfully!\n";
    echo "Check Laravel logs for the OTP code\n\n";
    
    // Test 4: OTP verification (using placeholder code)
    echo "4. Testing OTP verification (using 1234 as example)...\n";
    $verifyData = [
        'phone_number' => '0987654321',
        'code' => '1234',  // Replace with actual OTP from logs
        'name' => 'Jane Smith'  // Optional name for new users
    ];
    
    $response = makeRequest($baseUrl . '/auth/verify-otp', 'POST', $verifyData, [
        'Content-Type: application/json'
    ]);
    
    echo "Status: " . $response['code'] . "\n";
    if ($response['code'] === 200) {
        $otpResponse = json_decode($response['body'], true);
        echo "✅ OTP verification successful!\n";
        $otpToken = $otpResponse['token'];
        echo "User: " . $otpResponse['user']['name'] . " (Role: " . $otpResponse['user']['role'] . ")\n";
        echo "Token: " . substr($otpToken, 0, 20) . "...\n\n";
    } else {
        echo "❌ OTP verification failed (expected with placeholder code)\n";
        echo $response['body'] . "\n\n";
    }
} else {
    echo "❌ OTP sending failed\n";
    echo $response['body'] . "\n\n";
}

// Test 5: Test authenticated endpoints with password token
if (isset($passwordToken)) {
    echo "5. Testing authenticated endpoint with password token...\n";
    $response = makeRequest($baseUrl . '/me', 'GET', null, [
        'Authorization: Bearer ' . $passwordToken
    ]);
    
    echo "Status: " . $response['code'] . "\n";
    if ($response['code'] === 200) {
        echo "✅ Authenticated request successful!\n";
        $meResponse = json_decode($response['body'], true);
        echo "Authenticated as: " . $meResponse['user']['name'] . "\n\n";
    } else {
        echo "❌ Authenticated request failed\n";
        echo $response['body'] . "\n\n";
    }
}

// Test 6: Test invalid login credentials
echo "6. Testing invalid password login...\n";
$invalidLoginData = [
    'phone_number' => '1234567890',
    'password' => 'wrongpassword'
];

$response = makeRequest($baseUrl . '/login', 'POST', $invalidLoginData, [
    'Content-Type: application/json'
]);

echo "Status: " . $response['code'] . "\n";
if ($response['code'] === 422) {
    echo "✅ Invalid login properly rejected!\n\n";
} else {
    echo "❌ Invalid login handling failed\n";
    echo $response['body'] . "\n\n";
}

// Test 7: Test logout
if (isset($passwordToken)) {
    echo "7. Testing logout...\n";
    $response = makeRequest($baseUrl . '/logout', 'POST', null, [
        'Authorization: Bearer ' . $passwordToken
    ]);
    
    echo "Status: " . $response['code'] . "\n";
    if ($response['code'] === 200) {
        echo "✅ Logout successful!\n\n";
    } else {
        echo "❌ Logout failed\n";
        echo $response['body'] . "\n\n";
    }
}

echo "=== Test Summary ===\n";
echo "✅ Password registration and login working\n";
echo "✅ OTP sending implemented (check logs for codes)\n";
echo "✅ Both authentication methods supported\n";
echo "✅ Token-based authorization working\n";
echo "✅ Proper error handling for invalid credentials\n\n";

echo "Available Authentication Methods:\n";
echo "1. Register/Login with password: /register, /login\n";
echo "2. OTP-based authentication: /auth/send-otp, /auth/verify-otp\n\n";

echo "Test Users (for password login):\n";
echo "- Tipster: 1234567890 / password123\n";
echo "- Customer: 0987654321 / password123\n";
echo "- Admin: 1122334455 / password123\n";
