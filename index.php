<?php
session_start();

$db_host = 'localhost';
$db_name = 'u82192';       
$db_user = 'u82192';        
$db_pass = '2307509'; 

$languages = [];
$form_data = [];
$errors = [];
$auth_message = '';
$edit_id = null;

if (isset($_SESSION['edit_id'])) {
    $edit_id = $_SESSION['edit_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_COOKIE['form_data']) && !isset($_GET['success']) && !$edit_id) {
        $form_data = json_decode($_COOKIE['form_data'], true) ?: [];
    }
    if (isset($_SESSION['old'])) {
        $form_data = array_merge($form_data, $_SESSION['old']);
        unset($_SESSION['old']);
    }
}

if (isset($_COOKIE['errors'])) {
    $errors = json_decode($_COOKIE['errors'], true) ?: [];
    setcookie('errors', '', time() - 3600, '/');
}
if (isset($_SESSION['errors'])) {
    $errors = array_merge($errors, $_SESSION['errors']);
    unset($_SESSION['errors']);
}

if (isset($_SESSION['login_success'])) {
    $auth_message = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $tables_exist = $pdo->query("SHOW TABLES LIKE 'programming_languages'")->rowCount() > 0;
    
    if (!$tables_exist) {
        $pdo->exec("DROP TABLE IF EXISTS application_languages");
        $pdo->exec("DROP TABLE IF EXISTS applications");
        $pdo->exec("DROP TABLE IF EXISTS programming_languages");
        
        $pdo->exec("
            CREATE TABLE programming_languages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                language_name VARCHAR(50) NOT NULL UNIQUE
            );
            
            INSERT INTO programming_languages (language_name) VALUES 
            ('Pascal'), ('C'), ('C++'), ('JavaScript'), ('PHP'), 
            ('Python'), ('Java'), ('Haskell'), ('Clojure'), 
            ('Prolog'), ('Scala'), ('Go');
            
            CREATE TABLE applications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(150) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                email VARCHAR(100) NOT NULL,
                birth_date DATE NOT NULL,
                gender ENUM('male', 'female', 'other') NOT NULL,
                biography TEXT,
                agreed_to_contract TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                login VARCHAR(50) UNIQUE,
                password_hash VARCHAR(255),
                is_edited TINYINT(1) DEFAULT 0
            );
            
            CREATE TABLE application_languages (
                application_id INT UNSIGNED NOT NULL,
                language_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (application_id, language_id),
                FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
                FOREIGN KEY (language_id) REFERENCES programming_languages(id) ON DELETE CASCADE
            );
        ");
    }
    
    $stmt = $pdo->query("SELECT id, language_name FROM programming_languages ORDER BY language_name");
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($edit_id) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$edit_id]);
        $app_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($app_data) {
            $form_data = [
                'full_name' => $app_data['full_name'],
                'phone' => $app_data['phone'],
                'email' => $app_data['email'],
                'birth_date' => $app_data['birth_date'],
                'gender' => $app_data['gender'],
                'biography' => $app_data['biography'],
                'agreed_to_contract' => $app_data['agreed_to_contract']
            ];
            
            $stmt = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
            $stmt->execute([$edit_id]);
            $form_data['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    
} catch(PDOException $e) {
    $error_message = "Ошибка подключения к БД: " . $e->getMessage();
    $languages = [
        ['id' => 1, 'language_name' => 'Pascal'],
        ['id' => 2, 'language_name' => 'C'],
        ['id' => 3, 'language_name' => 'C++'],
        ['id' => 4, 'language_name' => 'JavaScript'],
        ['id' => 5, 'language_name' => 'PHP'],
        ['id' => 6, 'language_name' => 'Python'],
        ['id' => 7, 'language_name' => 'Java'],
        ['id' => 8, 'language_name' => 'Haskell'],
        ['id' => 9, 'language_name' => 'Clojure'],
        ['id' => 10, 'language_name' => 'Prolog'],
        ['id' => 11, 'language_name' => 'Scala'],
        ['id' => 12, 'language_name' => 'Go'],
    ];
}

function get_field_value($field_name, $form_data, $default = '') {
    if (isset($form_data[$field_name])) {
        return htmlspecialchars($form_data[$field_name]);
    }
    return htmlspecialchars($default);
}

function get_language_selected($lang_id, $form_data) {
    if (isset($form_data['languages']) && is_array($form_data['languages'])) {
        return in_array($lang_id, $form_data['languages']) ? 'selected' : '';
    }
    return '';
}

function get_gender_checked($gender, $form_data) {
    if (isset($form_data['gender']) && $form_data['gender'] == $gender) {
        return 'checked';
    }
    return '';
}

function get_checkbox_checked($form_data) {
    if (isset($form_data['agreed_to_contract']) && $form_data['agreed_to_contract'] == '1') {
        return 'checked';
    }
    return '';
}

function has_error($field_name, $errors) {
    return isset($errors[$field_name]) && !empty($errors[$field_name]);
}

function get_error_message($field_name, $errors) {
    return isset($errors[$field_name]) ? $errors[$field_name] : '';
}

function get_error_class($field_name, $errors) {
    return has_error($field_name, $errors) ? 'error-field' : '';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета разработчика</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form-container">
        <h1>Анкета разработчика</h1>
        <p class="subtitle">Заполните форму для участия в программе</p>
        
        <?php if ($edit_id): ?>
            <div class="info-message" style="background: #e3f2fd; border-left-color: #2196f3;">
                🔐 Вы авторизованы. Можете редактировать свои данные.
            </div>
        <?php endif; ?>
        
        <?php if ($auth_message): ?>
            <div class="success-message">
                <?= htmlspecialchars($auth_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="success-message">
                Данные успешно сохранены!
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['credentials']) && $_GET['credentials'] == 1 && isset($_COOKIE['temp_credentials'])): 
            $creds = json_decode($_COOKIE['temp_credentials'], true); ?>
            <div class="credentials-message" style="background: #fff3e0; border-left-color: #ff9800; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <strong>⚠️ Сохраните ваши данные для входа!</strong><br>
                Логин: <strong><?= htmlspecialchars($creds['login']) ?></strong><br>
                Пароль: <strong><?= htmlspecialchars($creds['password']) ?></strong><br>
                <small>Для редактирования данных используйте эти данные в форме входа ниже.</small>
            </div>
            <?php setcookie('temp_credentials', '', time() - 3600, '/'); ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <strong>Пожалуйста, исправьте следующие ошибки:</strong>
                <ul>
                    <?php foreach ($errors as $field => $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <form action="save.php" method="POST">
            <?php if ($edit_id): ?>
                <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label class="required">ФИО</label>
                <input type="text" name="full_name" 
                       class="<?= get_error_class('full_name', $errors) ?>"
                       value="<?= get_field_value('full_name', $form_data) ?>"
                       placeholder="Иванов Иван Иванович">
                <span class="hint">Только буквы, пробелы и дефисы. Максимум 150 символов.</span>
                <?php if (has_error('full_name', $errors)): ?>
                    <span class="error-field-message"><?= get_error_message('full_name', $errors) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="required">Телефон</label>
                <input type="tel" name="phone" 
                       class="<?= get_error_class('phone', $errors) ?>"
                       value="<?= get_field_value('phone', $form_data) ?>"
                       placeholder="+7 (123) 456-78-90">
                <span class="hint">Допустимы цифры, +, -, пробелы, скобки. Минимум 10 цифр.</span>
                <?php if (has_error('phone', $errors)): ?>
                    <span class="error-field-message"><?= get_error_message('phone', $errors) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="required">E-mail</label>
                <input type="email" name="email" 
                       class="<?= get_error_class('email', $errors) ?>"
                       value="<?= get_field_value('email', $form_data) ?>"
                       placeholder="example@mail.com">
                <span class="hint">Пример: user@domain.com</span>
                <?php if (has_error('email', $errors)): ?>
                    <span class="error-field-message"><?= get_error_message('email', $errors) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="required">Дата рождения</label>
                <input type="date" name="birth_date" 
                       class="<?= get_error_class('birth_date', $errors) ?>"
                       value="<?= get_field_value('birth_date', $form_data) ?>">
                <span class="hint">Возраст должен быть от 18 до 100 лет</span>
                <?php if (has_error('birth_date', $errors)): ?>
                    <span class="error-field-message"><?= get_error_message('birth_date', $errors) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="required">Пол</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="gender" value="male" 
                               <?= get_gender_checked('male', $form_data) ?>> Мужской
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="gender" value="female"
                               <?= get_gender_checked('female', $form_data) ?>> Женский
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="gender" value="other"
                               <?= get_gender_checked('other', $form_data) ?>> Другой
                    </label>
                </div>
                <?php if (has_error('gender', $errors)): ?>
                    <span class="error-field-message"><?= get_error_message('gender', $errors) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="required">Любимые языки программирования</label>
                <select name="languages[]" multiple class="<?= get_error_class('languages', $errors) ?>">
                    <?php foreach ($languages as $lang): ?>
                        <option value="<?= $lang['id'] ?>" 
                                <?= get_language_selected($lang['id'], $form_data) ?>>
                            <?= htmlspecialchars($lang['language_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">Удерживайте Ctrl (Cmd на Mac) для выбора нескольких языков</span>
                <?php if (has_error('languages', $errors)): ?>
                    <span class="error-field-message"><?= get_error_message('languages', $errors) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Биография</label>
                <textarea name="biography" rows="4" 
                          class="<?= get_error_class('biography', $errors) ?>"
                          placeholder="Расскажите немного о себе..."><?= get_field_value('biography', $form_data) ?></textarea>
                <span class="hint">Не более 5000 символов</span>
                <?php if (has_error('biography', $errors)): ?>
                    <span class="error-field-message"><?= get_error_message('biography', $errors) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="checkbox-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="agreed_to_contract" value="1"
                           <?= get_checkbox_checked($form_data) ?>>
                    Я ознакомлен(а) с условиями контракта
                </label>
                <?php if (has_error('agreed_to_contract', $errors)): ?>
                    <span class="error-field-message"><?= get_error_message('agreed_to_contract', $errors) ?></span>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="submit-btn"><?= $edit_id ? 'Обновить данные' : 'Сохранить данные' ?></button>
        </form>
        
        <hr style="margin: 30px 0 20px; border: none; border-top: 1px solid #ddd;">
        
        <h3 style="margin-bottom: 15px;">Вход для редактирования</h3>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="login" placeholder="Введите логин" required>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" placeholder="Введите пароль" required>
            </div>
            <button type="submit" class="submit-btn" style="background: #4caf50;">Войти</button>
        </form>
        
        <?php if ($edit_id): ?>
            <form action="logout.php" method="POST" style="margin-top: 15px;">
                <button type="submit" class="submit-btn" style="background: #f44336;">Выйти</button>
            </form>
        <?php endif; ?>
        
        <p class="required-note">Поля, отмеченные *, обязательны для заполнения</p>
    </div>
</body>
</html>