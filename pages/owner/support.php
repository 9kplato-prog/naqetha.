<?php
// التحقق من صلاحيات صاحب المطعم
if (!isset($user) || $user->getRole() !== 'restaurant_owner') {
    header('Location: /login');
    exit;
}

$page_title = 'الدعم الفني';
include 'includes/header.php';

// إرسال تذكرة دعم جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject = sanitizeInput($_POST['subject']);
    $category = sanitizeInput($_POST['category']);
    $priority = sanitizeInput($_POST['priority']);
    $message = sanitizeInput($_POST['message']);
    
    $sql = "INSERT INTO support_tickets (user_id, subject, category, priority, message, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'open', NOW())";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("issss", $user->getId(), $subject, $category, $priority, $message);
    
    if ($stmt->execute()) {
        $ticket_id = $stmt->insert_id;
        $success = 'تم إرسال تذكرتك بنجاح. رقم التذكرة: #' . str_pad($ticket_id, 6, '0', STR_PAD_LEFT);
    } else {
        $error = 'حدث خطأ أثناء إرسال التذكرة';
    }
}

// الحصول على تذاكر الدعم السابقة
$tickets = [];
$sql = "SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-headset"></i> الدعم الفني</h2>
    
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
        <div class="col-lg-8">
            <!-- إرسال تذكرة جديدة -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">إرسال تذكرة دعم جديدة</h5>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="subject" class="form-label">عنوان التذكرة <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="subject" name="subject" required 
                                       placeholder="مثال: مشكلة في إضافة مطعم">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">التصنيف <span class="text-danger">*</span></label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">اختر التصنيف</option>
                                    <option value="technical">مشكلة فنية</option>
                                    <option value="account">مشكلة في الحساب</option>
                                    <option value="restaurant">مشكلة في المطعم</option>
                                    <option value="payment">مشكلة في الدفع</option>
                                    <option value="feature">طلب ميزة جديدة</option>
                                    <option value="other">آخر</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="priority" class="form-label">أولوية المشكلة</label>
                            <select class="form-control" id="priority" name="priority">
                                <option value="low">منخفضة</option>
                                <option value="medium" selected>متوسطة</option>
                                <option value="high">عالية</option>
                                <option value="urgent">عاجلة</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">وصف المشكلة <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="6" required 
                                      placeholder="صف مشكلتك بالتفصيل..."></textarea>
                            <div class="form-text">أرفق أية رسائل خطأ أو صور توضح المشكلة إن أمكن</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="attachment" class="form-label">إرفاق ملف (اختياري)</label>
                            <input type="file" class="form-control" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                            <small class="text-muted">الحد الأقصى: 5MB، الأنواع المسموحة: صور، PDF، مستندات</small>
                        </div>
                        
                        <button type="submit" name="submit_ticket" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> إرسال التذكرة
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- تذاكر الدعم السابقة -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">تذاكر الدعم السابقة</h5>
                        <span class="badge bg-primary"><?php echo count($tickets); ?> تذكرة</span>
                    </div>
                    
                    <?php if (count($tickets) > 0): ?>
                        <div class="tickets-list">
                            <?php foreach ($tickets as $ticket): 
                                $priority_badge = [
                                    'low' => 'secondary',
                                    'medium' => 'info',
                                    'high' => 'warning',
                                    'urgent' => 'danger'
                                ];
                                
                                $status_badge = [
                                    'open' => 'success',
                                    'in_progress' => 'primary',
                                    'resolved' => 'info',
                                    'closed' => 'secondary'
                                ];
                            ?>
                            <div class="ticket-item p-3 border rounded mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1"><?php echo $ticket['subject']; ?></h6>
                                        <div class="d-flex gap-2">
                                            <span class="badge bg-<?php echo $priority_badge[$ticket['priority']]; ?>">
                                                <?php 
                                                $priority_names = [
                                                    'low' => 'منخفضة',
                                                    'medium' => 'متوسطة',
                                                    'high' => 'عالية',
                                                    'urgent' => 'عاجلة'
                                                ];
                                                echo $priority_names[$ticket['priority']];
                                                ?>
                                            </span>
                                            <span class="badge bg-<?php echo $status_badge[$ticket['status']]; ?>">
                                                <?php 
                                                $status_names = [
                                                    'open' => 'مفتوحة',
                                                    'in_progress' => 'قيد المعالجة',
                                                    'resolved' => 'تم الحل',
                                                    'closed' => 'مغلقة'
                                                ];
                                                echo $status_names[$ticket['status']];
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo formatRelativeTime($ticket['created_at']); ?></small>
                                </div>
                                
                                <p class="mb-3"><?php echo substr($ticket['message'], 0, 150); ?>...</p>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        رقم التذكرة: #<?php echo str_pad($ticket['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </small>
                                    
                                    <button class="btn btn-sm btn-primary" onclick="viewTicket(<?php echo $ticket['id']; ?>)">
                                        <i class="fas fa-eye"></i> عرض التفاصيل
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ticket-alt fa-2x text-muted mb-3"></i>
                            <p>لا توجد تذاكر دعم سابقة</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- معلومات الدعم -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">معلومات الدعم</h5>
                    
                    <div class="support-info">
                        <div class="support-item mb-3">
                            <div class="support-icon bg-primary">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="support-content">
                                <h6>ساعات العمل</h6>
                                <p>24/7</p>
                            </div>
                        </div>
                        
                        <div class="support-item mb-3">
                            <div class="support-icon bg-success">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="support-content">
                                <h6>رقم الدعم</h6>
                                <p>+966 500 000 000</p>
                            </div>
                        </div>
                        
                        <div class="support-item mb-3">
                            <div class="support-icon bg-warning">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="support-content">
                                <h6>البريد الإلكتروني</h6>
                                <p>support@nuqtaha.com</p>
                            </div>
                        </div>
                        
                        <div class="support-item">
                            <div class="support-icon bg-info">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="support-content">
                                <h6>الدردشة المباشرة</h6>
                                <p>متاحة خلال ساعات العمل</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- الأسئلة الشائعة -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">أسئلة شائعة</h5>
                    
                    <div class="faq-list">
                        <div class="faq-item mb-3">
                            <h6>كم تستغرق معالجة التذكرة؟</h6>
                            <p class="small text-muted">تذاكر الأولوية العالية: 24 ساعة، تذاكر أخرى: 2-3 أيام عمل</p>
                        </div>
                        
                        <div class="faq-item mb-3">
                            <h6>كيف أتابع حالة تذكرتي؟</h6>
                            <p class="small text-muted">يمكنك متابعة حالة تذكرتك من خلال صفحة التذاكر أو عبر البريد الإلكتروني</p>
                        </div>
                        
                        <div class="faq-item mb-3">
                            <h6>هل يمكنني إلغاء تذكرتي؟</h6>
                            <p class="small text-muted">نعم، يمكنك إلغاء التذكرة ما دامت لم يتم البدء في معالجتها</p>
                        </div>
                        
                        <div class="faq-item">
                            <h6>ماذا أفعل إذا نسيت رقم التذكرة؟</h6>
                            <p class="small text-muted">تواصل مع الدعم عبر البريد الإلكتروني وسنساعدك في العثور على تذكرتك</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- نصائح سريعة -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">نصائح سريعة</h5>
                    
                    <div class="tips-list">
                        <div class="tip-item mb-2">
                            <i class="fas fa-lightbulb text-warning"></i>
                            <span>صف المشكلة بدقة</span>
                        </div>
                        
                        <div class="tip-item mb-2">
                            <i class="fas fa-lightbulb text-warning"></i>
                            <span>أرفق صوراً للرسائل الخطأ</span>
                        </div>
                        
                        <div class="tip-item mb-2">
                            <i class="fas fa-lightbulb text-warning"></i>
                            <span>اذكر رقم المطعم إذا كان متعلقاً به</span>
                        </div>
                        
                        <div class="tip-item">
                            <i class="fas fa-lightbulb text-warning"></i>
                            <span>استخدم أولوية مناسبة للمشكلة</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال عرض التذكرة -->
