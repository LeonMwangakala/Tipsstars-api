<?php

// Simple PHP script to test the updated Pweza API endpoints
$baseUrl = 'http://127.0.0.1:8000/api';

function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            if (isset($data['image'])) {
                // Handle multipart for file upload
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'body' => $response];
}

echo "=== Updated Pweza API Test ===\n\n";

// Test 1: Send OTP
echo "1. Testing OTP sending...\n";
$otpData = [
    'phone_number' => '1234567890'
];

$response = makeRequest($baseUrl . '/auth/send-otp', 'POST', $otpData, [
    'Content-Type: application/json'
]);

echo "Status: " . $response['code'] . "\n";
if ($response['code'] === 200) {
    echo "✅ OTP sent successfully!\n";
    echo "Check the Laravel logs for the OTP code\n\n";
    
    // Test 2: Verify OTP (you'll need to check the logs for the actual OTP)
    echo "2. Testing OTP verification (using 1234 as example)...\n";
    $verifyData = [
        'phone_number' => '1234567890',
        'code' => '1234'  // Replace with actual OTP from logs
    ];
    
    $response = makeRequest($baseUrl . '/auth/verify-otp', 'POST', $verifyData, [
        'Content-Type: application/json'
    ]);
    
    echo "Status: " . $response['code'] . "\n";
    if ($response['code'] === 200) {
        $verifyResponse = json_decode($response['body'], true);
        echo "✅ OTP verification successful!\n";
        $token = $verifyResponse['token'] ?? '';
        echo "Token received: " . substr($token, 0, 20) . "...\n\n";
        
        // Test 3: Get public predictions
        echo "3. Testing public predictions listing...\n";
        $response = makeRequest($baseUrl . '/predictions', 'GET');
        
        echo "Status: " . $response['code'] . "\n";
        if ($response['code'] === 200) {
            echo "✅ Public predictions listed successfully!\n\n";
        } else {
            echo "❌ Failed to list predictions\n";
            echo $response['body'] . "\n\n";
        }
        
        // Test 4: Create prediction (requires tipster role - won't work with seeded user)
        echo "4. Testing prediction creation (may fail due to role)...\n";
        $predictionData = [
            'title' => 'Arsenal vs Chelsea - Over 2.5 Goals',
            'description' => 'Both teams have strong attacking formations',
            'image' => new CURLFile(__DIR__ . '/public/storage/.gitignore', 'text/plain', 'test.txt'), // Using a placeholder file
            'booking_codes' => ['ABC123', 'DEF456'],
            'odds_total' => 1.85,
            'kickoff_at' => '2025-07-26 15:00:00',
            'confidence_level' => 8,
            'is_premium' => false
        ];
        
        $response = makeRequest($baseUrl . '/predictions', 'POST', $predictionData, [
            'Authorization: Bearer ' . $token
        ]);
        
        echo "Status: " . $response['code'] . "\n";
        if ($response['code'] === 201) {
            echo "✅ Prediction created successfully!\n\n";
        } else {
            echo "❌ Failed to create prediction (expected if user is not tipster)\n";
            echo $response['body'] . "\n\n";
        }
        
    } else {
        echo "❌ OTP verification failed (check if OTP code is correct)\n";
        echo $response['body'] . "\n\n";
    }
    
} else {
    echo "❌ OTP sending failed\n";
    echo $response['body'] . "\n\n";
}

// Test 5: Payment initiation
echo "5. Testing payment initiation...\n";
$paymentData = [
    'tipster_id' => '1',
    'plan' => 'daily'
];

// This will fail without authentication, but shows the endpoint
$response = makeRequest($baseUrl . '/payments/initiate', 'POST', $paymentData, [
    'Content-Type: application/json'
]);

echo "Status: " . $response['code'] . "\n";
if ($response['code'] === 401) {
    echo "✅ Payment endpoint is properly protected (401 Unauthorized)\n\n";
} else {
    echo "Response: " . $response['body'] . "\n\n";
}

echo "=== Test Complete ===\n";
echo "\nNotes:\n";
echo "- Check Laravel logs for actual OTP codes\n";
echo "- Update the OTP code in the script before running\n";
echo "- Prediction creation requires tipster role\n";
echo "- Payment endpoints require authentication\n";
