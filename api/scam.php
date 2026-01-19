<?php
// File ini harus didecode dari scan.php.b64 setelah clone
// Decode dengan: base64 -d scan.php.b64 > scan.php

header('Content-Type: application/json');

// Decode incoming data
$encrypted = $_POST['data'] ?? '';
$data = json_decode(base64_decode($encrypted), true);

if(!$data || $data['action'] !== 'scan') {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$phone = $data['target'];

// Validasi nomor +62 atau +60
if(!preg_match('/^\+6[02][0-9]{9,13}$/', $phone)) {
    echo json_encode(['error' => 'Invalid number format']);
    exit;
}

// REAL OSINT INTEGRATION
function osint_scan($phone) {
    $results = [];
    
    // Method 1: PhoneInfoga (via local installation)
    $command = escapeshellcmd("python3 /opt/phoneinfoga -n " . escapeshellarg($phone));
    $output = shell_exec($command . " 2>/dev/null");
    
    if($output) {
        // Parse PhoneInfoga output
        if(preg_match('/Carrier:\s*(.+)/i', $output, $match)) {
            $results['carrier'] = trim($match[1]);
        }
        if(preg_match('/Location:\s*(.+)/i', $output, $match)) {
            $results['location'] = trim($match[1]);
        }
    }
    
    // Method 2: External API calls (via Tor)
    $tor_proxy = "socks5://127.0.0.1:9050";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://api.truecaller.com/v1/search");
    curl_setopt($ch, CURLOPT_PROXY, $tor_proxy);
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['phone' => $phone]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $api_result = curl_exec($ch);
    curl_close($ch);
    
    if($api_result) {
        $api_data = json_decode($api_result, true);
        if(isset($api_data['location'])) {
            $results['location'] = $api_data['location'];
            $results['coordinates'] = [
                'lat' => $api_data['lat'] ?? -6.2088,
                'lng' => $api_data['lng'] ?? 106.8456
            ];
        }
    }
    
    return $results;
}

// Execute scan
$scan_results = osint_scan($phone);
$scan_results['phone'] = $phone;
$scan_results['timestamp'] = date('Y-m-d H:i:s');

echo json_encode($scan_results);
?>
