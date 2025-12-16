<?php
// التحقق من صلاحيات العضو
if (!isset($user) || !in_array($user->getRole(), ['member', 'moderator'])) {
    header('Location: /login');
    exit;
}

$page_title = 'المهام';
$page_scripts = ['member-tasks.js'];

include 'includes/header.php';

// فلترة المهام
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// الحصول على المهام المتاحة
$available_tasks = [];
// الحصول على مهام العضو
$user_tasks = [];
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-tasks"></i> المهام</h2>
    
    <!-- التبويبات -->
    <ul class="nav nav-tabs mb-4" id="tasksTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button" role="tab">
                <i class="fas fa-list"></i> المهام المتاحة
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="my-tasks-tab" data-bs-toggle="tab" data-bs-target="#my-tasks" type="button" role="tab">
                <i class="fas fa-user"></i> مهامي
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                <i class="fas fa-check-circle"></i> المكتملة
            </button>
        </li>
    </ul>
    
    <!-- محتوى التبويبات -->
    <div class="tab-content" id="tasksTabsContent">
        <!-- المهام المتاحة -->
        <div class="tab-pane fade show active" id="available" role="tabpanel">
            <!-- فلترة -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">المدينة</label>
                            <select class="form-select" id="cityFilter" onchange="filterAvailableTasks()">
                                <option value="all">جميع المدن</option>
                                <?php foreach ($cities as $city): ?>
                                <option value="<?php echo $city; ?>" <?php echo $city === $user->getCity() ? 'selected' : ''; ?>>
                                    <?php echo $city; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">التصنيف</label>
                            <select class="form-select" id="categoryFilter" onchange="filterAvailableTasks()">
                                <option value="all">جميع التصنيفات</option>
                                <!-- سيتم تحميل التصنيفات هنا ديناميكياً -->
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">بحث</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="searchFilter" placeholder="ابحث عن مهمة..." onkeyup="filterAvailableTasks()">
                                <button class="btn btn-primary" onclick="filterAvailableTasks()">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- قائمة المهام المتاحة -->
            <div id="availableTasksList">
                <div class="row">
                    <div class="col-12 text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- مهامي -->
        <div class="tab-pane fade" id="my-tasks" role="tabpanel">
            <div id="myTasksList">
                <div class="row">
                    <div class="col-12 text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- المهام المكتملة -->
        <div class="tab-pane fade" id="completed" role="tabpanel">
            <div id="completedTasksList">
                <div class="row">
                    <div class="col-12 text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال تفاصيل المهمة -->
<div class="modal fade" id="taskDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تفاصيل المهمة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="taskDetailsContent">
                    <!-- سيتم تحميل التفاصيل هنا ديناميكياً -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-primary" id="reserveTaskBtn" onclick="reserveTask()">
                    <i class="fas fa-check"></i> احجز المهمة
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// تحميل البيانات عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
    loadAvailableTasks();
    loadMyTasks();
    loadCompletedTasks();
    loadCategories();
});

