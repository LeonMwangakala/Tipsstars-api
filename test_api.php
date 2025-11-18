<?php

// Simple PHP script to test our API endpoints
$baseUrl = 'http://127.0.0.1:8001/api';

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
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'body' => $response];
}

echo "=== Pweza API Test ===\n\n";

// Test 1: Login as tipster
echo "1. Testing tipster login...\n";
$loginData = [
    'phone_number' => '1234567890',
    'password' => 'password123'
];

$response = makeRequest($baseUrl . '/login', 'POST', $loginData, [
    'Content-Type: application/json'
]);

echo "Status: " . $response['code'] . "\n";
$loginResponse = json_decode($response['body'], true);

if ($response['code'] === 200 && isset($loginResponse['token'])) {
    echo "✅ Login successful!\n";
    $tipsterToken = $loginResponse['token'];
    echo "User: " . $loginResponse['user']['name'] . " (Role: " . $loginResponse['user']['role'] . ")\n\n";
    
    // Test 2: Create a prediction as tipster
    echo "2. Testing prediction creation...\n";
    $predictionData = [
        'title' => 'Arsenal vs Chelsea - Over 2.5 Goals',
        'description' => 'Both teams have strong attacking formations',
        'odds_total' => 1.85,
        'kickoff_at' => '2025-07-26 15:00:00',
        'confidence_level' => 8,
        'is_premium' => false,
        'status' => 'draft',
        'result_status' => 'pending'
    ];
    
    $response = makeRequest($baseUrl . '/predictions', 'POST', $predictionData, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $tipsterToken
    ]);
    
    echo "Status: " . $response['code'] . "\n";
    if ($response['code'] === 201) {
        echo "✅ Prediction created successfully!\n\n";
    } else {
        echo "❌ Failed to create prediction\n";
        echo $response['body'] . "\n\n";
    }
    
} else {
    echo "❌ Login failed\n";
    echo $response['body'] . "\n\n";
}

// Test 3: Login as customer
echo "3. Testing customer login...\n";
$customerLoginData = [
    'phone_number' => '0987654321',
    'password' => 'password123'
];

$response = makeRequest($baseUrl . '/login', 'POST', $customerLoginData, [
    'Content-Type: application/json'
]);

echo "Status: " . $response['code'] . "\n";
$customerLoginResponse = json_decode($response['body'], true);

if ($response['code'] === 200 && isset($customerLoginResponse['token'])) {
    echo "✅ Customer login successful!\n";
    $customerToken = $customerLoginResponse['token'];
    echo "User: " . $customerLoginResponse['user']['name'] . " (Role: " . $customerLoginResponse['user']['role'] . ")\n\n";
    
    // Test 4: List tipsters
    echo "4. Testing tipster listing...\n";
    $response = makeRequest($baseUrl . '/tipsters', 'GET', null, [
        'Authorization: Bearer ' . $customerToken
    ]);
    
    echo "Status: " . $response['code'] . "\n";
    if ($response['code'] === 200) {
        echo "✅ Tipsters listed successfully!\n\n";
    } else {
        echo "❌ Failed to list tipsters\n";
        echo $response['body'] . "\n\n";
    }
    
} else {
    echo "❌ Customer login failed\n";
    echo $response['body'] . "\n\n";
}

echo "=== Test Complete ===\n";
