<?php
// التحقق من صلاحيات الأدمن
if (!isset($user) || $user->getRole() !== 'admin') {
    header('Location: /login');
    exit;
}

$page_title = 'إدارة المهام';
$page_scripts = ['admin-tasks.js'];

include 'includes/header.php';

$admin = new Admin($db->getConnection(), $user->getId());

// معالجة الطلبات
$action = $_GET['action'] ?? '';
$task_id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add':
            $data = [
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'restaurant_id' => $_POST['restaurant_id'],
                'discount_percentage' => $_POST['discount_percentage'],
                'points_reward' => $_POST['points_reward'],
                'max_participants' => $_POST['max_participants'],
                'requirements' => $_POST['requirements'],
                'start_date' => $_POST['start_date'] ?: null,
                'end_date' => $_POST['end_date'] ?: null,
                'status' => $_POST['status']
            ];
            
            try {
                $sql = "INSERT INTO tasks (title, description, restaurant_id, discount_percentage, 
                         points_reward, max_participants, requirements, start_date, end_date, status, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->getConnection()->prepare($sql);
                $stmt->bind_param("ssiiiiisssi", 
                    $data['title'],
                    $data['description'],
                    $data['restaurant_id'],
                    $data['discount_percentage'],
                    $data['points_reward'],
                    $data['max_participants'],
                    $data['requirements'],
                    $data['start_date'],
                    $data['end_date'],
                    $data['status'],
                    $user->getId()
                );
                
                if ($stmt->execute()) {
                    $success = 'تم إنشاء المهمة بنجاح';
                } else {
                    $error = 'حدث خطأ أثناء إنشاء المهمة';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
            
        case 'edit':
            $data = [
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'discount_percentage' => $_POST['discount_percentage'],
                'points_reward' => $_POST['points_reward'],
                'max_participants' => $_POST['max_participants'],
                'requirements' => $_POST['requirements'],
                'start_date' => $_POST['start_date'] ?: null,
                'end_date' => $_POST['end_date'] ?: null,
                'status' => $_POST['status']
            ];
            
            $sql = "UPDATE tasks SET title = ?, description = ?, discount_percentage = ?, 
                    points_reward = ?, max_participants = ?, requirements = ?, 
                    start_date = ?, end_date = ?, status = ? WHERE id = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("ssiiissssi", 
                $data['title'],
                $data['description'],
                $data['discount_percentage'],
                $data['points_reward'],
                $data['max_participants'],
                $data['requirements'],
                $data['start_date'],
                $data['end_date'],
                $data['status'],
                $task_id
            );
            
            if ($stmt->execute()) {
                $success = 'تم تحديث المهمة بنجاح';
            } else {
                $error = 'حدث خطأ أثناء تحديث المهمة';
            }
            break;
            
        case 'delete':
            $sql = "DELETE FROM tasks WHERE id = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("i", $task_id);
            
            if ($stmt->execute()) {
                $success = 'تم حذف المهمة بنجاح';
            } else {
                $error = 'حدث خطأ أثناء حذف المهمة';
            }
            break;
    }
}

// الحصول على المطاعم
$restaurants = $admin->getRestaurants(['status' => 'active'], 100, 0);

