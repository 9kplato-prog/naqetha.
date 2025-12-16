<?php
// التحقق من صلاحيات الأدمن
if (!isset($user) || $user->getRole() !== 'admin') {
    header('Location: /login');
    exit;
}

$page_title = 'إدارة متجر النقاط';
$page_scripts = ['admin-store.js'];

include 'includes/header.php';

$admin = new Admin($db->getConnection(), $user->getId());

// معالجة الطلبات
$action = $_GET['action'] ?? '';
$product_id = $_GET['id'] ?? 0;
$order_id = $_GET['order_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add-product':
            $data = [
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'category' => $_POST['category'],
                'points_required' => $_POST['points_required'],
                'stock' => $_POST['stock'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'sort_order' => $_POST['sort_order'] ?? 0
            ];
            
            try {
                // رفع صورة المنتج إذا وجدت
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $filename = uploadFile($_FILES['image'], 'image');
                    $data['image'] = $filename;
                }
                
                $sql = "INSERT INTO store_products (name, description, category, points_required, image, stock, is_active, sort_order, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->getConnection()->prepare($sql);
                $stmt->bind_param("sssisiiii",
                    $data['name'],
                    $data['description'],
                    $data['category'],
                    $data['points_required'],
                    $data['image'] ?? null,
                    $data['stock'],
                    $data['is_active'],
                    $data['sort_order'],
                    $user->getId()
                );
                
                if ($stmt->execute()) {
                    $success = 'تم إضافة المنتج بنجاح';
                } else {
                    $error = 'حدث خطأ أثناء إضافة المنتج';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
            
        case 'edit-product':
            $data = [
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'category' => $_POST['category'],
                'points_required' => $_POST['points_required'],
                'stock' => $_POST['stock'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'sort_order' => $_POST['sort_order']
            ];
            
            try {
                // تحديث صورة المنتج إذا وجدت
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $filename = uploadFile($_FILES['image'], 'image');
                    $data['image'] = $filename;
                    
                    $sql = "UPDATE store_products SET name = ?, description = ?, category = ?, 
                           points_required = ?, image = ?, stock = ?, is_active = ?, sort_order = ? 
                           WHERE id = ?";
                    $stmt = $db->getConnection()->prepare($sql);
                    $stmt->bind_param("sssisiisi",
                        $data['name'],
                        $data['description'],
                        $data['category'],
                        $data['points_required'],
                        $data['image'],
                        $data['stock'],
                        $data['is_active'],
                        $data['sort_order'],
                        $product_id
                    );
                } else {
                    $sql = "UPDATE store_products SET name = ?, description = ?, category = ?, 
                           points_required = ?, stock = ?, is_active = ?, sort_order = ? 
                           WHERE id = ?";
                    $stmt = $db->getConnection()->prepare($sql);
                    $stmt->bind_param("sssiiisi",
                        $data['name'],
                        $data['description'],
                        $data['category'],
                        $data['points_required'],
                        $data['stock'],
                        $data['is_active'],
                        $data['sort_order'],
                        $product_id
                    );
                }
                
                if ($stmt->execute()) {
                    $success = 'تم تحديث المنتج بنجاح';
                } else {
                    $error = 'حدث خطأ أثناء تحديث المنتج';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
            
        case 'delete-product':
            $sql = "DELETE FROM store_products WHERE id = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("i", $product_id);
            
            if ($stmt->execute()) {
                $success = 'تم حذف المنتج بنجاح';
            } else {
                $error = 'حدث خطأ أثناء حذف المنتج';
            }
            break;
            
        case 'update-order-status':
            $status = $_POST['status'];
            $notes = $_POST['notes'] ?? '';
            
            if ($admin->updateOrderStatus($order_id, $status, $notes)) {
                $success = 'تم تحديث حالة الطلب بنجاح';
            } else {
                $error = 'حدث خطأ أثناء تحديث حالة الطلب';
            }
            break;
            
        case 'send-order-code':
            $code = $_POST['code'] ?? '';
            
            if ($admin->sendOrderCode($order_id, $code)) {
                $success = 'تم إرسال الكود للمستخدم بنجاح';
            } else {
                $error = 'حدث خطأ أثناء إرسال الكود';
            }
            break;
    }
}

// الحصول على قائمة الطلبات
$filters = [
    'status' => $_GET['status'] ?? '',
    'category' => $_GET['category'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$orders = $admin->getStoreOrders($filters, 20, 0);

// الحصول على قائمة المنتجات
$products_sql = "SELECT * FROM store_products ORDER BY sort_order, name";
$products_result = $db->getConnection()->query($products_sql);
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}

// فئات المنتجات
$categories = [
    'mobile_balance' => 'رصيد جوال',
    'coupons' => 'كوبونات',
    'bank_transfer' => 'تحويل بنكي',
    'tickets' => 'تذاكر',
    'other' => 'أخرى'
];

// حالات الطلبات
$order_statuses = [
    'pending' => 'معلق',
    'processing' => 'قيد المعالجة',
    'completed' => 'مكتمل',
    'cancelled' => 'ملغي'
];
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-store"></i> إدارة متجر النقاط</h2>
    
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
    
    <!-- التبويبات -->
    <ul class="nav nav-tabs mb-4" id="storeTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button">
                <i class="fas fa-shopping-cart"></i> طلبات الاستبدال
                <?php 
                $pending_count = 0;
                foreach ($orders as $order) {
                    if ($order['status'] === 'pending') $pending_count++;
                }
                if ($pending_count > 0): ?>
                <span class="badge bg-danger"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button">
                <i class="fas fa-gift"></i> المنتجات
            </button>
        </li>
    </ul>
    
    <!-- محتوى التبويبات -->
    <div class="tab-content" id="storeTabContent">
        <!-- تبويب الطلبات -->
        <div class="tab-pane fade show active" id="orders" role="tabpanel">
            <!-- فلترة الطلبات -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="tab" value="orders">
                        
                        <div class="col-md-3">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">جميع الحالات</option>
                                <?php foreach ($order_statuses as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($filters['status'] === $value) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">الفئة</label>
                            <select name="category" class="form-select" onchange="this.form.submit()">
                                <option value="">جميع الفئات</option>
                                <?php foreach ($categories as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($filters['category'] === $value) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">بحث</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="ابحث..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- جدول الطلبات -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>المستخدم</th>
                                    <th>المنتج</th>
                                    <th>النقاط</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($orders) > 0): ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo $order['order_number']; ?></strong>
                                            <?php if ($order['code_sent']): ?>
                                            <span class="badge bg-success">✓</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo $order['user_name']; ?></strong>
                                                <small class="d-block text-muted"><?php echo $order['user_email']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($order['image']): ?>
                                                <img src="/uploads/images/<?php echo $order['image']; ?>" class="product-thumb me-2" alt="<?php echo $order['product_name']; ?>">
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo $order['product_name']; ?></strong>
                                                    <small class="d-block text-muted"><?php echo $categories[$order['product_category']] ?? $order['product_category']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">
                                                <?php echo number_format($order['points_paid']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'completed' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?>">
                                                <?php echo $order_statuses[$order['status']] ?? $order['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#orderDetailsModal" 
                                                        onclick="showOrderDetails(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                                                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#updateStatusModal" 
                                                        onclick="setUpdateStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>', '#<?php echo $order['order_number']; ?>')">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if (($order['product_category'] === 'mobile_balance' || $order['product_category'] === 'coupons') && 
                                                         ($order['status'] === 'processing' || $order['status'] === 'pending')): ?>
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendCodeModal" 
                                                        onclick="setSendCode(<?php echo $order['id']; ?>, '<?php echo $order['order_number']; ?>')">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-shopping-cart fa-2x text-muted mb-3"></i>
                                            <p>لا يوجد طلبات</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- تبويب المنتجات -->
        <div class="tab-pane fade" id="products" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>المنتجات</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus"></i> إضافة منتج جديد
                </button>
            </div>
            
            <div class="row">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                    <div class="col-md-4 col-sm-6 mb-4">
                        <div class="card product-card">
                            <?php if ($product['image']): ?>
                            <img src="/uploads/images/<?php echo $product['image']; ?>" class="card-img-top product-image" alt="<?php echo $product['name']; ?>">
                            <?php else: ?>
                            <div class="product-image-placeholder">
                                <i class="fas fa-gift"></i>
                            </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0"><?php echo $product['name']; ?></h5>
                                    <span class="badge bg-<?php echo $product['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $product['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </div>
                                
                                <p class="card-text text-muted small"><?php echo substr($product['description'], 0, 60); ?>...</p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge bg-warning">
                                        <i class="fas fa-coins"></i> <?php echo number_format($product['points_required']); ?> نقطة
                                    </span>
                                    <span class="badge bg-info">
                                        <?php echo $categories[$product['category']] ?? $product['category']; ?>
                                    </span>
                                </div>
                                
                                <div class="stock-info mb-3">
                                    <small class="text-muted">
                                        <?php if ($product['stock'] == -1): ?>
                                        مخزون غير محدود
                                        <?php else: ?>
                                        المخزون: <?php echo $product['stock']; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                
                                <div class="btn-group w-100">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProductModal" 
                                            onclick="editProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>')">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-gift fa-3x text-muted mb-3"></i>
                        <p>لا يوجد منتجات</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus"></i> إضافة أول منتج
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- مودال تفاصيل الطلب -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تفاصيل الطلب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="orderDetailsContent">
                    <!-- سيتم تحميل المحتوى هنا ديناميكياً -->
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

<!-- مودال تحديث حالة الطلب -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تحديث حالة الطلب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="updateStatusForm">
                    <input type="hidden" name="action" value="update-order-status">
                    <input type="hidden" id="update_status_order_id" name="order_id">
                    
                    <div class="mb-3">
                        <p>الطلب: <strong id="update_status_order_number"></strong></p>
                        <label for="update_status" class="form-label">الحالة الجديدة</label>
                        <select class="form-control" id="update_status" name="status" required>
                            <?php foreach ($order_statuses as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status_notes" class="form-label">ملاحظات (اختياري)</label>
                        <textarea class="form-control" id="status_notes" name="notes" rows="3" placeholder="ملاحظات إضافية..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="updateStatusForm" class="btn btn-primary">تحديث الحالة</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال إرسال الكود -->
<div class="modal fade" id="sendCodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إرسال كود المنتج</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="sendCodeForm">
                    <input type="hidden" name="action" value="send-order-code">
                    <input type="hidden" id="send_code_order_id" name="order_id">
                    
                    <div class="mb-3">
                        <p>الطلب: <strong id="send_code_order_number"></strong></p>
                        <label for="code" class="form-label">الكود <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" required>
                        <small class="text-muted">أدخل كود المنتج المراد إرساله للمستخدم</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <small>سيتم إرسال الكود للمستخدم عبر الإشعارات الداخلية</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="sendCodeForm" class="btn btn-primary">إرسال الكود</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال إضافة منتج -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إضافة منتج جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="addProductForm">
                    <input type="hidden" name="action" value="add-product">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="product_name" class="form-label">اسم المنتج <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="product_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="product_category" class="form-label">الفئة <span class="text-danger">*</span></label>
                                <select class="form-control" id="product_category" name="category" required>
                                    <?php foreach ($categories as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="product_description" class="form-label">وصف المنتج</label>
                        <textarea class="form-control" id="product_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="product_points" class="form-label">النقاط المطلوبة <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="product_points" name="points_required" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="product_stock" class="form-label">المخزون</label>
                                <input type="number" class="form-control" id="product_stock" name="stock" value="-1" min="-1">
                                <small class="text-muted">-1 يعني مخزون غير محدود</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="product_image" class="form-label">صورة المنتج</label>
                                <input type="file" class="form-control" id="product_image" name="image" accept="image/*">
                                <small class="text-muted">الحجم الأمثل: 400×400 بكسل</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="product_order" class="form-label">ترتيب الظهور</label>
                                <input type="number" class="form-control" id="product_order" name="sort_order" value="0" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="product_active" name="is_active" checked>
                                    <label class="form-check-label" for="product_active">
                                        المنتج نشط
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="preview-section">
                        <label class="form-label">معاينة الصورة</label>
                        <div class="image-preview" id="imagePreview">
                            <div class="preview-placeholder">
                                <i class="fas fa-image"></i>
                                <span>سيتم عرض معاينة الصورة هنا</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="addProductForm" class="btn btn-primary">إضافة المنتج</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال تعديل المنتج -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تعديل المنتج</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="editProductForm">
                    <input type="hidden" name="action" value="edit-product">
                    <input type="hidden" id="edit_product_id" name="id">
                    
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
                <button type="submit" form="editProductForm" class="btn btn-primary">حفظ التعديلات</button>
            </div>
        </div>
    </div>
</div>

<script>
// معاينة الصورة عند رفعها
document.getElementById('product_image')?.addEventListener('change', function() {
    const preview = document.getElementById('imagePreview');
    const file = this.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded">`;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = `
            <div class="preview-placeholder">
                <i class="fas fa-image"></i>
                <span>سيتم عرض معاينة الصورة هنا</span>
            </div>
        `;
    }
});

// إظهار تفاصيل الطلب
async function showOrderDetails(orderId) {
    try {
        const response = await fetch(`/api/admin?action=get-order-details&order_id=${orderId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const order = data.data;
            const statusName = order_statuses[order.status] || order.status;
            const categoryName = categories[order.product_category] || order.product_category;
            
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>معلومات الطلب</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>رقم الطلب:</strong></td>
                                <td>#${order.order_number}</td>
                            </tr>
                            <tr>
                                <td><strong>التاريخ:</strong></td>
                                <td>${order.created_at}</td>
                            </tr>
                            <tr>
                                <td><strong>الحالة:</strong></td>
                                <td><span class="badge bg-${order.status === 'completed' ? 'success' : order.status === 'pending' ? 'warning' : 'info'}">${statusName}</span></td>
                            </tr>
                            ${order.sent_at ? `
                            <tr>
                                <td><strong>تاريخ الإرسال:</strong></td>
                                <td>${order.sent_at}</td>
                            </tr>
                            ` : ''}
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>معلومات المستخدم</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>الاسم:</strong></td>
                                <td>${order.user_name}</td>
                            </tr>
                            <tr>
                                <td><strong>البريد:</strong></td>
                                <td>${order.user_email}</td>
                            </tr>
                            <tr>
                                <td><strong>الهاتف:</strong></td>
                                <td>${order.user_phone}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>تفاصيل المنتج</h6>
                        <div class="d-flex align-items-center bg-light p-3 rounded">
                            ${order.product_image ? `
                                <img src="/uploads/images/${order.product_image}" class="product-thumb-lg me-3" alt="${order.product_name}">
                            ` : ''}
                            <div>
                                <h5 class="mb-1">${order.product_name}</h5>
                                <p class="text-muted mb-2">${order.product_description}</p>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-primary">${categoryName}</span>
                                    <span class="badge bg-warning">${order.points_paid} نقطة</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${order.details ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>تفاصيل إضافية</h6>
                        <div class="bg-light p-3 rounded">
                            <pre class="mb-0">${order.details}</pre>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                ${order.admin_notes ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>ملاحظات الأدمن</h6>
                        <div class="alert alert-info">
                            ${order.admin_notes}
                        </div>
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('orderDetailsContent').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading order details:', error);
        document.getElementById('orderDetailsContent').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> حدث خطأ في تحميل التفاصيل
            </div>
        `;
    }
}

// تحديث حالة الطلب
function setUpdateStatus(orderId, currentStatus, orderNumber) {
    document.getElementById('update_status_order_id').value = orderId;
    document.getElementById('update_status_order_number').textContent = orderNumber;
    document.getElementById('update_status').value = currentStatus;
}

// إرسال الكود
function setSendCode(orderId, orderNumber) {
    document.getElementById('send_code_order_id').value = orderId;
    document.getElementById('send_code_order_number').textContent = orderNumber;
}

// تعديل المنتج
async function editProduct(productId) {
    try {
        const response = await fetch(`/api/admin?action=get-product&id=${productId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const product = data.data;
            
            document.getElementById('edit_product_id').value = product.id;
            
            let html = `
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">اسم المنتج <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" value="${product.name}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">الفئة <span class="text-danger">*</span></label>
                            <select class="form-control" name="category" required>
                                ${Object.entries(categories).map(([value, label]) => `
                                    <option value="${value}" ${product.category === value ? 'selected' : ''}>${label}</option>
                                `).join('')}
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">وصف المنتج</label>
                    <textarea class="form-control" name="description" rows="3">${product.description || ''}</textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">النقاط المطلوبة <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="points_required" value="${product.points_required}" min="1" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">المخزون</label>
                            <input type="number" class="form-control" name="stock" value="${product.stock}" min="-1">
                            <small class="text-muted">-1 يعني مخزون غير محدود</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">صورة المنتج</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                            ${product.image ? `
                                <div class="mt-2">
                                    <img src="/uploads/images/${product.image}" class="img-thumbnail" style="max-height: 100px;">
                                    <small class="d-block text-muted">الصورة الحالية</small>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label">ترتيب الظهور</label>
                            <input type="number" class="form-control" name="sort_order" value="${product.sort_order}" min="0">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_active" id="edit_product_active" ${product.is_active ? 'checked' : ''}>
                                <label class="form-check-label" for="edit_product_active">
                                    المنتج نشط
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.querySelector('#editProductForm .modal-body').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading product:', error);
        document.querySelector('#editProductForm .modal-body').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> حدث خطأ في تحميل بيانات المنتج
            </div>
        `;
    }
}

// حذف المنتج
function deleteProduct(productId, productName) {
    if (confirm(`هل أنت متأكد من حذف المنتج "${productName}"؟`)) {
        window.location.href = `/admin/store?action=delete-product&id=${productId}`;
    }
}

// البحث الفوري في الطلبات
let searchTimeout;
document.querySelector('input[name="search"]')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.closest('form').submit();
    }, 500);
});
</script>

<style>
.product-thumb {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
}

.product-thumb-lg {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    object-fit: cover;
}

.product-card {
    transition: var(--transition);
    border: 1px solid var(--border-color);
    height: 100%;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.product-image {
    height: 200px;
    object-fit: cover;
}

.product-image-placeholder {
    height: 200px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
}

.preview-placeholder {
    height: 200px;
    background-color: var(--light-color);
    border: 2px dashed var(--border-color);
    border-radius: var(--border-radius);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--gray-color);
}

.preview-placeholder i {
    font-size: 3rem;
    margin-bottom: 10px;
}

.image-preview img {
    max-height: 200px;
    width: auto;
    border-radius: var(--border-radius);
}

.nav-tabs .nav-link {
    color: var(--dark-color);
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.stock-info {
    background-color: var(--light-color);
    padding: 8px 12px;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
}
</style>

<?php
include 'includes/footer.php';
?>