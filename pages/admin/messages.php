<?php
// التحقق من صلاحيات الأدمن
if (!isset($user) || $user->getRole() !== 'admin') {
    header('Location: /login');
    exit;
}

$page_title = 'المراسلات';
$page_scripts = ['admin-messages.js'];

include 'includes/header.php';

// معالجة الطلبات
$action = $_GET['action'] ?? '';
$message_id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'send':
            $receiver_id = $_POST['receiver_id'];
            $subject = $_POST['subject'];
            $message = $_POST['message'];
            
            $sql = "INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("iiss", $user->getId(), $receiver_id, $subject, $message);
            
            if ($stmt->execute()) {
                $success = 'تم إرسال الرسالة بنجاح';
            } else {
                $error = 'حدث خطأ أثناء إرسال الرسالة';
            }
            break;
            
        case 'delete':
            $type = $_GET['type'] ?? 'sent'; // sent أو received
            
            if ($type === 'sent') {
                $sql = "UPDATE messages SET sender_deleted = 1 WHERE id = ? AND sender_id = ?";
            } else {
                $sql = "UPDATE messages SET receiver_deleted = 1 WHERE id = ? AND receiver_id = ?";
            }
            
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("ii", $message_id, $user->getId());
            
            if ($stmt->execute()) {
                $success = 'تم حذف الرسالة بنجاح';
            } else {
                $error = 'حدث خطأ أثناء حذف الرسالة';
            }
            break;
            
        case 'mark_read':
            $sql = "UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ? AND receiver_id = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("ii", $message_id, $user->getId());
            $stmt->execute();
            break;
    }
}

// تحديد نوع الرسائل
$type = $_GET['type'] ?? 'inbox'; // inbox, sent, unread
$search = $_GET['search'] ?? '';

// الحصول على الرسائل
$where_clause = "WHERE ";
if ($type === 'inbox') {
    $where_clause .= "receiver_id = ? AND receiver_deleted = 0";
} elseif ($type === 'sent') {
    $where_clause .= "sender_id = ? AND sender_deleted = 0";
} elseif ($type === 'unread') {
    $where_clause .= "receiver_id = ? AND is_read = 0 AND receiver_deleted = 0";
}

if ($search) {
    $where_clause .= " AND (subject LIKE ? OR message LIKE ?)";
}

$sql = "SELECT m.*, 
               s.name as sender_name, s.email as sender_email,
               r.name as receiver_name, r.email as receiver_email
        FROM messages m
        JOIN users s ON m.sender_id = s.id
        JOIN users r ON m.receiver_id = r.id
        $where_clause
        ORDER BY m.created_at DESC";

$stmt = $db->getConnection()->prepare($sql);
if ($search) {
    $search_term = "%$search%";
    if ($type === 'inbox' || $type === 'unread') {
        $stmt->bind_param("iss", $user->getId(), $search_term, $search_term);
    } else {
        $stmt->bind_param("iss", $user->getId(), $search_term, $search_term);
    }
} else {
    $stmt->bind_param("i", $user->getId());
}

$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

// الحصول على عدد الرسائل غير المقروءة
$sql = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0 AND receiver_deleted = 0";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$unread_result = $stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['unread_count'];
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-comments"></i> المراسلات</h2>
    
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
        <!-- الشريط الجانبي -->
        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <button class="btn btn-primary w-100 mb-3" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                        <i class="fas fa-edit"></i> رسالة جديدة
                    </button>
                    
                    <div class="list-group">
                        <a href="/admin/messages?type=inbox" class="list-group-item list-group-item-action <?php echo $type === 'inbox' ? 'active' : ''; ?>">
                            <i class="fas fa-inbox me-2"></i> الوارد
                            <?php if ($unread_count > 0 && $type !== 'inbox'): ?>
                            <span class="badge bg-danger float-end"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/admin/messages?type=sent" class="list-group-item list-group-item-action <?php echo $type === 'sent' ? 'active' : ''; ?>">
                            <i class="fas fa-paper-plane me-2"></i> المرسلة
                        </a>
                        <a href="/admin/messages?type=unread" class="list-group-item list-group-item-action <?php echo $type === 'unread' ? 'active' : ''; ?>">
                            <i class="fas fa-envelope me-2"></i> غير المقروءة
                            <?php if ($unread_count > 0 && $type !== 'unread'): ?>
                            <span class="badge bg-danger float-end"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <!-- جهات الاتصال -->
                    <div class="mt-4">
                        <h6>جهات الاتصال</h6>
                        <div id="contactsList">
                            <!-- سيتم تحميل جهات الاتصال هنا ديناميكياً -->
                            <div class="text-center">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">جاري التحميل...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- قائمة الرسائل -->
        <div class="col-lg-9">
            <!-- بحث -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <input type="hidden" name="type" value="<?php echo $type; ?>">
                        <div class="col-md-10">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="ابحث في الرسائل..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger w-100" onclick="deleteSelected()">
                                <i class="fas fa-trash"></i> حذف
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- الرسائل -->
            <div class="card">
                <div class="card-body">
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $message): ?>
                        <div class="message-item border-bottom pb-3 mb-3 <?php echo !$message['is_read'] ? 'unread-message' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="form-check me-3">
                                    <input class="form-check-input message-checkbox" type="checkbox" value="<?php echo $message['id']; ?>">
                                </div>
                                
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php if ($type === 'inbox'): ?>
                                                <strong><?php echo $message['sender_name']; ?></strong>
                                                <?php else: ?>
                                                <strong><?php echo $message['receiver_name']; ?></strong>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-1">
                                                <?php if ($message['subject']): ?>
                                                <strong><?php echo $message['subject']; ?></strong> - 
                                                <?php endif; ?>
                                                <?php echo substr($message['message'], 0, 100); ?>...
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted"><?php echo formatRelativeTime($message['created_at']); ?></small>
                                            <br>
                                            <?php if (!$message['is_read'] && $type === 'inbox'): ?>
                                            <span class="badge bg-success">جديد</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-info" onclick="viewMessage(<?php echo $message['id']; ?>)">
                                            <i class="fas fa-eye"></i> عرض
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteMessage(<?php echo $message['id']; ?>, '<?php echo $type; ?>')">
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-envelope-open fa-2x text-muted mb-3"></i>
                            <p>لا توجد رسائل</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال رسالة جديدة -->
