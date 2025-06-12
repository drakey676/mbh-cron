<?php
// Set zona waktu ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi database MySQL InfinityFree
$host = "sql207.yzz.me";
$user = "yzzme_39213576";
$pass = "kangen123";
$db   = "yzzme_39213576_shopee_order";

// Koneksi
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Konfigurasi Shopee
$partner_id = 2011439;
$partner_key = "6e596e70587146797a48715667626b57504d5279435951495841586e6b517865";
$shop_id = 629000745;

$getToken = $conn->prepare("SELECT refresh_token FROM shopee_tokens WHERE shop_id = ?");
$getToken->bind_param("i", $shop_id);
$getToken->execute();
$getResult = $getToken->get_result();

if ($getResult->num_rows > 0) {
    $row = $getResult->fetch_assoc();
    $refresh_token = $row['refresh_token'];
} else {
    die("❌ Refresh token tidak ditemukan di database.");
}


$timestamp = time(); // tetap pakai time() UTC untuk Shopee
$path = "/api/v2/auth/access_token/get";
$base_string = "$partner_id$path$timestamp";
$signature = hash_hmac('sha256', $base_string, $partner_key, false);

$url = "https://partner.shopeemobile.com/api/v2/auth/access_token/get?partner_id=$partner_id&timestamp=$timestamp&sign=$signature";

// Body POST
$data = [
    "partner_id" => $partner_id,
    "refresh_token" => $refresh_token,
    "shop_id" => 629000745
];

// CURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
$result = curl_exec($ch);
curl_close($ch);

// Decode respons
$response = json_decode($result, true);

// Jika berhasil
if (isset($response['access_token'])) {
    $access_token = $response['access_token'];
    $refresh_token = $response['refresh_token'];
    $shop_id = $response['shop_id'];
    $expire_in = $response['expire_in'];

    // Hitung waktu lokal
    $created_at = date("Y-m-d H:i:s"); // sekarang (WIB)
    $expire_at = date("Y-m-d H:i:s", time() + $expire_in); // waktu kedaluwarsa (WIB)

    // Cek apakah data shop_id sudah ada
    $check = $conn->prepare("SELECT shop_id FROM shopee_tokens WHERE shop_id = ?");
    $check->bind_param("i", $shop_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE shopee_tokens SET access_token=?, refresh_token=?, expire_at=?, created_at=? WHERE shop_id=?");
        $stmt->bind_param("ssssi", $access_token, $refresh_token, $expire_at, $created_at, $shop_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO shopee_tokens (shop_id, access_token, refresh_token, expire_at, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $shop_id, $access_token, $refresh_token, $expire_at, $created_at);
    }

    if ($stmt->execute()) {
        echo "✅ Token berhasil disimpan ke database (Waktu Jakarta).";
    } else {
        echo "❌ Gagal menyimpan ke database: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "❌ Error dari Shopee: " . json_encode($response);
}

$conn->close();