// تحميل التصنيفات
async function loadCategories() {
    try {
        const response = await fetch('/api/restaurants?action=get-categories');
        const data = await response.json();
        
        if (data.status === 'success') {
            const select = document.getElementById('categoryFilter');
            data.data.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

// تحميل المهام المتاحة
async function loadAvailableTasks() {
    try {
        const city = document.getElementById('cityFilter').value;
        const category = document.getElementById('categoryFilter').value;
        const search = document.getElementById('searchFilter').value;
        
        const response = await fetch(`/api/tasks?action=get-available&city=${city !== 'all' ? city : ''}&category_id=${category !== 'all' ? category : ''}&search=${search}&limit=12`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const tasks = data.data;
            let html = '';
            
            if (tasks.length === 0) {
                html = `
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                        <h5>لا توجد مهام متاحة حالياً</h5>
                        <p class="text-muted">عد لاحقاً للتحقق من المهام الجديدة</p>
                    </div>
                `;
            } else {
                tasks.forEach(task => {
                    const percentage = task.max_participants > 0 ? 
                        Math.round((task.current_participants / task.max_participants) * 100) : 0;
                    
                    html += `
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="task-card h-100">
                                <div class="task-header">
                                    <div class="task-category" style="background-color: ${task.category_color || '#ff6b35'}">
                                        ${task.category_name || 'غير مصنف'}
                                    </div>
                                    <div class="task-reward">
                                        <span class="badge bg-primary">${task.points_reward} نقطة</span>
                                        <span class="badge bg-success">خصم ${task.discount_percentage}%</span>
                                    </div>
                                </div>
                                
                                <div class="task-body">
                                    <h5 class="task-title">${task.title}</h5>
                                    <div class="task-meta">
                                        <p><i class="fas fa-utensils"></i> ${task.restaurant_name}</p>
                                        <p><i class="fas fa-map-marker-alt"></i> ${task.city}</p>
                                    </div>
                                    
                                    <p class="task-description">${task.description ? task.description.substring(0, 100) + '...' : 'لا يوجد وصف'}</p>
                                    
                                    <div class="task-progress mb-3">
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" style="width: ${percentage}%">
                                                ${percentage}%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            ${task.current_participants}/${task.max_participants} مشارك
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="task-footer">
                                    <button class="btn btn-outline-primary btn-sm" onclick="viewTaskDetails(${task.id})">
                                        <i class="fas fa-info-circle"></i> التفاصيل
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="reserveTask(${task.id})">
                                        <i class="fas fa-check"></i> احجز الآن
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            document.getElementById('availableTasksList').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading tasks:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل المهام', 'error');
    }
}

// تصفية المهام المتاحة
function filterAvailableTasks() {
    loadAvailableTasks();
}

// تحميل مهامي
async function loadMyTasks() {
    try {
        const response = await fetch('/api/tasks?action=get-user-tasks&status=active');
        const data = await response.json();
        
        if (data.status === 'success') {
            const tasks = data.data;
            let html = '';
            
            if (tasks.length === 0) {
                html = `
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-user-clock fa-3x text-muted mb-3"></i>
                        <h5>لا توجد مهام نشطة</h5>
                        <p class="text-muted">ابدأ بحجز أول مهمة لك من قسم المهام المتاحة</p>
                    </div>
                `;
            } else {
                html = '<div class="row">';
                
                tasks.forEach(task => {
                    const isExpired = new Date(task.code_expires) < new Date();
                    
                    html += `
                        <div class="col-md-6 mb-4">
                            <div class="task-card ${isExpired ? 'expired-task' : ''}">
                                <div class="task-header">
                                    <div class="task-status">
                                        <span class="badge ${isExpired ? 'bg-danger' : 'bg-warning'}">
                                            ${isExpired ? 'منتهي' : 'نشط'}
                                        </span>
                                    </div>
                                    <div class="task-reward">
                                        <span class="badge bg-primary">${task.points_reward} نقطة</span>
                                        <span class="badge bg-success">خصم ${task.discount_percentage}%</span>
                                    </div>
                                </div>
                                
                                <div class="task-body">
                                    <h5 class="task-title">${task.title}</h5>
                                    <div class="task-meta">
                                        <p><i class="fas fa-utensils"></i> ${task.restaurant_name}</p>
                                        <p><i class="fas fa-map-marker-alt"></i> ${task.city}</p>
                                    </div>
                                    
                                    ${task.discount_code ? `
                                    <div class="discount-code mb-3">
                                        <label class="form-label">كود الخصم:</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" value="${task.discount_code}" readonly>
                                            <button class="btn btn-outline-secondary" onclick="copyCode('${task.discount_code}')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">
                                            ينتهي في: ${task.code_expires}
                                        </small>
                                    </div>
                                    ` : ''}
                                </div>
                                
                                <div class="task-footer">
                                    ${isExpired ? `
                                    <button class="btn btn-danger btn-sm" onclick="renewTask(${task.id})">
                                        <i class="fas fa-redo"></i> تجديد
                                    </button>
                                    ` : `
                                    <button class="btn btn-primary btn-sm" onclick="completeMyTask(${task.id})">
                                        <i class="fas fa-check-circle"></i> إكمال المهمة
                                    </button>
                                    `}
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
            }
            
            document.getElementById('myTasksList').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading my tasks:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل مهامك', 'error');
    }
}

// تحميل المهام المكتملة
async function loadCompletedTasks() {
    try {
        const response = await fetch('/api/tasks?action=get-user-tasks&status=completed');
        const data = await response.json();
        
        if (data.status === 'success') {
            const tasks = data.data;
            let html = '';
            
            if (tasks.length === 0) {
                html = `
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                        <h5>لا توجد مهام مكتملة</h5>
                        <p class="text-muted">أكمل مهامك الأولى لكي تظهر هنا</p>
                    </div>
                `;
            } else {
                html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>المهمة</th><th>المطعم</th><th>النقاط</th><th>التاريخ</th><th>الإجراءات</th></tr></thead><tbody>';
                
                tasks.forEach(task => {
                    html += `
                        <tr>
                            <td>
                                <strong>${task.title}</strong>
                                ${task.rating ? `
                                <div class="rating-stars small">
                                    ${'★'.repeat(task.rating) + '☆'.repeat(5 - task.rating)}
                                </div>
                                ` : ''}
                            </td>
                            <td>${task.restaurant_name}</td>
                            <td><span class="badge bg-primary">${task.points_reward} نقطة</span></td>
                            <td>${task.completed_at}</td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewCompletedTask(${task.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
            }
            
            document.getElementById('completedTasksList').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading completed tasks:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل المهام المكتملة', 'error');
    }
}

// عرض تفاصيل المهمة
async function viewTaskDetails(taskId) {
    try {
        const response = await fetch(`/api/tasks?action=get-task-details&task_id=${taskId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const task = data.data;
            
            let html = `
                <div class="task-details">
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <h4>${task.title}</h4>
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-primary me-2">${task.points_reward} نقطة</span>
                                <span class="badge bg-success me-2">خصم ${task.discount_percentage}%</span>
                                <span class="badge bg-info">${task.city}</span>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="task-meta">
                                <p><strong>المطعم:</strong> ${task.restaurant_name}</p>
                                <p><strong>التقييم:</strong> ${task.restaurant_rating} (${task.total_reviews} تقييم)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>وصف المهمة</h5>
                        <p>${task.description || 'لا يوجد وصف'}</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>كيف تعمل:</h5>
                            <ol>
                                <li>احصل على كود الخصم بعد الحجز</li>
                                <li>زُر المطعم واستخدم الكود</li>
                                <li>ارفع مراجعتك على خرائط جوجل</li>
                                <li>أدخل رابط المراجعة في النظام</li>
                                <li>احصل على ${task.points_reward} نقطة</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h5>متطلبات المهمة</h5>
                            <div class="requirements">
                                ${task.requirements ? task.requirements.replace(/\n/g, '<br>') : 'لا توجد متطلبات خاصة'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>مشاركون</h5>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: ${(task.current_participants / task.max_participants) * 100}%">
                                ${task.current_participants}/${task.max_participants}
                            </div>
                        </div>
                        <small class="text-muted">المتبقي: ${task.max_participants - task.current_participants} مكان</small>
                    </div>
                    
                    ${task.reviews && task.reviews.length > 0 ? `
                    <div class="mb-4">
                        <h5>آخر التقييمات للمطعم</h5>
                        <div class="reviews-preview">
                            ${task.reviews.slice(0, 3).map(review => `
                                <div class="review-item border-bottom py-2">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>${review.user_name}</strong>
                                            <div class="rating-stars small">${'★'.repeat(review.rating)}</div>
                                        </div>
                                        <small>${review.created_at}</small>
                                    </div>
                                    <p class="mb-0">${review.comment ? review.comment.substring(0, 80) + '...' : ''}</p>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('taskDetailsContent').innerHTML = html;
            document.getElementById('reserveTaskBtn').setAttribute('data-task-id', taskId);
            
            const modal = new bootstrap.Modal(document.getElementById('taskDetailsModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading task details:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل تفاصيل المهمة', 'error');
    }
}

// حجز المهمة
async function reserveTask(taskId = null) {
    if (!taskId) {
        taskId = document.getElementById('reserveTaskBtn').getAttribute('data-task-id');
    }
    
    try {
        const response = await fetch('/api/tasks?action=reserve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `task_id=${taskId}`
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showNotification('نجاح', 'تم حجز المهمة بنجاح! تحقق من بريدك الإلكتروني للحصول على كود الخصم');
            
            // إغلاق المودال
            const modal = bootstrap.Modal.getInstance(document.getElementById('taskDetailsModal'));
            if (modal) modal.hide();
            
            // تحديث البيانات
            loadAvailableTasks();
            loadMyTasks();
            
            // عرض كود الخصم
            showDiscountCode(data.data);
        } else {
            showNotification('خطأ', data.message, 'error');
        }
    } catch (error) {
        console.error('Error reserving task:', error);
        showNotification('خطأ', 'حدث خطأ أثناء حجز المهمة', 'error');
    }
}

// عرض كود الخصم
function showDiscountCode(data) {
    let html = `
        <div class="text-center p-4">
            <i class="fas fa-gift fa-4x text-success mb-3"></i>
            <h4>مبروك! تم حجز المهمة بنجاح</h4>
            
            <div class="discount-code-box mt-4 p-3 border rounded">
                <h5 class="text-danger">كود الخصم</h5>
                <h2 class="my-3">${data.discount_code}</h2>
                <p class="text-muted">صالح حتى: ${data.code_expires}</p>
            </div>
            
            <div class="mt-4">
                <button class="btn btn-primary" onclick="copyCode('${data.discount_code}')">
                    <i class="fas fa-copy"></i> نسخ الكود
                </button>
                <button class="btn btn-outline-primary ms-2" onclick="window.location.href='/member/tasks#my-tasks'">
                    <i class="fas fa-list"></i> عرض مهامي
                </button>
            </div>
        </div>
    `;
    
    showModal('كود الخصم', html);
}

// نسخ الكود
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        showNotification('نجاح', 'تم نسخ الكود إلى الحافظة');
    });
}

// إكمال مهمتي
function completeMyTask(taskId) {
    window.location.href = `/complete-task?id=${taskId}`;
}

// تجديد المهمة
async function renewTask(taskId) {
    if (confirm('هل تريد تجديد هذه المهمة؟ سيتم إنشاء كود خصم جديد')) {
        try {
            const response = await fetch('/api/member?action=renew-task', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `task_id=${taskId}`
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                showNotification('نجاح', 'تم تجديد المهمة بنجاح');
                loadMyTasks();
            } else {
                showNotification('خطأ', data.message, 'error');
            }
        } catch (error) {
            console.error('Error renewing task:', error);
            showNotification('خطأ', 'حدث خطأ أثناء تجديد المهمة', 'error');
        }
    }
}

// عرض مهمة مكتملة
async function viewCompletedTask(taskId) {
    // يمكن إضافة تفاصيل المهمة المكتملة هنا
    showNotification('قريباً', 'ستتوفر تفاصيل المهمة المكتملة قريباً', 'info');
}
</script>

<style>
.task-card {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
    transition: var(--transition);
    background: white;
}

.task-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.task-header {
    padding: 15px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.task-category {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.9rem;
    color: white;
}

.task-reward {
    display: flex;
    gap: 5px;
}

.task-body {
    padding: 20px;
}

.task-title {
    color: var(--dark-color);
    margin-bottom: 10px;
    font-size: 1.1rem;
}

.task-meta p {
    margin-bottom: 5px;
    color: var(--gray-color);
    font-size: 0.9rem;
}

.task-meta i {
    width: 20px;
    text-align: center;
}

.task-description {
    color: var(--gray-color);
    font-size: 0.9rem;
    line-height: 1.6;
}

.task-progress .progress {
    height: 10px;
    margin-bottom: 5px;
}

.task-footer {
    padding: 15px 20px;
    background-color: var(--light-color);
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.expired-task {
    opacity: 0.8;
    border-color: var(--danger-color);
}

.expired-task .task-header {
    background: linear-gradient(135deg, var(--danger-color), #dc2626);
}

.discount-code-box {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-color: var(--info-color) !important;
}

.rating-stars.small {
    font-size: 0.9rem;
    color: #ffc107;
}

.requirements {
    background-color: var(--light-color);
    padding: 15px;
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    line-height: 1.6;
}

.reviews-preview {
    max-height: 200px;
    overflow-y: auto;
}
</style>

<?php
include 'includes/footer.php';
?>