<?php
// التحقق من صلاحيات العضو
if (!isset($user) || !in_array($user->getRole(), ['member', 'moderator'])) {
    header('Location: /login');
    exit;
}

$page_title = 'متجر النقاط';
$page_scripts = ['member-store.js'];

include 'includes/header.php';

// الحصول على رصيد النقاط
$points = $user->getPoints();
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-gift"></i> متجر النقاط</h2>
    
    <!-- رصيد النقاط -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="points-icon me-3">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo formatPoints($points); ?> نقطة</h3>
                            <p class="text-muted mb-0">رصيدك الحالي من النقاط</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-primary" onclick="showPointsHistory()">
                        <i class="fas fa-history"></i> سجل النقاط
                    </button>
                    <button class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#redeemModal">
                        <i class="fas fa-shopping-cart"></i> استبدال النقاط
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- فلترة المنتجات -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">الفئة</label>
                    <select class="form-select" id="categoryFilter" onchange="filterProducts()">
                        <option value="all">جميع الفئات</option>
                        <!-- سيتم تحميل الفئات هنا ديناميكياً -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الترتيب حسب</label>
                    <select class="form-select" id="sortFilter" onchange="filterProducts()">
                        <option value="points_asc">الأقل نقاطاً</option>
                        <option value="points_desc">الأكثر نقاطاً</option>
                        <option value="name_asc">حسب الاسم (أ-ي)</option>
                        <option value="name_desc">حسب الاسم (ي-أ)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">بحث</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchFilter" placeholder="ابحث عن منتج..." onkeyup="filterProducts()">
                        <button class="btn btn-primary" onclick="filterProducts()">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">الإجراء</label>
                    <button class="btn btn-info w-100" onclick="viewMyOrders()">
                        <i class="fas fa-box"></i> طلباتي
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- المنتجات -->
    <div id="productsList">
        <div class="row">
            <div class="col-12 text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">جاري التحميل...</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- طلباتي -->
    <div class="card mt-4 d-none" id="myOrdersSection">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">طلبات الاستبدال</h5>
                <button class="btn btn-sm btn-secondary" onclick="hideMyOrders()">
                    <i class="fas fa-times"></i> إغلاق
                </button>
            </div>
            <div id="myOrdersList">
                <!-- سيتم تحميل الطلبات هنا ديناميكياً -->
            </div>
        </div>
    </div>
</div>

<!-- مودال استبدال النقاط -->
<div class="modal fade" id="redeemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">استبدال النقاط</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="redeemForm">
                    <div class="mb-3">
                        <label class="form-label">اختر المنتج <span class="text-danger">*</span></label>
                        <select class="form-control" id="productSelect" required>
                            <option value="">اختر منتجاً للاستبدال</option>
                            <!-- سيتم تحميل المنتجات هنا ديناميكياً -->
                        </select>
                    </div>
                    
                    <div id="productDetails" class="mb-3">
                        <!-- سيتم عرض تفاصيل المنتج هنا -->
                    </div>
                    
                    <div id="extraDetails" class="mb-3">
                        <!-- سيتم عرض تفاصيل إضافية حسب نوع المنتج -->
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <small>سيكون طلبك قيد المراجعة وسيتم إرسال الكود لك عند الموافقة عليه</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="submitRedeem()">
                    <i class="fas fa-check"></i> تأكيد الاستبدال
                </button>
            </div>
        </div>
    </div>
</div>

<!-- مودال سجل النقاط -->
<div class="modal fade" id="pointsHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">سجل النقاط</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="pointsHistoryContent">
                    <!-- سيتم تحميل سجل النقاط هنا ديناميكياً -->
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
// تحميل البيانات عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    loadCategories();
    loadProductsForRedeem();
});

