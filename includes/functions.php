<?php
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    // تحقق من رقم الهاتف السعودي
    return preg_match('/^(05|5)(5|0|3|6|4|9|1|8|7|2)([0-9]{7})$/', $phone);
}

function validatePassword($password) {
    // على الأقل 8 أحرف، تحتوي على حرف كبير وحرف صغير ورقم
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}

function generateRandomString($length = 10) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

function generateDiscountCode($task_id) {
    $prefix = 'NQT';
    $random = generateRandomString(6);
    return "$prefix-$task_id-$random";
}

function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '';
    $dateTime = new DateTime($date);
    return $dateTime->format($format);
}

function formatRelativeTime($date) {
    $now = new DateTime();
    $dateTime = new DateTime($date);
    $interval = $now->diff($dateTime);
    
    if ($interval->y > 0) {
        return "قبل {$interval->y} سنة";
    } elseif ($interval->m > 0) {
        return "قبل {$interval->m} شهر";
    } elseif ($interval->d > 0) {
        return "قبل {$interval->d} يوم";
    } elseif ($interval->h > 0) {
        return "قبل {$interval->h} ساعة";
    } elseif ($interval->i > 0) {
        return "قبل {$interval->i} دقيقة";
    } else {
        return "الآن";
    }
}

function formatPoints($points) {
    return number_format($points, 0, '.', ',');
}

function getRatingStars($rating, $max = 5) {
    $stars = '';
    $fullStars = floor($rating);
    $hasHalfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = $max - $fullStars - ($hasHalfStar ? 1 : 0);
    
    for ($i = 0; $i < $fullStars; $i++) {
        $stars .= '<i class="fas fa-star text-warning"></i>';
    }
    
    if ($hasHalfStar) {
        $stars .= '<i class="fas fa-star-half-alt text-warning"></i>';
    }
    
    for ($i = 0; $i < $emptyStars; $i++) {
        $stars .= '<i class="far fa-star text-warning"></i>';
    }
    
    return $stars;
}

function uploadFile($file, $type = 'image', $allowed_types = null) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('حدث خطأ في رفع الملف');
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('حجم الملف أكبر من المسموح به');
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($type === 'image') {
        $allowed = $allowed_types ?: ALLOWED_IMAGE_TYPES;
        if (!in_array($extension, $allowed)) {
            throw new Exception('نوع الملف غير مسموح به');
        }
        
        // التحقق من أن الملف صورة حقيقية
        $image_info = getimagesize($file['tmp_name']);
        if (!$image_info) {
            throw new Exception('الملف ليس صورة صالحة');
        }
    }
    
    // إنشاء اسم فريد للملف
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $upload_path = UPLOADS_PATH . '/' . $type . 's/';
    
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }
    
    $destination = $upload_path . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('فشل في حفظ الملف');
    }
    
    return $filename;
}

function deleteFile($filename, $type = 'image') {
    $file_path = UPLOADS_PATH . '/' . $type . 's/' . $filename;
    
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    
    return false;
}

function getSetting($key, $default = null) {
    global $db;
    
    $sql = "SELECT setting_value FROM design_settings WHERE setting_key = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return $default;
}

function getThemeColors() {
    return [
        'primary' => getSetting('primary_color', '#ff6b35'),
        'secondary' => getSetting('secondary_color', '#2a9d8f'),
        'dark' => getSetting('dark_color', '#264653'),
        'light' => getSetting('light_color', '#f8f9fa')
    ];
}

function sendNotification($user_id, $title, $message, $type = 'info', $link = null) {
    global $db;
    
    $sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("issss", $user_id, $title, $message, $type, $link);
    
    return $stmt->execute();
}

function logActivity($user_id, $action, $description) {
    global $db;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("issss", $user_id, $action, $description, $ip, $user_agent);
    
    return $stmt->execute();
}

function checkPermission($user, $permission) {
    if ($user->getRole() === 'admin') {
        return true;
    }
    
    // يمكن إضافة المزيد من التحقق من الصلاحيات هنا
    return false;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function jsonResponse($status, $message, $data = null) {
    header('Content-Type: application/json');
    
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}
?>