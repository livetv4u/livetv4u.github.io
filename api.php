<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET');

// 1. Get the playlist URL from the query string
$playlistUrl = isset($_GET['url']) ? $_GET['url'] : '';

if (empty($playlistUrl)) {
    echo json_encode(['error' => 'No playlist URL provided. Use api.php?url=YOUR_URL']);
    exit;
}

// 2. Fetch the M3U content using cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $playlistUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'); 
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$content) {
    echo json_encode(['error' => 'Failed to fetch the M3U file. HTTP Status: ' . $httpCode]);
    exit;
}

// 3. Parse the M3U file content
$lines = explode("\n", $content);
$playlist = [];
$current = [];

foreach ($lines as $line) {
    $line = trim($line);

    if (empty($line)) {
        continue;
    }

    if (strpos($line, '#EXTINF') === 0) {
        // Extract logo
        preg_match('/tvg-logo="([^"]+)"/', $line, $logoMatch);
        
        // Extract channel name (everything after the last comma)
        $commaPos = strrpos($line, ',');
        $name = ($commaPos !== false) ? substr($line, $commaPos + 1) : 'Unknown Channel';

        $current = [
            'name' => trim($name),
            'logo' => isset($logoMatch[1]) ? $logoMatch[1] : ''
        ];
        
    } elseif (strpos($line, '#KODIPROP') === 0 && strpos($line, 'license_key=') !== false) {
        // Extract ClearKey DRM data
        preg_match('/license_key=(.*?):(.*)/', $line, $keyInfo);
        if ($keyInfo) {
            $current['drm'] = 'clearkey';
            $current['keyId'] = trim($keyInfo[1]);
            $current['key'] = trim($keyInfo[2]);
        }
        
    } elseif (strpos($line, '#') !== 0) {
        // Line is a URL, lock in the channel object
        $current['url'] = $line;
        $playlist[] = $current;
        $current = []; // Reset for next channel
    }
}

// 4. Return the parsed array as JSON
echo json_encode($playlist);