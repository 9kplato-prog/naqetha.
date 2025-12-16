<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

$db = new Database();
$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'login':
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            $user = new User($db->getConnection());
            if ($user->login($email, $password)) {
                $_SESSION['user_id'] = $user->getId();
                $_SESSION['user_role'] = $user->getRole();
                $_SESSION['user_name'] = $user->getName();
                
                $response = [
                    'status' => 'success',
                    'message' => 'تم تسجيل الدخول بنجاح',
                    'data' => [
                        'user_id' => $user->getId(),
                        'name' => $user->getName(),
                        'email' => $user->getEmail(),
                        'role' => $user->getRole(),
                        'points' => $user->getPoints(),
                        'avatar' => $user->getAvatar()
                    ]
                ];
            } else {
                $response['message'] = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
            }
            break;
            
        case 'register':
            $data = [
                'name' => $_POST['firstname'] . ' ' . $_POST['lastname'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'city' => $_POST['city'],
                'birthdate' => $_POST['birthdate'],
                'password' => $_POST['password']
            ];
            
            $user = new User($db->getConnection());
            try {
                if ($user->register($data)) {
                    $response = [
                        'status' => 'success',
                        'message' => 'تم إنشاء الحساب بنجاح',
                        'data' => [
                            'user_id' => $user->getId(),
                            'name' => $user->getName(),
                            'email' => $user->getEmail()
                        ]
                    ];
                }
            } catch (Exception $e) {
                $response['message'] = $e->getMessage();
            }
            break;
            
        case 'forgot-password':
            $email = $_POST['email'] ?? '';
            
            // التحقق من وجود البريد
            $sql = "SELECT id, name FROM users WHERE email = ? AND status = 'active'";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // إنشاء توكن إعادة تعيين
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
                $stmt = $db->getConnection()->prepare($sql);
                $stmt->bind_param("sss", $email, $token, $expires);
                $stmt->execute();
                
                // إرسال البريد الإلكتروني (في الإنتاج)
                $reset_link = BASE_URL . "reset-password?token=$token";
                
                $response = [
                    'status' => 'success',
                    'message' => 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني'
                ];
            } else {
                $response['message'] = 'البريد الإلكتروني غير مسجل';
            }
            break;
            
        case 'reset-password':
            $token = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // التحقق من صحة التوكن
            $sql = "SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $email = $row['email'];
                
                // تحديث كلمة المرور
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE email = ?";
                $stmt = $db->getConnection()->prepare($sql);
                $stmt->bind_param("ss", $hashed_password, $email);
                
                if ($stmt->execute()) {
                    // حذف التوكن المستخدم
                    $sql = "DELETE FROM password_resets WHERE token = ?";
                    $stmt = $db->getConnection()->prepare($sql);
                    $stmt->bind_param("s", $token);
                    $stmt->execute();
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'تم تحديث كلمة المرور بنجاح'
                    ];
                } else {
                    $response['message'] = 'حدث خطأ أثناء تحديث كلمة المرور';
                }
            } else {
                $response['message'] = 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية';
            }
            break;
            
        case 'check-email':
            $email = $_POST['email'] ?? '';
            
            $sql = "SELECT id FROM users WHERE email = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $response = [
                'status' => 'success',
                'available' => $result->num_rows === 0
            ];
            break;
    }
}

echo json_encode($response);
?>