// الحصول على المهام
$filters = [
    'status' => $_GET['status'] ?? '',
    'restaurant_id' => $_GET['restaurant_id'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$sql = "SELECT t.*, r.name as restaurant_name, r.city, 
               c.name as category_name, c.color as category_color,
               (SELECT COUNT(*) FROM user_tasks WHERE task_id = t.id AND status = 'completed') as completed_count
        FROM tasks t
        JOIN restaurants r ON t.restaurant_id = r.id
        LEFT JOIN categories c ON r.category_id = c.id
        WHERE 1=1";
        
$params = [];
$types = '';

if ($filters['status']) {
    $sql .= " AND t.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}

if ($filters['restaurant_id']) {
    $sql .= " AND t.restaurant_id = ?";
    $params[] = $filters['restaurant_id'];
    $types .= 'i';
}

if ($filters['search']) {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $search_term = "%{$filters['search']}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $db->getConnection()->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-tasks"></i> إدارة المهام</h2>
    
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
    
    <!-- فلترة المهام -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع الحالات</option>
                        <option value="available" <?php echo ($filters['status'] === 'available') ? 'selected' : ''; ?>>متاحة</option>
                        <option value="active" <?php echo ($filters['status'] === 'active') ? 'selected' : ''; ?>>نشطة</option>
                        <option value="completed" <?php echo ($filters['status'] === 'completed') ? 'selected' : ''; ?>>مكتملة</option>
                        <option value="cancelled" <?php echo ($filters['status'] === 'cancelled') ? 'selected' : ''; ?>>ملغاة</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">المطعم</label>
                    <select name="restaurant_id" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع المطاعم</option>
                        <?php foreach ($restaurants as $restaurant): ?>
                        <option value="<?php echo $restaurant['id']; ?>" <?php echo ($filters['restaurant_id'] == $restaurant['id']) ? 'selected' : ''; ?>>
                            <?php echo $restaurant['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">بحث</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="ابحث..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">إجراء</label>
                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i class="fas fa-plus"></i> إضافة مهمة جديدة
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- جدول المهام -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>المهمة</th>
                            <th>المطعم</th>
                            <th>المكافأة</th>
                            <th>المشاركون</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tasks) > 0): ?>
                            <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $task['title']; ?></strong>
                                    <?php if ($task['description']): ?>
                                    <br>
                                    <small class="text-muted"><?php echo substr($task['description'], 0, 60); ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo $task['restaurant_name']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $task['city']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="badge bg-primary"><?php echo $task['points_reward']; ?> نقطة</span>
                                        <br>
                                        <span class="badge bg-success">خصم <?php echo $task['discount_percentage']; ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="progress" style="height: 10px;">
                                        <?php
                                        $percentage = $task['max_participants'] > 0 ? ($task['current_participants'] / $task['max_participants']) * 100 : 0;
                                        ?>
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                        </div>
                                    </div>
                                    <small>
                                        <?php echo $task['current_participants']; ?>/<?php echo $task['max_participants']; ?>
                                        (<?php echo $task['completed_count']; ?> مكتملة)
                                    </small>
                                </td>
                                <td>
                                    <?php if ($task['start_date']): ?>
                                    <small>من: <?php echo date('Y-m-d', strtotime($task['start_date'])); ?></small>
                                    <br>
                                    <?php endif; ?>
                                    <?php if ($task['end_date']): ?>
                                    <small>إلى: <?php echo date('Y-m-d', strtotime($task['end_date'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_badge = [
                                        'available' => 'success',
                                        'active' => 'primary',
                                        'completed' => 'info',
                                        'cancelled' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_badge[$task['status']] ?? 'secondary'; ?>">
                                        <?php
                                        $status_names = [
                                            'available' => 'متاحة',
                                            'active' => 'نشطة',
                                            'completed' => 'مكتملة',
                                            'cancelled' => 'ملغاة'
                                        ];
                                        echo $status_names[$task['status']] ?? $task['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-info" onclick="viewParticipants(<?php echo $task['id']; ?>)">
                                            <i class="fas fa-users"></i>
                                        </button>
                                        <button class="btn btn-warning" onclick="editTask(<?php echo $task['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger" onclick="deleteTask(<?php echo $task['id']; ?>, '<?php echo addslashes($task['title']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-tasks fa-2x text-muted mb-3"></i>
                                    <p>لا توجد مهام</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- مودال إضافة مهمة جديدة -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إضافة مهمة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addTaskForm">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label">عنوان المهمة <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="restaurant_id" class="form-label">المطعم <span class="text-danger">*</span></label>
                            <select class="form-control" id="restaurant_id" name="restaurant_id" required>
                                <option value="">اختر المطعم</option>
                                <?php foreach ($restaurants as $restaurant): ?>
                                <option value="<?php echo $restaurant['id']; ?>"><?php echo $restaurant['name']; ?> (<?php echo $restaurant['city']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">وصف المهمة</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="discount_percentage" class="form-label">نسبة الخصم % <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" 
                                   min="1" max="50" value="10" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="points_reward" class="form-label">عدد النقاط <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="points_reward" name="points_reward" 
                                   min="1" max="1000" value="100" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="max_participants" class="form-label">الحد الأقصى للمشاركين <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                   min="1" max="1000" value="10" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">تاريخ البدء (اختياري)</label>
                            <input type="datetime-local" class="form-control" id="start_date" name="start_date">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">تاريخ الانتهاء (اختياري)</label>
                            <input type="datetime-local" class="form-control" id="end_date" name="end_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="requirements" class="form-label">متطلبات المهمة</label>
                        <textarea class="form-control" id="requirements" name="requirements" rows="3" 
                                  placeholder="مثال: - زيارة المطعم وطلب وجبة
- استخدام كود الخصم
- رفع مراجعة على خرائط جوجل"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">الحالة</label>
                        <select class="form-control" id="status" name="status">
                            <option value="available">متاحة</option>
                            <option value="active">نشطة</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="addTaskForm" class="btn btn-primary">إضافة المهمة</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال تعديل المهمة -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تعديل المهمة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editTaskForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_task_id">
                    
                    <!-- سيتم تحميل النموذج هنا ديناميكياً -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="editTaskForm" class="btn btn-primary">حفظ التعديلات</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال المشاركين -->
<div class="modal fade" id="participantsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">مشاركي المهمة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="participantsList">
                    <!-- سيتم تحميل المشاركين هنا ديناميكياً -->
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
// تعديل مهمة
async function editTask(taskId) {
    try {
        const response = await fetch(`/api/admin?action=get-task&id=${taskId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const task = data.data;
            
            document.getElementById('edit_task_id').value = task.id;
            
            let html = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">عنوان المهمة <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" value="${task.title}" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">المطعم</label>
                        <input type="text" class="form-control" value="${task.restaurant_name}" readonly>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">وصف المهمة</label>
                    <textarea class="form-control" name="description" rows="3">${task.description || ''}</textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">نسبة الخصم % <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="discount_percentage" 
                               value="${task.discount_percentage}" min="1" max="50" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">عدد النقاط <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="points_reward" 
                               value="${task.points_reward}" min="1" max="1000" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">الحد الأقصى للمشاركين <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="max_participants" 
                               value="${task.max_participants}" min="1" max="1000" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">تاريخ البدء</label>
                        <input type="datetime-local" class="form-control" name="start_date" 
                               value="${task.start_date ? task.start_date.substring(0, 16) : ''}">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">تاريخ الانتهاء</label>
                        <input type="datetime-local" class="form-control" name="end_date" 
                               value="${task.end_date ? task.end_date.substring(0, 16) : ''}">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">متطلبات المهمة</label>
                    <textarea class="form-control" name="requirements" rows="3">${task.requirements || ''}</textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">الحالة</label>
                    <select class="form-control" name="status">
                        <option value="available" ${task.status === 'available' ? 'selected' : ''}>متاحة</option>
                        <option value="active" ${task.status === 'active' ? 'selected' : ''}>نشطة</option>
                        <option value="completed" ${task.status === 'completed' ? 'selected' : ''}>مكتملة</option>
                        <option value="cancelled" ${task.status === 'cancelled' ? 'selected' : ''}>ملغاة</option>
                    </select>
                </div>
            `;
            
            document.querySelector('#editTaskForm .modal-body').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading task:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل بيانات المهمة', 'error');
    }
}

// عرض المشاركين
async function viewParticipants(taskId) {
    try {
        const response = await fetch(`/api/admin?action=get-task-participants&task_id=${taskId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            let html = '';
            
            if (data.data.length === 0) {
                html = '<p class="text-center">لا يوجد مشاركون</p>';
            } else {
                html = `
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>الكود</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.data.forEach(participant => {
                    const status_badge = {
                        'reserved': 'warning',
                        'in_progress': 'info',
                        'completed': 'success',
                        'cancelled': 'danger'
                    }[participant.status] || 'secondary';
                    
                    const status_names = {
                        'reserved': 'محجوزة',
                        'in_progress': 'قيد التنفيذ',
                        'completed': 'مكتملة',
                        'cancelled': 'ملغاة'
                    };
                    
                    html += `
                        <tr>
                            <td>
                                <div>${participant.user_name}</div>
                                <small class="text-muted">${participant.user_email}</small>
                            </td>
                            <td><code>${participant.discount_code}</code></td>
                            <td>
                                <span class="badge bg-${status_badge}">
                                    ${status_names[participant.status] || participant.status}
                                </span>
                            </td>
                            <td>
                                <small>${participant.created_at}</small>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            document.getElementById('participantsList').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('participantsModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading participants:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل المشاركين', 'error');
    }
}

// حذف مهمة
function deleteTask(taskId, taskTitle) {
    if (confirm(`هل أنت متأكد من حذف المهمة "${taskTitle}"؟`)) {
        window.location.href = `/admin/tasks?action=delete&id=${taskId}`;
    }
}
</script>

<style>
.progress {
    margin-bottom: 5px;
}

.badge {
    margin-right: 5px;
}

.btn-group .btn {
    border-radius: var(--border-radius) !important;
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}
</style>

<?php
include 'includes/footer.php';
?>