<div class="modal fade" id="ticketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تفاصيل التذكرة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="ticketDetails">
                    <!-- سيتم تحميل تفاصيل التذكرة هنا ديناميكياً -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<script>
// عرض تفاصيل التذكرة
function viewTicket(ticketId) {
    fetch(`/api/owner?action=get-ticket&ticket_id=${ticketId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const ticket = data.data;
                
                let html = `
                    <div class="ticket-details">
                        <div class="ticket-header mb-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4>${ticket.subject}</h4>
                                    <div class="d-flex gap-2 mb-2">
                                        <span class="badge bg-${getPriorityBadge(ticket.priority)}">
                                            ${getPriorityName(ticket.priority)}
                                        </span>
                                        <span class="badge bg-${getStatusBadge(ticket.status)}">
                                            ${getStatusName(ticket.status)}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">رقم التذكرة</small>
                                    <strong>#${ticket.id.toString().padStart(6, '0')}</strong>
                                </div>
                            </div>
                            
                            <div class="ticket-meta mt-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">التصنيف</small>
                                        <strong>${getCategoryName(ticket.category)}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">تاريخ الإرسال</small>
                                        <strong>${formatDate(ticket.created_at)}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">آخر تحديث</small>
                                        <strong>${ticket.updated_at ? formatDate(ticket.updated_at) : 'لا يوجد'}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ticket-message mb-4">
                            <h6>وصف المشكلة:</h6>
                            <div class="message-box p-3 bg-light rounded">
                                <p class="mb-0">${ticket.message}</p>
                            </div>
                        </div>
                `;
                
                if (ticket.replies && ticket.replies.length > 0) {
                    html += `
                        <div class="ticket-replies mb-4">
                            <h6>الردود:</h6>
                            <div class="replies-list">
                    `;
                    
                    ticket.replies.forEach(reply => {
                        html += `
                            <div class="reply-item p-3 border rounded mb-3 ${reply.is_admin ? 'admin-reply' : ''}">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong>${reply.is_admin ? 'فريق الدعم' : 'أنت'}</strong>
                                        ${reply.is_admin ? '<span class="badge bg-primary ms-2">دعم فني</span>' : ''}
                                    </div>
                                    <small class="text-muted">${formatDate(reply.created_at)}</small>
                                </div>
                                <p class="mb-0">${reply.message}</p>
                            </div>
                        `;
                    });
                    
                    html += `
                            </div>
                        </div>
                    `;
                }
                
                if (ticket.status !== 'closed') {
                    html += `
                        <div class="ticket-reply-form">
                            <h6>إضافة رد:</h6>
                            <form id="replyForm">
                                <input type="hidden" name="ticket_id" value="${ticket.id}">
                                <div class="mb-3">
                                    <textarea class="form-control" id="reply_message" rows="3" 
                                              placeholder="اكتب ردك هنا..."></textarea>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="submitReply(${ticket.id})">
                                    <i class="fas fa-reply"></i> إرسال الرد
                                </button>
                            </form>
                        </div>
                    `;
                }
                
                html += `</div>`;
                
                document.getElementById('ticketDetails').innerHTML = html;
                
                const modal = new bootstrap.Modal(document.getElementById('ticketModal'));
                modal.show();
            }
        })
        .catch(error => {
            console.error('Error loading ticket:', error);
            showNotification('خطأ', 'حدث خطأ في تحميل تفاصيل التذكرة', 'error');
        });
}

// إرسال رد على التذكرة
function submitReply(ticketId) {
    const message = document.getElementById('reply_message').value;
    
    if (!message.trim()) {
        showNotification('خطأ', 'يرجى كتابة الرد', 'error');
        return;
    }
    
    fetch('/api/owner?action=reply-to-ticket', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ticket_id=${ticketId}&message=${encodeURIComponent(message)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showNotification('نجاح', 'تم إرسال الرد بنجاح');
            viewTicket(ticketId); // تحديث عرض التذكرة
        } else {
            showNotification('خطأ', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting reply:', error);
        showNotification('خطأ', 'حدث خطأ أثناء إرسال الرد', 'error');
    });
}

// دوال المساعدة
function getPriorityBadge(priority) {
    const badges = {
        'low': 'secondary',
        'medium': 'info',
        'high': 'warning',
        'urgent': 'danger'
    };
    return badges[priority] || 'secondary';
}

function getPriorityName(priority) {
    const names = {
        'low': 'منخفضة',
        'medium': 'متوسطة',
        'high': 'عالية',
        'urgent': 'عاجلة'
    };
    return names[priority] || 'متوسطة';
}

function getStatusBadge(status) {
    const badges = {
        'open': 'success',
        'in_progress': 'primary',
        'resolved': 'info',
        'closed': 'secondary'
    };
    return badges[status] || 'secondary';
}

function getStatusName(status) {
    const names = {
        'open': 'مفتوحة',
        'in_progress': 'قيد المعالجة',
        'resolved': 'تم الحل',
        'closed': 'مغلقة'
    };
    return names[status] || 'مفتوحة';
}

function getCategoryName(category) {
    const names = {
        'technical': 'مشكلة فنية',
        'account': 'مشكلة في الحساب',
        'restaurant': 'مشكلة في المطعم',
        'payment': 'مشكلة في الدفع',
        'feature': 'طلب ميزة جديدة',
        'other': 'آخر'
    };
    return names[category] || 'آخر';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-SA') + ' ' + date.toLocaleTimeString('ar-SA');
}
</script>

<style>
.support-item {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.support-item:last-child {
    margin-bottom: 0;
}

.support-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.support-content h6 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.support-content p {
    margin: 0;
    color: var(--gray-color);
}

.faq-item {
    padding: 15px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background-color: var(--card-bg);
}

.faq-item h6 {
    margin-bottom: 5px;
    color: var(--primary-color);
}

.tips-list {
    padding: 0;
    list-style: none;
}

.tip-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: var(--border-radius);
    background-color: var(--light-color);
    margin-bottom: 10px;
}

.tip-item:last-child {
    margin-bottom: 0;
}

.ticket-item {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.ticket-item:hover {
    border-color: var(--primary-color);
    transform: translateX(-5px);
}

.ticket-meta {
    background-color: var(--light-color);
    padding: 15px;
    border-radius: var(--border-radius);
}

.message-box {
    background-color: var(--light-color);
    border-right: 3px solid var(--primary-color);
}

.reply-item {
    background-color: var(--card-bg);
}

.reply-item.admin-reply {
    background-color: rgba(var(--primary-color-rgb), 0.05);
    border-right: 3px solid var(--primary-color);
}
</style>

<?php
include 'includes/footer.php';
?>