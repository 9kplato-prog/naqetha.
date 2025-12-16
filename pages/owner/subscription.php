<?php
// التحقق من صلاحيات صاحب المطعم
if (!isset($user) || $user->getRole() !== 'restaurant_owner') {
    header('Location: /login');
    exit;
}

$page_title = 'الاشتراك والترقية';
include 'includes/header.php';

// الحصول على معلومات اشتراك المستخدم
$sql = "SELECT rp.*, r.name as restaurant_name 
        FROM restaurant_permissions rp 
        JOIN restaurants r ON rp.restaurant_id = r.id 
        WHERE r.owner_id = ?";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();
$subscription = $result->fetch_assoc();

// خطط الاشتراك المتاحة
$plans = [
    'basic' => [
        'name' => 'الخطة الأساسية',
        'price' => 0,
        'features' => [
            'مهام محدودة (10 مهام نشطة)',
            'خصم حتى 30%',
            'ظهور عادي في النتائج',
            'تقارير أساسية',
            'دعم عبر البريد الإلكتروني'
        ]
    ],
    'pro' => [
        'name' => 'الخطة الاحترافية',
        'price' => 199,
        'features' => [
            'مهام غير محدودة',
            'خصم حتى 50%',
            'أولوية في ظهور النتائج',
            'تمييز المطعم',
            'تقارير متقدمة',
            'دعم فني سريع',
            'إحصائيات مفصلة'
        ]
    ],
    'enterprise' => [
        'name' => 'الخطة المؤسسية',
        'price' => 499,
        'price_period' => 'شهري',
        'features' => [
            'مهام غير محدودة',
            'خصم حتى 70%',
            'أولوية قصوى في النتائج',
            'تمييز المطعم بشكل دائم',
            'تقارير مفصلة ومخصصة',
            'دعم فني 24/7',
            'إعلانات متميزة',
            'تحليلات متقدمة',
            'واجهة API مخصصة'
        ]
    ]
];
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-crown"></i> الاشتراك والترقية</h2>
    
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
    
    <!-- حالة الاشتراك الحالية -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">اشتراكك الحالي</h5>
                        <span class="badge bg-primary"><?php echo $subscription ? 'مفعل' : 'غير مشترك'; ?></span>
                    </div>
                    
                    <?php if ($subscription): ?>
                    <div class="current-plan">
                        <div class="plan-header mb-4">
                            <h4>الخطة الأساسية</h4>
                            <p class="text-muted">مجانية</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="plan-feature mb-3">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <span>الحد الأقصى للمهام: <strong><?php echo $subscription['max_tasks']; ?> مهمة نشطة</strong></span>
                                </div>
                                
                                <div class="plan-feature mb-3">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <span>الحد الأقصى للخصم: <strong><?php echo $subscription['max_discount']; ?>%</strong></span>
                                </div>
                                
                                <div class="plan-feature mb-3">
                                    <i class="fas <?php echo $subscription['can_feature'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'; ?>"></i>
                                    <span>تمييز المطعم: <strong><?php echo $subscription['can_feature'] ? 'مفعل' : 'غير مفعل'; ?></strong></span>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="plan-feature mb-3">
                                    <i class="fas <?php echo $subscription['can_priority'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'; ?>"></i>
                                    <span>أولوية الظهور: <strong><?php echo $subscription['can_priority'] ? 'مفعل' : 'غير مفعل'; ?></strong></span>
                                </div>
                                
                                <div class="plan-feature mb-3">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <span>التقارير: <strong>أساسية</strong></span>
                                </div>
                                
                                <div class="plan-feature mb-3">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <span>الدعم: <strong>عبر البريد الإلكتروني</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-crown fa-2x text-muted mb-3"></i>
                        <p>ليس لديك اشتراك نشط حالياً</p>
                        <p class="text-muted">اختر إحدى الخطط أدناه للبدء</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- خطط الاشتراك -->
    <h3 class="section-title mt-5 mb-4">خطط الاشتراك المتاحة</h3>
    
    <div class="row">
        <?php foreach ($plans as $key => $plan): ?>
        <div class="col-lg-4 mb-4">
            <div class="card pricing-card <?php echo $key === 'pro' ? 'popular' : ''; ?>">
                <?php if ($key === 'pro'): ?>
                <div class="popular-badge">الأكثر شعبية</div>
                <?php endif; ?>
                
                <div class="card-body">
                    <div class="pricing-header text-center mb-4">
                        <h4 class="card-title"><?php echo $plan['name']; ?></h4>
                        <div class="price">
                            <?php if ($plan['price'] == 0): ?>
                            <h2>مجاناً</h2>
                            <?php else: ?>
                            <h2><?php echo number_format($plan['price']); ?> <small>ريال</small></h2>
                            <p class="text-muted"><?php echo $plan['price_period'] ?? 'مرة واحدة'; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <ul class="features-list">
                        <?php foreach ($plan['features'] as $feature): ?>
                        <li>
                            <i class="fas fa-check"></i>
                            <span><?php echo $feature; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div class="text-center mt-4">
                        <?php if ($key === 'basic'): ?>
                        <button class="btn btn-outline-primary w-100" disabled>
                            <i class="fas fa-check"></i> الخطة الحالية
                        </button>
                        <?php else: ?>
                        <button class="btn btn-primary w-100" onclick="selectPlan('<?php echo $key; ?>')">
                            <i class="fas fa-shopping-cart"></i> <?php echo $plan['price'] == 0 ? 'تفعيل مجاناً' : 'الاشتراك الآن'; ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- مقارنة الخطط -->
    <div class="row mt-5">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">مقارنة بين الخطط</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>الميزة</th>
                                    <th>الخطة الأساسية</th>
                                    <th>الخطة الاحترافية</th>
                                    <th>الخطة المؤسسية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>عدد المهام النشطة</td>
                                    <td>10</td>
                                    <td>غير محدود</td>
                                    <td>غير محدود</td>
                                </tr>
                                
                                <tr>
                                    <td>الحد الأقصى للخصم</td>
                                    <td>30%</td>
                                    <td>50%</td>
                                    <td>70%</td>
                                </tr>
                                
                                <tr>
                                    <td>تمييز المطعم</td>
                                    <td><i class="fas fa-times text-danger"></i></td>
                                    <td><i class="fas fa-check text-success"></i></td>
                                    <td><i class="fas fa-check text-success"></i></td>
                                </tr>
                                
                                <tr>
                                    <td>أولوية الظهور</td>
                                    <td><i class="fas fa-times text-danger"></i></td>
                                    <td><i class="fas fa-check text-success"></i></td>
                                    <td><i class="fas fa-check text-success"></i></td>
                                </tr>
                                
                                <tr>
                                    <td>التقارير</td>
                                    <td>أساسية</td>
                                    <td>متقدمة</td>
                                    <td>مخصصة</td>
                                </tr>
                                
                                <tr>
                                    <td>الدعم الفني</td>
                                    <td>بريد إلكتروني</td>
                                    <td>سريع</td>
                                    <td>24/7</td>
                                </tr>
                                
                                <tr>
                                    <td>واجهة API</td>
                                    <td><i class="fas fa-times text-danger"></i></td>
                                    <td><i class="fas fa-times text-danger"></i></td>
                                    <td><i class="fas fa-check text-success"></i></td>
                                </tr>
                                
                                <tr>
                                    <td>التكلفة</td>
                                    <td>مجاناً</td>
                                    <td>199 ريال</td>
                                    <td>499 ريال/شهر</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- الأسئلة الشائعة -->
    <div class="row mt-5">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">الأسئلة الشائعة</h5>
                    
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    كيف يمكنني ترقية خطتي؟
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    يمكنك ترقية خطتك بالنقر على زر "الاشتراك الآن" في الخطة المرغوبة وإتمام عملية الدفع.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    هل يمكنني التراجع عن الاشتراك؟
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    نعم، يمكنك التراجع عن الاشتراك في أي وقت. سيتم خصم المبلغ المتبقي من الفترة الحالية.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    ما هي طرق الدفع المتاحة؟
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    نقبل الدفع عبر بطاقات الائتمان (Visa/Mastercard) والتحويل البنكي وحسابات PayPal.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    هل هناك فترة تجريبية؟
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    نقدم فترة تجريبية مجانية لمدة 7 أيام للخطة الاحترافية والمؤسسية.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال تأكيد الاشتراك -->
<div class="modal fade" id="subscribeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد الاشتراك</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="planDetails"></div>
                
                <form id="paymentForm" class="mt-4">
                    <div class="mb-3">
                        <label class="form-label">طريقة الدفع</label>
                        <div class="payment-methods">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="credit_card" checked>
                                <label class="form-check-label" for="credit_card">
                                    <i class="fab fa-cc-visa"></i> بطاقة ائتمان
                                </label>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="bank_transfer" value="bank_transfer">
                                <label class="form-check-label" for="bank_transfer">
                                    <i class="fas fa-university"></i> تحويل بنكي
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="paypal" value="paypal">
                                <label class="form-check-label" for="paypal">
                                    <i class="fab fa-paypal"></i> PayPal
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div id="creditCardForm">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="card_number" class="form-label">رقم البطاقة</label>
                                <input type="text" class="form-control" id="card_number" placeholder="1234 5678 9012 3456">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="card_expiry" class="form-label">تاريخ الانتهاء</label>
                                <input type="text" class="form-control" id="card_expiry" placeholder="MM/YY">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="card_cvc" class="form-label">رمز CVC</label>
                                <input type="text" class="form-control" id="card_cvc" placeholder="123">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="terms_agreement" required>
                        <label class="form-check-label" for="terms_agreement">
                            أوافق على <a href="/terms" target="_blank">شروط الخدمة</a> و 
                            <a href="/privacy" target="_blank">سياسة الخصوصية</a>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="processPayment()">إتمام الدفع</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedPlan = '';

