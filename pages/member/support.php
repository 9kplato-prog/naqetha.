<?php
// التحقق من صلاحيات العضو
if (!isset($user) || !in_array($user->getRole(), ['member', 'moderator'])) {
    header('Location: /login');
    exit;
}

$page_title = 'الدعم الفني';
$page_scripts = ['support.js'];

include 'includes/header.php';

// معالجة إرسال تذكرة الدعم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $priority = $_POST['priority'];
    
    try {
        // إدراج تذكرة الدعم
        $sql = "INSERT INTO support_tickets (user_id, subject, message, priority, status, created_at) 
                VALUES (?, ?, ?, ?, 'open', NOW())";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("isss", $user->getId(), $subject, $message, $priority);
        
        if ($stmt->execute()) {
            $ticket_id = $stmt->insert_id;
            $success = "تم إنشاء تذكرة الدعم رقم #{$ticket_id} بنجاح";
            
            // إرسال إشعار للأدمن
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, link) 
                                SELECT id, 'تذكرة دعم جديدة', 'تم إنشاء تذكرة دعم جديدة من قبل عضو', 'info', '/admin/support/tickets' 
                                FROM users WHERE role = 'admin'";
            $db->getConnection()->query($notification_sql);
        } else {
            $error = "حدث خطأ أثناء إنشاء تذكرة الدعم";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// الحصول على تذاكر الدعم السابقة للمستخدم
$sql = "SELECT * FROM support_tickets 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();

$tickets = [];
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
        <!-- نموذج إرسال تذكرة -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">إرسال تذكرة دعم</h5>
                    
                    <form method="POST" id="supportForm">
                        <div class="mb-3">
                            <label for="subject" class="form-label">عنوان المشكلة <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
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
                            <label for="message" class="form-label">تفاصيل المشكلة <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                            <div class="form-text">يرجى وصف المشكلة بالتفصيل لتقديم أفضل مساعدة</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">نوع المشكلة</label>
                            <div class="problem-types">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="problem_technical" value="technical">
                                    <label class="form-check-label" for="problem_technical">مشكلة تقنية</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="problem_account" value="account">
                                    <label class="form-check-label" for="problem_account">مشكلة في الحساب</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="problem_points" value="points">
                                    <label class="form-check-label" for="problem_points">مشكلة في النقاط</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="problem_task" value="task">
                                    <label class="form-check-label" for="problem_task">مشكلة في المهام</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="problem_other" value="other">
                                    <label class="form-check-label" for="problem_other">أخرى</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="attachments" class="form-label">مرفقات (اختياري)</label>
                            <input type="file" class="form-control" id="attachments" multiple accept="image/*,.pdf,.doc,.docx">
                            <div class="form-text">يمكنك رفع صور أو مستندات توضح المشكلة (الحد الأقصى 5 ملفات)</div>
                        </div>
                        
                        <button type="submit" name="submit_ticket" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> إرسال تذكرة الدعم
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- الأسئلة الشائعة -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">الأسئلة الشائعة</h5>
                    
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    كيف أحصل على نقاط؟
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    يمكنك الحصول على نقاط من خلال:
                                    <ul>
                                        <li>إكمال المهام المتاحة</li>
                                        <li>تقييم المطاعم التي تزورها</li>
                                        <li>المشاركة في المنافسات الشهرية</li>
                                        <li>الدعوة لأصدقائك للانضمام</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    كيف أستبدل نقاطي؟
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    يمكنك استبدال نقاطك من خلال:
                                    <ol>
                                        <li>انتقل إلى صفحة "متجر النقاط"</li>
                                        <li>اختر الهدية التي تريدها</li>
                                        <li>اضغط على زر "استبدال"</li>
                                        <li>اتبع التعليمات للحصول على الهدية</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    ماذا أفعل إذا لم أحصل على نقاط بعد إكمال المهمة؟
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    إذا لم تحصل على نقاطك بعد إكمال المهمة:
                                    <ol>
                                        <li>تأكد من أنك أدخلت رابط المراجعة الصحيح</li>
                                        <li>تحقق من أن المراجعة ظاهرة على خرائط جوجل</li>
                                        <li>انتظر لمدة 24 ساعة للمعالجة التلقائية</li>
                                        <li>إذا لم تستلم النقاط، أرسل تذكرة دعم مع رقم المهمة</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    كيف أتواصل مع إدارة المطعم؟
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    للتواصل مع إدارة المطعم:
                                    <ul>
                                        <li>استخدم خاصية "الرسائل" في لوحة التحكم</li>
                                        <li>أرسل رسالة مباشرة إلى صاحب المطعم</li>
                                        <li>تأكد من ذكر اسم المطعم في الرسالة</li>
                                        <li>يمكنك أيضاً الاتصال بالمطعم مباشرة عبر الهاتف</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الشريط الجانبي -->
        <div class="col-lg-4">
            <!-- تذاكر الدعم السابقة -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">تذاكر الدعم السابقة</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="loadAllTickets()">عرض الكل</button>
                    </div>
                    
                    <div id="ticketsList">
                        <?php if (count($tickets) > 0): ?>
                            <?php foreach ($tickets as $ticket): ?>
                            <div class="ticket-item mb-3 p-3 border rounded <?php echo $ticket['status'] == 'open' ? 'border-primary' : 'border-success'; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1"><?php echo $ticket['subject']; ?></h6>
                                        <small class="text-muted">#<?php echo $ticket['id']; ?></small>
                                    </div>
                                    <span class="badge <?php echo getTicketBadgeClass($ticket['status']); ?>">
                                        <?php echo getTicketStatusArabic($ticket['status']); ?>
                                    </span>
                                </div>
                                
                                <p class="small mb-2"><?php echo substr($ticket['message'], 0, 80); ?>...</p>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted"><?php echo formatRelativeTime($ticket['created_at']); ?></small>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewTicketDetails(<?php echo $ticket['id']; ?>)">
                                        التفاصيل
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-ticket-alt fa-2x text-muted mb-2"></i>
                                <p>لا توجد تذاكر سابقة</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- معلومات الاتصال -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">معلومات الاتصال</h5>
                    
                    <div class="contact-info">
                        <div class="contact-item mb-3">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-details">
                                <h6>البريد الإلكتروني</h6>
                                <p><a href="mailto:support@nuqtaha.com">support@nuqtaha.com</a></p>
                            </div>
                        </div>
                        
                        <div class="contact-item mb-3">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="contact-details">
                                <h6>الهاتف</h6>
                                <p><a href="tel:920000000">920000000</a></p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="contact-details">
                                <h6>ساعات العمل</h6>
                                <p>24/7</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- قنوات الدعم -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">قنوات الدعم الأخرى</h5>
                    
                    <div class="support-channels">
                        <a href="https://wa.me/966500000000" target="_blank" class="channel-item whatsapp">
                            <i class="fab fa-whatsapp"></i>
                            <span>واتساب</span>
                        </a>
                        
                        <a href="https://twitter.com/nuqtaha" target="_blank" class="channel-item twitter">
                            <i class="fab fa-twitter"></i>
                            <span>تويتر</span>
                        </a>
                        
                        <a href="https://t.me/nuqtaha" target="_blank" class="channel-item telegram">
                            <i class="fab fa-telegram"></i>
                            <span>تلجرام</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال تفاصيل التذكرة -->
<div class="modal fade" id="ticketDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تفاصيل التذكرة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="ticketDetailsContent">
                    <!-- سيتم تحميل محتوى التذكرة هنا -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<script>
// تحميل تفاصيل التذكرة
async function viewTicketDetails(ticketId) {
    try {
        const response = await fetch(`/api/member?action=get-ticket-details&ticket_id=${ticketId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const ticket = data.data;
            
            let html = `
                <div class="ticket-header mb-4">
                    <h4>${ticket.subject}</h4>
                    <div class="d-flex gap-3">
                        <span class="badge ${getTicketBadgeClass(ticket.status)}">${getTicketStatusArabic(ticket.status)}</span>
                        <span class="text-muted">#${ticket.id}</span>
                        <span class="text-muted">${formatDate(ticket.created_at)}</span>
                    </div>
                </div>
                
                <div class="ticket-content mb-4">
                    <h6>وصف المشكلة:</h6>
                    <div class="p-3 bg-light rounded">
                        ${ticket.message}
                    </div>
                </div>
            `;
            
            // عرض الردود إذا وجدت
            if (ticket.replies && ticket.replies.length > 0) {
                html += `<h6>الردود:</h6>`;
                ticket.replies.forEach(reply => {
                    html += `
                        <div class="reply-item mb-3 p-3 border rounded ${reply.is_admin ? 'bg-light' : ''}">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>${reply.sender_name}</strong>
                                <small class="text-muted">${formatDate(reply.created_at)}</small>
                            </div>
                            <p class="mb-0">${reply.message}</p>
                        </div>
                    `;
                });
            }
            
            // نموذج إضافة رد (إذا كانت التذكرة مفتوحة)
            if (ticket.status === 'open') {
                html += `
                    <div class="reply-form mt-4">
                        <h6>إضافة رد:</h6>
                        <form id="replyForm">
                            <input type="hidden" name="ticket_id" value="${ticket.id}">
                            <div class="mb-3">
                                <textarea class="form-control" name="message" rows="3" required></textarea>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="submitReply()">إرسال الرد</button>
                        </form>
                    </div>
                `;
            }
            
            document.getElementById('ticketDetailsContent').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('ticketDetailsModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading ticket details:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل تفاصيل التذكرة', 'error');
    }
}

// إرسال رد على التذكرة
async function submitReply() {
    const form = document.getElementById('replyForm');
    const formData = new FormData(form);
    
    try {
        const response = await fetch('/api/member?action=add-ticket-reply', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showNotification('نجاح', 'تم إرسال الرد بنجاح');
            const modal = bootstrap.Modal.getInstance(document.getElementById('ticketDetailsModal'));
            modal.hide();
            
            // تحديث قائمة التذاكر
            setTimeout(() => {
                viewTicketDetails(formData.get('ticket_id'));
            }, 1000);
        } else {
            showNotification('خطأ', data.message, 'error');
        }
    } catch (error) {
        console.error('Error submitting reply:', error);
        showNotification('خطأ', 'حدث خطأ أثناء إرسال الرد', 'error');
    }
}

// تحميل جميع التذاكر
async function loadAllTickets() {
    try {
        const response = await fetch('/api/member?action=get-all-tickets');
        const data = await response.json();
        
        if (data.status === 'success') {
            let html = '';
            
            if (data.data.length === 0) {
                html = '<div class="text-center py-3"><p>لا توجد تذاكر</p></div>';
            } else {
                data.data.forEach(ticket => {
                    html += `
                        <div class="ticket-item mb-3 p-3 border rounded ${ticket.status == 'open' ? 'border-primary' : 'border-success'}">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">${ticket.subject}</h6>
                                    <small class="text-muted">#${ticket.id}</small>
                                </div>
                                <span class="badge ${getTicketBadgeClass(ticket.status)}">
                                    ${getTicketStatusArabic(ticket.status)}
                                </span>
                            </div>
                            
                            <p class="small mb-2">${ticket.message.substring(0, 80)}...</p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">${formatRelativeTime(ticket.created_at)}</small>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewTicketDetails(${ticket.id})">
                                    التفاصيل
                                </button>
                            </div>
                        </div>
                    `;
                });
            }
            
            document.getElementById('ticketsList').innerHTML = html;
            showNotification('تم التحميل', 'تم تحميل جميع التذاكر بنجاح');
        }
    } catch (error) {
        console.error('Error loading tickets:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل التذاكر', 'error');
    }
}

// دالة المساعدة لحالة التذكرة
function getTicketBadgeClass(status) {
    const classes = {
        'open': 'bg-primary',
        'in_progress': 'bg-warning',
        'resolved': 'bg-success',
        'closed': 'bg-secondary'
    };
    return classes[status] || 'bg-secondary';
}

function getTicketStatusArabic(status) {
    const statuses = {
        'open': 'مفتوحة',
        'in_progress': 'قيد المعالجة',
        'resolved': 'تم الحل',
        'closed': 'مغلقة'
    };
    return statuses[status] || status;
}

// دالة المساعدة لصياغة التاريخ
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// دالة المساعدة لصياغة الوقت النسبي
function formatRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffDay = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffDay === 0) {
        return 'اليوم';
    } else if (diffDay === 1) {
        return 'أمس';
    } else if (diffDay < 7) {
        return `قبل ${diffDay} أيام`;
    } else {
        return formatDate(dateString);
    }
}
</script>

<style>
.problem-types {
    background-color: var(--light-color);
    padding: 15px;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
}

.form-check-inline {
    margin-right: 20px;
    margin-bottom: 10px;
}

.ticket-item:hover {
    background-color: var(--light-color);
    cursor: pointer;
}

.contact-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}

.contact-item:last-child {
    margin-bottom: 0;
}

.contact-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    background-color: var(--primary-color);
    color: white;
    flex-shrink: 0;
}

.contact-details h6 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.contact-details p {
    margin-bottom: 0;
    color: var(--gray-color);
}

.support-channels {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.channel-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border-radius: var(--border-radius);
    color: white;
    text-decoration: none;
    transition: var(--transition);
}

.channel-item:hover {
    transform: translateX(-5px);
    color: white;
}

.channel-item.whatsapp {
    background-color: #25D366;
}

.channel-item.twitter {
    background-color: #1DA1F2;
}

.channel-item.telegram {
    background-color: #0088CC;
}

.channel-item i {
    margin-right: 10px;
    font-size: 1.2rem;
}

.reply-item {
    border-left: 4px solid var(--primary-color);
}

.reply-item.bg-light {
    border-left-color: var(--secondary-color);
}

.accordion-button:not(.collapsed) {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--dark-color);
}

.accordion-button:focus {
    box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25);
}
</style>

<?php
include 'includes/footer.php';
?>