// تحميل المنتجات
async function loadProducts() {
    try {
        const category = document.getElementById('categoryFilter').value;
        const sort = document.getElementById('sortFilter').value;
        const search = document.getElementById('searchFilter').value;
        
        const response = await fetch(`/api/store?action=get-products&category=${category !== 'all' ? category : ''}&search=${search}&sort=${sort}&limit=12`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const products = data.data;
            let html = '';
            
            if (products.length === 0) {
                html = `
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-gift fa-3x text-muted mb-3"></i>
                        <h5>لا توجد منتجات متاحة حالياً</h5>
                        <p class="text-muted">عد لاحقاً للتحقق من المنتجات الجديدة</p>
                    </div>
                `;
            } else {
                products.forEach(product => {
                    const canRedeem = <?php echo $points; ?> >= product.points_required;
                    const stockStatus = product.stock === 0 ? 'نفذ من المخزون' : 
                                       product.stock > 0 ? `${product.stock} متبقي` : 'غير محدود';
                    
                    html += `
                        <div class="col-md-4 col-lg-3 mb-4">
                            <div class="product-card h-100">
                                <div class="product-image">
                                    ${product.image ? 
                                        `<img src="/uploads/images/${product.image}" alt="${product.name}">` :
                                        `<div class="image-placeholder">
                                            <i class="fas fa-gift"></i>
                                        </div>`
                                    }
                                    <div class="product-points">
                                        <span class="badge bg-primary">${product.points_required} نقطة</span>
                                    </div>
                                </div>
                                
                                <div class="product-body">
                                    <h5 class="product-title">${product.name}</h5>
                                    <p class="product-category">
                                        ${getCategoryName(product.category)}
                                    </p>
                                    <p class="product-description">
                                        ${product.description ? product.description.substring(0, 80) + '...' : ''}
                                    </p>
                                    
                                    <div class="product-meta">
                                        <small class="text-muted">${stockStatus}</small>
                                    </div>
                                </div>
                                
                                <div class="product-footer">
                                    ${canRedeem ? 
                                        `<button class="btn btn-primary w-100" onclick="redeemProduct(${product.id})">
                                            <i class="fas fa-shopping-cart"></i> استبدال
                                        </button>` :
                                        `<button class="btn btn-outline-secondary w-100" disabled>
                                            <i class="fas fa-lock"></i> غير كافٍ
                                        </button>`
                                    }
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            document.getElementById('productsList').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading products:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل المنتجات', 'error');
    }
}

// تحميل الفئات
async function loadCategories() {
    try {
        const response = await fetch('/api/store?action=get-categories');
        const data = await response.json();
        
        if (data.status === 'success') {
            const select = document.getElementById('categoryFilter');
            data.data.forEach(category => {
                const option = document.createElement('option');
                option.value = category.value;
                option.textContent = category.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

// تحميل المنتجات للاستبدال
async function loadProductsForRedeem() {
    try {
        const response = await fetch('/api/store?action=get-products&limit=50');
        const data = await response.json();
        
        if (data.status === 'success') {
            const select = document.getElementById('productSelect');
            select.innerHTML = '<option value="">اختر منتجاً للاستبدال</option>';
            
            data.data.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = `${product.name} - ${product.points_required} نقطة`;
                option.setAttribute('data-product', JSON.stringify(product));
                select.appendChild(option);
            });
            
            // إضافة مستمع للحدث
            select.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const product = JSON.parse(selectedOption.getAttribute('data-product'));
                    showProductDetails(product);
                } else {
                    document.getElementById('productDetails').innerHTML = '';
                    document.getElementById('extraDetails').innerHTML = '';
                }
            });
        }
    } catch (error) {
        console.error('Error loading products for redeem:', error);
    }
}

// عرض تفاصيل المنتج
function showProductDetails(product) {
    let html = `
        <div class="product-details-preview p-3 border rounded">
            <div class="row">
                <div class="col-md-4">
                    ${product.image ? 
                        `<img src="/uploads/images/${product.image}" class="img-fluid rounded" alt="${product.name}">` :
                        `<div class="image-placeholder-large">
                            <i class="fas fa-gift fa-3x"></i>
                        </div>`
                    }
                </div>
                <div class="col-md-8">
                    <h5>${product.name}</h5>
                    <p class="text-muted">${getCategoryName(product.category)}</p>
                    <p>${product.description || 'لا يوجد وصف'}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="text-primary mb-0">${product.points_required} نقطة</h4>
                        <span class="badge ${product.stock === 0 ? 'bg-danger' : 'bg-success'}">
                            ${product.stock === 0 ? 'نفذ' : product.stock > 0 ? `${product.stock} متبقي` : 'متوفر'}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('productDetails').innerHTML = html;
    
    // عرض تفاصيل إضافية حسب نوع المنتج
    let extraHtml = '';
    switch (product.category) {
        case 'mobile_balance':
            extraHtml = `
                <div class="mb-3">
                    <label class="form-label">رقم الجوال <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="mobile_number" placeholder="05XXXXXXXX" required>
                    <small class="text-muted">سيتم إرسال الرصيد إلى هذا الرقم</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">شركة الاتصالات <span class="text-danger">*</span></label>
                    <select class="form-control" id="mobile_operator" required>
                        <option value="">اختر الشركة</option>
                        <option value="stc">STC</option>
                        <option value="mobily">موبايلي</option>
                        <option value="zain">زين</option>
                    </select>
                </div>
            `;
            break;
            
        case 'coupons':
            extraHtml = `
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="coupon_email" placeholder="example@gmail.com" required>
                    <small class="text-muted">سيتم إرسال الكوبون إلى هذا البريد</small>
                </div>
            `;
            break;
            
        case 'bank_transfer':
            extraHtml = `
                <div class="mb-3">
                    <label class="form-label">رقم الحساب البنكي <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="bank_account" placeholder="SAXXXXXXXXXXXXXX" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">اسم البنك <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="bank_name" placeholder="البنك الأهلي السعودي" required>
                </div>
            `;
            break;
            
        default:
            extraHtml = `
                <div class="mb-3">
                    <label class="form-label">تفاصيل إضافية</label>
                    <textarea class="form-control" id="extra_info" rows="3" placeholder="أي معلومات إضافية..."></textarea>
                </div>
            `;
    }
    
    document.getElementById('extraDetails').innerHTML = extraHtml;
}

// تصفية المنتجات
function filterProducts() {
    loadProducts();
}

// عرض طلباتي
async function viewMyOrders() {
    document.getElementById('myOrdersSection').classList.remove('d-none');
    document.getElementById('productsList').classList.add('d-none');
    
    try {
        const response = await fetch('/api/store?action=get-user-orders');
        const data = await response.json();
        
        if (data.status === 'success') {
            const orders = data.data;
            let html = '';
            
            if (orders.length === 0) {
                html = `
                    <div class="text-center py-4">
                        <i class="fas fa-box-open fa-2x text-muted mb-3"></i>
                        <p>لا توجد طلبات سابقة</p>
                    </div>
                `;
            } else {
                html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>رقم الطلب</th><th>المنتج</th><th>النقاط</th><th>الحالة</th><th>التاريخ</th><th>الإجراءات</th></tr></thead><tbody>';
                
                orders.forEach(order => {
                    const statusBadge = {
                        'pending': 'warning',
                        'processing': 'info',
                        'completed': 'success',
                        'cancelled': 'danger'
                    }[order.status];
                    
                    const statusText = {
                        'pending': 'قيد المراجعة',
                        'processing': 'قيد المعالجة',
                        'completed': 'مكتمل',
                        'cancelled': 'ملغى'
                    }[order.status];
                    
                    html += `
                        <tr>
                            <td>${order.order_number}</td>
                            <td>${order.product_name}</td>
                            <td>${order.points_paid}</td>
                            <td><span class="badge bg-${statusBadge}">${statusText}</span></td>
                            <td>${order.created_at}</td>
                            <td>
                                ${order.code ? 
                                    `<button class="btn btn-sm btn-success" onclick="showOrderCode('${order.code}')">
                                        <i class="fas fa-key"></i> عرض الكود
                                    </button>` : 
                                    ''
                                }
                                <button class="btn btn-sm btn-info" onclick="viewOrderDetails(${order.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
            }
            
            document.getElementById('myOrdersList').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading orders:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل الطلبات', 'error');
    }
}

// إخفاء طلباتي
function hideMyOrders() {
    document.getElementById('myOrdersSection').classList.add('d-none');
    document.getElementById('productsList').classList.remove('d-none');
}

// استبدال منتج مباشرة
async function redeemProduct(productId) {
    // تحديد المنتج في القائمة
    const productSelect = document.getElementById('productSelect');
    for (let option of productSelect.options) {
        if (option.value == productId) {
            productSelect.value = productId;
            const product = JSON.parse(option.getAttribute('data-product'));
            showProductDetails(product);
            break;
        }
    }
    
    const modal = new bootstrap.Modal(document.getElementById('redeemModal'));
    modal.show();
}

// تأكيد الاستبدال
async function submitRedeem() {
    const productId = document.getElementById('productSelect').value;
    if (!productId) {
        showNotification('خطأ', 'يرجى اختيار منتج', 'error');
        return;
    }
    
    // جمع التفاصيل الإضافية
    const product = JSON.parse(document.getElementById('productSelect').selectedOptions[0].getAttribute('data-product'));
    let details = {};
    
    switch (product.category) {
        case 'mobile_balance':
            const mobileNumber = document.getElementById('mobile_number').value;
            const mobileOperator = document.getElementById('mobile_operator').value;
            if (!mobileNumber || !mobileOperator) {
                showNotification('خطأ', 'يرجى إدخال جميع بيانات الجوال', 'error');
                return;
            }
            details = { mobile_number: mobileNumber, operator: mobileOperator };
            break;
            
        case 'coupons':
            const couponEmail = document.getElementById('coupon_email').value;
            if (!couponEmail) {
                showNotification('خطأ', 'يرجى إدخال البريد الإلكتروني', 'error');
                return;
            }
            details = { email: couponEmail };
            break;
            
        case 'bank_transfer':
            const bankAccount = document.getElementById('bank_account').value;
            const bankName = document.getElementById('bank_name').value;
            if (!bankAccount || !bankName) {
                showNotification('خطأ', 'يرجى إدخال جميع بيانات البنك', 'error');
                return;
            }
            details = { bank_account: bankAccount, bank_name: bankName };
            break;
            
        default:
            const extraInfo = document.getElementById('extra_info').value;
            details = { extra_info: extraInfo };
    }
    
    try {
        const response = await fetch('/api/store?action=redeem', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}&details=${JSON.stringify(details)}`
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showNotification('نجاح', 'تم تقديم طلب الاستبدال بنجاح! سيتم مراجعة طلبك قريباً');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('redeemModal'));
            modal.hide();
            
            // تحديث رصيد النقاط
            location.reload();
        } else {
            showNotification('خطأ', data.message, 'error');
        }
    } catch (error) {
        console.error('Error redeeming:', error);
        showNotification('خطأ', 'حدث خطأ أثناء الاستبدال', 'error');
    }
}

// عرض سجل النقاط
async function showPointsHistory() {
    try {
        const response = await fetch('/api/member?action=get-points-history');
        const data = await response.json();
        
        if (data.status === 'success') {
            let html = `
                <div class="points-summary mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stat-card text-center">
                                <h3>${data.data.total_earned || 0}</h3>
                                <p>إجمالي النقاط المكتسبة</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card text-center">
                                <h3>${data.data.total_redeemed || 0}</h3>
                                <p>إجمالي النقاط المستخدمة</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card text-center">
                                <h3><?php echo formatPoints($points); ?></h3>
                                <p>الرصيد الحالي</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>النوع</th>
                                <th>المبلغ</th>
                                <th>الوصف</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            data.data.transactions.forEach(transaction => {
                const typeText = {
                    'earn': 'اكتساب',
                    'redeem': 'استبدال',
                    'withdraw': 'سحب',
                    'transfer': 'تحويل',
                    'bonus': 'مكافأة'
                }[transaction.type] || transaction.type;
                
                const typeClass = transaction.type === 'earn' ? 'text-success' : 'text-danger';
                
                html += `
                    <tr>
                        <td><span class="${typeClass}">${typeText}</span></td>
                        <td><strong>${transaction.amount}</strong></td>
                        <td>${transaction.description}</td>
                        <td>${transaction.created_at}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('pointsHistoryContent').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('pointsHistoryModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading points history:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل سجل النقاط', 'error');
    }
}

// عرض كود الطلب
function showOrderCode(code) {
    let html = `
        <div class="text-center p-4">
            <i class="fas fa-key fa-4x text-success mb-3"></i>
            <h4>كود المنتج</h4>
            <div class="code-box mt-3 p-3 border rounded">
                <h2 class="text-danger">${code}</h2>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary" onclick="copyCode('${code}')">
                    <i class="fas fa-copy"></i> نسخ الكود
                </button>
            </div>
        </div>
    `;
    
    showModal('كود المنتج', html);
}

// عرض تفاصيل الطلب
async function viewOrderDetails(orderId) {
    // يمكن إضافة تفاصيل الطلب هنا
    showNotification('قريباً', 'ستتوفر تفاصيل الطلب قريباً', 'info');
}

// دالة مساعدة للحصول على اسم الفئة
function getCategoryName(category) {
    const categories = {
        'mobile_balance': 'رصيد جوال',
        'coupons': 'كوبونات',
        'bank_transfer': 'تحويل بنكي',
        'tickets': 'تذاكر',
        'other': 'أخرى'
    };
    return categories[category] || category;
}

// عرض مودال
function showModal(title, content) {
    let html = `
        <div class="modal fade" id="customModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', html);
    const modal = new bootstrap.Modal(document.getElementById('customModal'));
    modal.show();
    
    // تنظيف المودال بعد إغلاقه
    document.getElementById('customModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}
</script>

<style>
.points-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.product-card {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
    transition: var(--transition);
    background: white;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.product-image {
    height: 150px;
    overflow: hidden;
    position: relative;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--info-color);
    font-size: 3rem;
}

.image-placeholder-large {
    width: 100%;
    height: 150px;
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--info-color);
    border-radius: var(--border-radius);
}

.product-points {
    position: absolute;
    top: 10px;
    left: 10px;
}

.product-body {
    padding: 15px;
}

.product-title {
    color: var(--dark-color);
    margin-bottom: 5px;
    font-size: 1.1rem;
    height: 2.5em;
    overflow: hidden;
}

.product-category {
    color: var(--primary-color);
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.product-description {
    color: var(--gray-color);
    font-size: 0.9rem;
    line-height: 1.5;
    height: 4.5em;
    overflow: hidden;
}

.product-meta {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border-color);
}

.product-footer {
    padding: 15px;
    background-color: var(--light-color);
}

.product-details-preview {
    background-color: var(--light-color);
}

.code-box {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-color: var(--info-color) !important;
}

.stat-card {
    padding: 20px;
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
}

.stat-card h3 {
    color: var(--primary-color);
    margin-bottom: 5px;
}

.stat-card p {
    color: var(--gray-color);
    margin-bottom: 0;
}
</style>

<?php
include 'includes/footer.php';
?>