// اختيار خطة
function selectPlan(planKey) {
    selectedPlan = planKey;
    const plan = <?php echo json_encode($plans); ?>[planKey];
    
    let html = `
        <div class="plan-confirmation">
            <h6>تأكيد اشتراك الخطة:</h6>
            <h4 class="text-primary">${plan.name}</h4>
            ${plan.price > 0 ? `
            <p class="mb-2">السعر: <strong>${plan.price.toLocaleString()} ريال</strong></p>
            <p class="text-muted">${plan.price_period || 'مرة واحدة'}</p>
            ` : '<p class="text-success"><strong>مجاناً</strong></p>'}
            
            <div class="mt-3">
                <h6>المميزات:</h6>
                <ul class="list-unstyled">
    `;
    
    plan.features.forEach(feature => {
        html += `<li><i class="fas fa-check text-success me-2"></i> ${feature}</li>`;
    });
    
    html += `
                </ul>
            </div>
        </div>
    `;
    
    document.getElementById('planDetails').innerHTML = html;
    
    const modal = new bootstrap.Modal(document.getElementById('subscribeModal'));
    modal.show();
}

// إظهار/إخفاء تفاصيل بطاقة الائتمان
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const creditCardForm = document.getElementById('creditCardForm');
        creditCardForm.style.display = this.value === 'credit_card' ? 'block' : 'none';
    });
});

