<?php
// التحقق من صلاحيات صاحب المطعم
if (!isset($user) || $user->getRole() !== 'restaurant_owner') {
    header('Location: /login');
    exit;
}

$page_title = 'مراسلة الإدارة';
include 'includes/header.php';

// الحصول على مطعم المستخدم
$sql = "SELECT id, name FROM restaurants WHERE owner_id = ? LIMIT 1";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();
$restaurant = $result->fetch_assoc();

// إرسال رسالة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);
    
    // الحصول على جميع الأدمن
    $admin_sql = "SELECT id FROM users WHERE role = 'admin'";
    $admin_result = $db->getConnection()->query($admin_sql);
    
    $success_count = 0;
    while ($admin = $admin_result->fetch_assoc()) {
        $insert_sql = "INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) 
                      VALUES (?, ?, ?, ?, NOW())";
        $insert_stmt = $db->getConnection()->prepare($insert_sql);
        $insert_stmt->bind_param("iiss", $user->getId(), $admin['id'], $subject, $message);
        
        if ($insert_stmt->execute()) {
            $success_count++;
        }
    }
    
    if ($success_count > 0) {
        $success = 'تم إرسال الرسالة للإدارة بنجاح';
    } else {
        $error = 'حدث خطأ أثناء إرسال الرسالة';
    }
}

// الحصول على الرسائل المرسلة
$sent_messages = [];
$sql = "SELECT m.*, u.name as receiver_name 
        FROM messages m 
        JOIN users u ON m.receiver_id = u.id 
        WHERE m.sender_id = ? AND m.sender_deleted = 0 
        ORDER BY m.created_at DESC";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sent_messages[] = $row;
}

// الحصول على الرسائل الواردة
$received_messages = [];
$sql = "SELECT m.*, u.name as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.receiver_id = ? AND m.receiver_deleted = 0 
        ORDER BY m.created_at DESC";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $received_messages[] = $row;
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-comments"></i> مراسلة الإدارة</h2>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-4">
            <!-- إرسال رسالة جديدة -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">إرسال رسالة للإدارة</h5>
                    <p class="card-text">أرسل استفسارك أو اقتراحك لإدارة المنصة</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="subject" class="form-label">الموضوع</label>
                            <select class="form-control" id="subject" name="subject" required>
                                <option value="">اختر موضوع الرسالة</option>
                                <option value="استفسار عام">استفسار عام</option>
                                <option value="مشكلة فنية">مشكلة فنية</option>
                                <option value="اقتراح تحسين">اقتراح تحسين</option>
                                <option value="تحديث بيانات المطعم">تحديث بيانات المطعم</option>
                                <option value="طلب دعم فني">طلب دعم فني</option>
                                <option value="شكوى">شكوى</option>
                                <option value="آخر">آخر</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="custom_subject" class="form-label">أو اكتب موضوع مخصص</label>
                            <input type="text" class="form-control" id="custom_subject" name="custom_subject" placeholder="اكتب موضوع الرسالة">
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">الرسالة <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="5" required placeholder="اكتب رسالتك هنا..."></textarea>
                        </div>
                        
                        <button type="submit" name="send_message" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> إرسال الرسالة
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- معلومات الاتصال -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">معلومات الاتصال</h5>
                    <div class="contact-info">
                        <div class="contact-item mb-3">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <strong>البريد الإلكتروني</strong>
                                <p>admin@nuqtaha.com</p>
                            </div>
                        </div>
                        
                        <div class="contact-item mb-3">
                            <i class="fas fa-phone"></i>
                            <div>
                                <strong>رقم الهاتف</strong>
                                <p>+966 500 000 000</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong>ساعات العمل</strong>
                                <p>24/7</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- الرسائل الواردة -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">الرسائل الواردة</h5>
                        <span class="badge bg-primary"><?php echo count($received_messages); ?> رسالة</span>
                    </div>
                    
                    <?php if (count($received_messages) > 0): ?>
                        <div class="messages-list">
                            <?php foreach ($received_messages as $message): ?>
                            <div class="message-item p-3 border rounded mb-3 <?php echo !$message['is_read'] ? 'unread' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong><?php echo $message['sender_name']; ?></strong>
                                        <span class="badge bg-secondary ms-2">إدارة المنصة</span>
                                    </div>
                                    <small class="text-muted"><?php echo formatRelativeTime($message['created_at']); ?></small>
                                </div>
                                
                                <h6 class="mb-2"><?php echo $message['subject']; ?></h6>
                                <p class="mb-3"><?php echo nl2br($message['message']); ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <?php if (!$message['is_read']): ?>
                                    <button class="btn btn-sm btn-success" onclick="markAsRead(<?php echo $message['id']; ?>)">
                                        <i class="fas fa-check"></i> تمت القراءة
                                    </button>
                                    <?php else: ?>
                                    <small class="text-muted">تمت القراءة في <?php echo date('Y-m-d H:i', strtotime($message['read_at'])); ?></small>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-danger" onclick="deleteMessage(<?php echo $message['id']; ?>, 'received')">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                            <p>لا توجد رسائل واردة</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- الرسائل المرسلة -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">الرسائل المرسلة</h5>
                        <span class="badge bg-success"><?php echo count($sent_messages); ?> رسالة</span>
                    </div>
                    
                    <?php if (count($sent_messages) > 0): ?>
                        <div class="messages-list">
                            <?php foreach ($sent_messages as $message): ?>
                            <div class="message-item p-3 border rounded mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong>إلى: <?php echo $message['receiver_name']; ?></strong>
                                    </div>
                                    <small class="text-muted"><?php echo formatRelativeTime($message['created_at']); ?></small>
                                </div>
                                
                                <h6 class="mb-2"><?php echo $message['subject']; ?></h6>
                                <p class="mb-3"><?php echo nl2br($message['message']); ?></p>
                                
                                <div class="text-end">
                                    <button class="btn btn-sm btn-danger" onclick="deleteMessage(<?php echo $message['id']; ?>, 'sent')">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-paper-plane fa-2x text-muted mb-3"></i>
                            <p>لم ترسل أي رسائل بعد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تحديد الموضوع تلقائياً عند الكتابة
document.getElementById('custom_subject').addEventListener('input', function() {
    if (this.value.trim() !== '') {
        document.getElementById('subject').value = 'آخر';
    }
});

// تمييز الرسالة كمقروءة
function markAsRead(messageId) {
    fetch('/api/owner?action=mark-as-read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `message_id=${messageId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showNotification('نجاح', 'تم تمييز الرسالة كمقروءة');
            setTimeout(() => location.reload(), 1000);
        }
    });
}

// حذف الرسالة
function deleteMessage(messageId, type) {
    if (confirm('هل أنت متأكد من حذف هذه الرسالة؟')) {
        fetch('/api/owner?action=delete-message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `message_id=${messageId}&type=${type}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification('نجاح', 'تم حذف الرسالة بنجاح');
                setTimeout(() => location.reload(), 1000);
            }
        });
    }
}
</script>

<style>
.message-item {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.message-item:hover {
    border-color: var(--primary-color);
}

.message-item.unread {
    border-left: 4px solid var(--primary-color);
    background-color: rgba(var(--primary-color-rgb), 0.05);
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 15px;
}

.contact-item i {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
}

.contact-item strong {
    display: block;
    margin-bottom: 5px;
}

.contact-item p {
    margin: 0;
    color: var(--gray-color);
}
</style>

<?php
include 'includes/footer.php';
?>