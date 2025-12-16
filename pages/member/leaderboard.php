<?php
// التحقق من صلاحيات العضو
if (!isset($user) || !in_array($user->getRole(), ['member', 'moderator'])) {
    header('Location: /login');
    exit;
}

$page_title = 'المتصدرون';
$page_scripts = ['leaderboard.js'];

include 'includes/header.php';

// الحصول على أفضل الأعضاء هذا الأسبوع
$sql = "SELECT u.id, u.name, u.city, u.avatar, u.points, 
               COUNT(ut.id) as completed_tasks,
               DENSE_RANK() OVER (ORDER BY u.points DESC) as rank
        FROM users u
        LEFT JOIN user_tasks ut ON u.id = ut.user_id AND ut.status = 'completed' 
            AND ut.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        WHERE u.role = 'member' AND u.status = 'active'
        GROUP BY u.id
        ORDER BY u.points DESC
        LIMIT 100";

$result = $db->query($sql);
$top_users = [];
while ($row = $result->fetch_assoc()) {
    $top_users[] = $row;
}

// الحصول على ترتيب المستخدم الحالي
$sql = "SELECT COUNT(*) as position FROM users 
        WHERE role = 'member' AND status = 'active' AND points > ?";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getPoints());
$stmt->execute();
$result = $stmt->get_result();
$user_position = $result->fetch_assoc()['position'] + 1;
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-trophy"></i> المتصدرون</h2>
    
    <!-- معلومات المستخدم الحالي -->
    <div class="current-user-rank mb-4">
        <div class="rank-card">
            <div class="rank-number">#<?php echo $user_position; ?></div>
            <div class="user-info">
                <div class="user-avatar"><?php echo mb_substr($user->getName(), 0, 1, 'UTF-8'); ?></div>
                <div>
                    <h4><?php echo $user->getName(); ?></h4>
                    <p class="mb-1"><i class="fas fa-map-marker-alt"></i> <?php echo $user->getCity(); ?></p>
                    <p class="mb-0"><i class="fas fa-coins"></i> <?php echo formatPoints($user->getPoints()); ?> نقطة</p>
                </div>
            </div>
            <div class="rank-badge">
                <span class="badge bg-primary">مستوى <?php echo floor($user_position / 10) + 1; ?></span>
            </div>
        </div>
    </div>
    
    <!-- فلاتر القائمة -->
    <div class="filters mb-4">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">المدينة:</label>
                <select id="cityFilter" class="form-select" onchange="filterLeaderboard()">
                    <option value="all">جميع المدن</option>
                    <?php foreach ($cities as $city): ?>
                    <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">الفترة:</label>
                <select id="periodFilter" class="form-select" onchange="filterLeaderboard()">
                    <option value="all">جميع الأوقات</option>
                    <option value="week">هذا الأسبوع</option>
                    <option value="month">هذا الشهر</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">البحث:</label>
                <input type="text" id="searchFilter" class="form-control" placeholder="ابحث عن عضو..." onkeyup="filterLeaderboard()">
            </div>
        </div>
    </div>
    
    <!-- قائمة المتصدرين -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="80">الترتيب</th>
                            <th>العضو</th>
                            <th>المدينة</th>
                            <th>النقاط</th>
                            <th>المهام المكتملة</th>
                            <th>المستوى</th>
                        </tr>
                    </thead>
                    <tbody id="leaderboardTable">
                        <?php if (count($top_users) > 0): ?>
                            <?php foreach ($top_users as $index => $user_row): 
                                $is_current_user = $user_row['id'] == $user->getId();
                            ?>
                            <tr class="<?php echo $is_current_user ? 'current-user-row' : ''; ?>" 
                                data-city="<?php echo $user_row['city']; ?>"
                                data-points="<?php echo $user_row['points']; ?>"
                                data-name="<?php echo htmlspecialchars($user_row['name']); ?>">
                                <td>
                                    <div class="rank-cell">
                                        <?php if ($index < 3): ?>
                                            <div class="medal rank-<?php echo $index + 1; ?>">
                                                <i class="fas fa-medal"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="rank-number">#<?php echo $index + 1; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                            <?php echo mb_substr($user_row['name'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo $user_row['name']; ?></strong>
                                            <?php if ($is_current_user): ?>
                                            <span class="badge bg-primary ms-2">أنت</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $user_row['city']; ?></td>
                                <td>
                                    <div class="points-cell">
                                        <i class="fas fa-coins text-warning"></i>
                                        <span><?php echo formatPoints($user_row['points']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo $user_row['completed_tasks']; ?></td>
                                <td>
                                    <?php $level = floor(($index + 1) / 10) + 1; ?>
                                    <div class="level-badge level-<?php echo $level; ?>">
                                        مستوى <?php echo $level; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                    <p>لا توجد بيانات للمتصدرين</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- مكافآت التصنيفات -->
    <div class="rewards-section mt-4">
        <h4 class="mb-3"><i class="fas fa-gift"></i> مكافآت التصنيفات</h4>
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="reward-card">
                    <div class="reward-medal gold">
                        <i class="fas fa-medal"></i>
                    </div>
                    <h5>المركز الأول</h5>
                    <p>500 نقطة إضافية</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="reward-card">
                    <div class="reward-medal silver">
                        <i class="fas fa-medal"></i>
                    </div>
                    <h5>المركز الثاني</h5>
                    <p>300 نقطة إضافية</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="reward-card">
                    <div class="reward-medal bronze">
                        <i class="fas fa-medal"></i>
                    </div>
                    <h5>المركز الثالث</h5>
                    <p>200 نقطة إضافية</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="reward-card">
                    <div class="reward-medal">
                        <i class="fas fa-star"></i>
                    </div>
                    <h5>أعلى 10</h5>
                    <p>100 نقطة إضافية</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تصفية قائمة المتصدرين
function filterLeaderboard() {
    const city = document.getElementById('cityFilter').value;
    const search = document.getElementById('searchFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#leaderboardTable tr');
    
    rows.forEach(row => {
        const rowCity = row.getAttribute('data-city');
        const rowName = row.getAttribute('data-name').toLowerCase();
        const showCity = city === 'all' || rowCity === city;
        const showSearch = search === '' || rowName.includes(search);
        
        if (showCity && showSearch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// عند تحميل الصفحة، نحدد المدينة الحالية للمستخدم
document.addEventListener('DOMContentLoaded', function() {
    const userCity = "<?php echo $user->getCity(); ?>";
    if (userCity) {
        document.getElementById('cityFilter').value = userCity;
        filterLeaderboard();
    }
});
</script>

<style>
.current-user-row {
    background-color: rgba(var(--primary-color-rgb), 0.1) !important;
    border-left: 4px solid var(--primary-color);
}

.rank-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-radius: var(--border-radius);
}

.rank-card .rank-number {
    font-size: 2.5rem;
    font-weight: bold;
}

.rank-card .user-info {
    display: flex;
    align-items: center;
    flex: 1;
    margin: 0 20px;
}

.rank-card .user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: white;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    margin-right: 15px;
}

.rank-card .rank-badge .badge {
    font-size: 1rem;
    padding: 8px 15px;
}

.rank-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.medal {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.medal.rank-1 {
    background: linear-gradient(135deg, #ffd700, #ffa500);
    color: #fff;
}

.medal.rank-2 {
    background: linear-gradient(135deg, #c0c0c0, #808080);
    color: #fff;
}

.medal.rank-3 {
    background: linear-gradient(135deg, #cd7f32, #8b4513);
    color: #fff;
}

.points-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.points-cell i {
    font-size: 1.2rem;
}

.level-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.level-1 { background-color: #dc3545; color: white; }
.level-2 { background-color: #fd7e14; color: white; }
.level-3 { background-color: #ffc107; color: #000; }
.level-4 { background-color: #28a745; color: white; }
.level-5 { background-color: #17a2b8; color: white; }
.level-6 { background-color: #007bff; color: white; }
.level-7 { background-color: #6f42c1; color: white; }
.level-8 { background-color: #e83e8c; color: white; }
.level-9 { background-color: #20c997; color: white; }
.level-10 { background-color: #343a40; color: white; }

.reward-card {
    text-align: center;
    padding: 20px;
    background-color: var(--card-bg);
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    transition: var(--transition);
    height: 100%;
}

.reward-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow);
}

.reward-medal {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin: 0 auto 15px;
}

.reward-medal.gold {
    background: linear-gradient(135deg, #ffd700, #ffa500);
    color: #fff;
}

.reward-medal.silver {
    background: linear-gradient(135deg, #c0c0c0, #808080);
    color: #fff;
}

.reward-medal.bronze {
    background: linear-gradient(135deg, #cd7f32, #8b4513);
    color: #fff;
}

.reward-medal:not(.gold):not(.silver):not(.bronze) {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

.reward-card h5 {
    margin-bottom: 10px;
    color: var(--dark-color);
}

.reward-card p {
    color: var(--gray-color);
    margin-bottom: 0;
}
</style>

<?php
include 'includes/footer.php';
?>