// معالجة الدفع
function processPayment() {
    const termsAgreement = document.getElementById('terms_agreement');
    
    if (!termsAgreement.checked) {
        showNotification('خطأ', 'يجب الموافقة على الشروط والأحكام', 'error');
        return;
    }
    
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    
    if (paymentMethod === 'credit_card') {
        const cardNumber = document.getElementById('card_number').value;
        const cardExpiry = document.getElementById('card_expiry').value;
        const cardCvc = document.getElementById('card_cvc').value;
        
        if (!cardNumber || !cardExpiry || !cardCvc) {
            showNotification('خطأ', 'يرجى ملء جميع تفاصيل بطاقة الائتمان', 'error');
            return;
        }
    }
    
    // إظهار مؤشر التحميل
    const submitBtn = document.querySelector('#subscribeModal .btn-primary');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري المعالجة...';
    submitBtn.disabled = true;
    
    // محاكاة عملية الدفع
    setTimeout(() => {
        fetch('/api/owner?action=subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `plan=${selectedPlan}&payment_method=${paymentMethod}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification('نجاح', 'تم تفعيل الاشتراك بنجاح!');
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('subscribeModal'));
                modal.hide();
                
                // تحديث الصفحة بعد 2 ثانية
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showNotification('خطأ', data.message, 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error processing payment:', error);
            showNotification('خطأ', 'حدث خطأ أثناء معالجة الدفع', 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }, 2000);
}
</script>

<style>
.pricing-card {
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius);
    transition: var(--transition);
    position: relative;
    height: 100%;
}

.pricing-card:hover {
    transform: translateY(-10px);
    border-color: var(--primary-color);
}

.pricing-card.popular {
    border-color: var(--primary-color);
    box-shadow: 0 10px 30px rgba(var(--primary-color-rgb), 0.2);
}

.popular-badge {
    position: absolute;
    top: -10px;
    right: 20px;
    background-color: var(--primary-color);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.pricing-header {
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.pricing-header .price h2 {
    color: var(--primary-color);
    margin-bottom: 5px;
}

.pricing-header .price small {
    font-size: 1rem;
    color: var(--gray-color);
}

.features-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.features-list li {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}

.features-list li:last-child {
    border-bottom: none;
}

.features-list li i {
    color: var(--success-color);
    margin-right: 10px;
}

.plan-feature {
    display: flex;
    align-items: center;
    gap: 10px;
}

.plan-feature i {
    width: 20px;
}

.payment-methods {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 15px;
}

.payment-methods .form-check-label {
    display: flex;
    align-items: center;
    gap: 10px;
}

.plan-confirmation {
    text-align: center;
    padding: 20px;
    background-color: var(--light-color);
    border-radius: var(--border-radius);
}

.plan-confirmation h4 {
    margin: 10px 0;
}

.plan-confirmation ul li {
    text-align: right;
    margin-bottom: 5px;
}

.table th {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}

.accordion-button {
    font-weight: 600;
    color: var(--dark-color);
}

.accordion-button:not(.collapsed) {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-color);
}
</style>

<?php
include 'includes/footer.php';
?>