<div class="modal fade" id="newMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">رسالة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="newMessageForm">
                    <input type="hidden" name="action" value="send">
                    
                    <div class="mb-3">
                        <label for="receiver_id" class="form-label">إلى <span class="text-danger">*</span></label>
                        <select class="form-control" id="receiver_id" name="receiver_id" required>
                            <option value="">اختر المستلم</option>
                            <!-- سيتم تحميل المستخدمين هنا ديناميكياً -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">الموضوع (اختياري)</label>
                        <input type="text" class="form-control" id="subject" name="subject">
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">الرسالة <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="message" name="message" rows="8" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="newMessageForm" class="btn btn-primary">إرسال</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال عرض الرسالة -->
<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">الرسالة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="messageDetails">
                    <!-- سيتم تحميل الرسالة هنا ديناميكياً -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-primary" id="replyBtn" onclick="replyToMessage()">
                    <i class="fas fa-reply"></i> رد
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// تحميل جهات الاتصال عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
    loadContacts();
    loadUsersForNewMessage();
});

// تحميل جهات الاتصال
async function loadContacts() {
    try {
        const response = await fetch('/api/admin?action=get-contacts');
        const data = await response.json();
        
        if (data.status === 'success') {
            let html = '';
            
            data.data.forEach(contact => {
                html += `
                    <div class="contact-item d-flex align-items-center mb-2">
                        <div class="user-avatar me-2">
                            ${contact.name.charAt(0)}
                        </div>
                        <div style="flex: 1;">
                            <strong>${contact.name}</strong>
                            <br>
                            <small class="text-muted">${contact.role_name}</small>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="sendToUser(${contact.id})">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                `;
            });
            
            document.getElementById('contactsList').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading contacts:', error);
    }
}

// تحميل المستخدمين للرسالة الجديدة
async function loadUsersForNewMessage() {
    try {
        const response = await fetch('/api/admin?action=get-users-for-message');
        const data = await response.json();
        
        if (data.status === 'success') {
            const select = document.getElementById('receiver_id');
            data.data.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.name} (${user.role_name})`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// إرسال رسالة إلى مستخدم معين
function sendToUser(userId) {
    document.getElementById('receiver_id').value = userId;
    const modal = new bootstrap.Modal(document.getElementById('newMessageModal'));
    modal.show();
}

// عرض الرسالة
async function viewMessage(messageId) {
    try {
        const response = await fetch(`/api/admin?action=get-message&id=${messageId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const message = data.data;
            
            let html = `
                <div class="message-details">
                    <div class="message-header mb-4 p-3 border-bottom">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>من:</strong> ${message.sender_name} (${message.sender_email})</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>إلى:</strong> ${message.receiver_name} (${message.receiver_email})</p>
                            </div>
                            <div class="col-md-12">
                                <p><strong>التاريخ:</strong> ${message.created_at}</p>
                            </div>
                            ${message.subject ? `
                            <div class="col-md-12">
                                <p><strong>الموضوع:</strong> ${message.subject}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="message-body p-3">
                        <h6>الرسالة:</h6>
                        <div class="message-content p-3 bg-light rounded">
                            ${message.message.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('messageDetails').innerHTML = html;
            
            // تخزين بيانات المرسل للرد
            document.getElementById('replyBtn').setAttribute('data-sender-id', message.sender_id);
            document.getElementById('replyBtn').setAttribute('data-sender-name', message.sender_name);
            
            // تعليم الرسالة كمقروءة
            markAsRead(messageId);
            
            const modal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading message:', error);
        showNotification('خطأ', 'حدث خطأ في تح