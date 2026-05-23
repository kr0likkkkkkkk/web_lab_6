<?php
$db_host = 'localhost';
$db_name = 'u82192';
$db_user = 'u82192';
$db_pass = '2307509';

$auth_ok = false;

if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
        $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
            $auth_ok = true;
        }
    } catch(PDOException $e) {
        $auth_ok = false;
    }
}

if (!$auth_ok) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo '<h1>401 Требуется авторизация</h1>';
    echo '<p>Доступ только для администратора</p>';
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $delete_id = (int)$_POST['delete_id'];
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$delete_id]);
            $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$delete_id]);
            
            $message = "Запись #$delete_id успешно удалена";
        } catch(PDOException $e) {
            $error = "Ошибка удаления: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_id']) && isset($_POST['full_name'])) {
        $edit_id = (int)$_POST['edit_id'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $birth_date = $_POST['birth_date'];
        $gender = $_POST['gender'];
        $biography = trim($_POST['biography']);
        $agreed_to_contract = isset($_POST['agreed_to_contract']) ? 1 : 0;
        $languages = $_POST['languages'] ?? [];
        
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET full_name = ?, phone = ?, email = ?, birth_date = ?, 
                    gender = ?, biography = ?, agreed_to_contract = ?, is_edited = 1
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $agreed_to_contract, $edit_id]);
            
            $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$edit_id]);
            
            $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($languages as $lang_id) {
                $lang_stmt->execute([$edit_id, $lang_id]);
            }
            
            $pdo->commit();
            $message = "Запись #$edit_id успешно обновлена";
            
        } catch(PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Ошибка обновления: " . $e->getMessage();
        }
    }
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $applications = $pdo->query("
        SELECT a.*, 
               GROUP_CONCAT(pl.language_name SEPARATOR ', ') as languages
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        LEFT JOIN programming_languages pl ON al.language_id = pl.id
        GROUP BY a.id
        ORDER BY a.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $languages_list = $pdo->query("SELECT id, language_name FROM programming_languages ORDER BY language_name")->fetchAll(PDO::FETCH_ASSOC);
    
    $language_stats = $pdo->query("
        SELECT pl.id, pl.language_name, COUNT(al.application_id) as user_count
        FROM programming_languages pl
        LEFT JOIN application_languages al ON pl.id = al.language_id
        GROUP BY pl.id
        ORDER BY user_count DESC, pl.language_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $total_users = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    
} catch(PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}

$user_languages = [];
foreach ($applications as $app) {
    $stmt = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
    $stmt->execute([$app['id']]);
    $user_languages[$app['id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 24px;
        }
        
        .logout-btn {
            background: #f44336;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
        }
        
        .content {
            padding: 30px;
        }
        
        .stats-section {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .stats-section h2 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .stat-card .count {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        
        .total-users {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .total-users .count {
            font-size: 48px;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }
        
        th {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 2px;
        }
        
        .btn-edit {
            background: #4caf50;
            color: white;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow: auto;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            width: 90%;
            max-width: 700px;
            border-radius: 10px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .form-group select[multiple] {
            height: 100px;
        }
        
        .btn-save {
            background: #2196f3;
            color: white;
            padding: 10px 20px;
        }
        
        .btn-cancel {
            background: #999;
            color: white;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            th, td {
                font-size: 11px;
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Панель администратора</h1>
            <a href="logout_admin.php" class="logout-btn">Выйти</a>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message message-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message message-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="total-users">
                <div>Всего пользователей</div>
                <div class="count"><?= $total_users ?></div>
            </div>
            
            <div class="stats-section">
                <h2>Статистика по языкам программирования</h2>
                <div class="stats-grid">
                    <?php foreach ($language_stats as $stat): ?>
                        <div class="stat-card">
                            <h3><?= htmlspecialchars($stat['language_name']) ?></h3>
                            <div class="count"><?= $stat['user_count'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <h2>Все пользователи</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Дата рожд.</th>
                        <th>Пол</th>
                        <th>Языки</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?= $app['id'] ?></td>
                            <td><?= htmlspecialchars($app['full_name']) ?></td>
                            <td><?= htmlspecialchars($app['phone']) ?></td>
                            <td><?= htmlspecialchars($app['email']) ?></td>
                            <td><?= $app['birth_date'] ?></td>
                            <td>
                                <?php
                                    $genders = ['male' => 'Мужской', 'female' => 'Женский', 'other' => 'Другой'];
                                    echo $genders[$app['gender']] ?? $app['gender'];
                                ?>
                            </td>
                            <td><?= htmlspecialchars($app['languages'] ?? '-') ?></td>
                            <td>
                                <button class="btn btn-edit" onclick="openEditModal(<?= $app['id'] ?>)">Редактировать</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить запись #<?= $app['id'] ?>?')">
                                    <input type="hidden" name="delete_id" value="<?= $app['id'] ?>">
                                    <button type="submit" class="btn btn-delete">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h3>Редактирование пользователя</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="form-group">
                    <label>ФИО *</label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>
                
                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="tel" name="phone" id="edit_phone" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                
                <div class="form-group">
                    <label>Дата рождения *</label>
                    <input type="date" name="birth_date" id="edit_birth_date" required>
                </div>
                
                <div class="form-group">
                    <label>Пол *</label>
                    <select name="gender" id="edit_gender" required>
                        <option value="male">Мужской</option>
                        <option value="female">Женский</option>
                        <option value="other">Другой</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Любимые языки программирования *</label>
                    <select name="languages[]" multiple id="edit_languages" size="6">
                        <?php foreach ($languages_list as $lang): ?>
                            <option value="<?= $lang['id'] ?>"><?= htmlspecialchars($lang['language_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="biography" id="edit_biography" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="agreed_to_contract" value="1" id="edit_agreed">
                        Я ознакомлен(а) с условиями контракта
                    </label>
                </div>
                
                <button type="submit" class="btn btn-save">Сохранить изменения</button>
                <button type="button" class="btn btn-cancel" onclick="closeEditModal()">Отмена</button>
            </form>
        </div>
    </div>
    
    <script>
        const userLanguages = <?php echo json_encode($user_languages); ?>;
        const applicationsData = <?php
            $data = [];
            foreach ($applications as $app) {
                $data[$app['id']] = [
                    'full_name' => $app['full_name'],
                    'phone' => $app['phone'],
                    'email' => $app['email'],
                    'birth_date' => $app['birth_date'],
                    'gender' => $app['gender'],
                    'biography' => $app['biography'] ?? '',
                    'agreed_to_contract' => $app['agreed_to_contract']
                ];
            }
            echo json_encode($data);
        ?>;
        
        function openEditModal(id) {
            const data = applicationsData[id];
            if (data) {
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_full_name').value = data.full_name;
                document.getElementById('edit_phone').value = data.phone;
                document.getElementById('edit_email').value = data.email;
                document.getElementById('edit_birth_date').value = data.birth_date;
                document.getElementById('edit_gender').value = data.gender;
                document.getElementById('edit_biography').value = data.biography || '';
                document.getElementById('edit_agreed').checked = data.agreed_to_contract == 1;
                
                const select = document.getElementById('edit_languages');
                const selectedLangs = userLanguages[id] || [];
                for (let i = 0; i < select.options.length; i++) {
                    select.options[i].selected = selectedLangs.includes(parseInt(select.options[i].value));
                }
                
                document.getElementById('editModal').style.display = 'block';
            }
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
