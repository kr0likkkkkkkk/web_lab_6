<?php
session_start();

$db_host = 'localhost';
$db_name = 'u82192';
$db_user = 'u82192';
$db_pass = '2307509';

$errors = [];
$form_data = [];
$is_edit = false;
$edit_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'birth_date' => $_POST['birth_date'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'languages' => $_POST['languages'] ?? [],
        'biography' => trim($_POST['biography'] ?? ''),
        'agreed_to_contract' => $_POST['agreed_to_contract'] ?? ''
    ];
    
    if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
        $is_edit = true;
        $edit_id = $_POST['edit_id'];
    }
    
    if (empty($form_data['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно для заполнения';
    } elseif (strlen($form_data['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов. Допустимые символы: буквы, пробелы, дефисы.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $form_data['full_name'])) {
        $errors['full_name'] = 'ФИО может содержать только буквы, пробелы и дефисы. Недопустимые символы: цифры, знаки препинания, спецсимволы.';
    }
    
    if (empty($form_data['phone'])) {
        $errors['phone'] = 'Телефон обязателен для заполнения';
    } else {
        $cleanPhone = preg_replace('/[^0-9+]/', '', $form_data['phone']);
        if (strlen($cleanPhone) < 10) {
            $errors['phone'] = 'Телефон должен содержать минимум 10 цифр. Допустимые символы: цифры, +, -, пробелы, скобки.';
        } elseif (strlen($cleanPhone) > 20) {
            $errors['phone'] = 'Телефон не должен превышать 20 символов. Допустимые символы: цифры, +, -, пробелы, скобки.';
        } elseif (!preg_match('/^[\+\-\s\(\)0-9]{10,20}$/', $form_data['phone'])) {
            $errors['phone'] = 'Неверный формат телефона. Допустимые символы: цифры, +, -, пробелы, скобки.';
        }
    }
    
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email обязателен для заполнения';
    } elseif (strlen($form_data['email']) > 100) {
        $errors['email'] = 'Email не должен превышать 100 символов';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Неверный формат email. Пример правильного email: user@example.com';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $form_data['email'])) {
        $errors['email'] = 'Email может содержать только буквы, цифры, точки, дефисы и знак @. Недопустимые символы: пробелы, спецсимволы.';
    }
    
    if (empty($form_data['birth_date'])) {
        $errors['birth_date'] = 'Дата рождения обязательна для заполнения';
    } else {
        $birth_timestamp = strtotime($form_data['birth_date']);
        if (!$birth_timestamp || $birth_timestamp > time()) {
            $errors['birth_date'] = 'Некорректная дата рождения. Формат: ГГГГ-ММ-ДД';
        } else {
            $age = date('Y') - date('Y', $birth_timestamp);
            if (date('md') < date('md', $birth_timestamp)) {
                $age--;
            }
            if ($age < 18) {
                $errors['birth_date'] = 'Вам должно быть не менее 18 лет';
            }
            if ($age > 100) {
                $errors['birth_date'] = 'Возраст не может превышать 100 лет';
            }
        }
    }
    
    if (empty($form_data['gender'])) {
        $errors['gender'] = 'Выберите пол';
    } elseif (!in_array($form_data['gender'], ['male', 'female', 'other'])) {
        $errors['gender'] = 'Выберите корректный пол из предложенных вариантов';
    }
    
    if (empty($form_data['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования';
    } elseif (count($form_data['languages']) > 12) {
        $errors['languages'] = 'Выбрано слишком много языков (максимум 12)';
    }
    
    if (strlen($form_data['biography']) > 5000) {
        $errors['biography'] = 'Биография не должна превышать 5000 символов';
    }
    
    if (empty($form_data['agreed_to_contract'])) {
        $errors['agreed_to_contract'] = 'Вы должны подтвердить ознакомление с контрактом';
    } elseif ($form_data['agreed_to_contract'] != '1') {
        $errors['agreed_to_contract'] = 'Подтвердите ознакомление с контрактом';
    }
    
    if (!empty($errors)) {
        setcookie('errors', json_encode($errors), 0, '/');
        setcookie('form_data', json_encode($form_data), 0, '/');
        header('Location: index.php');
        exit();
    }
    
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->beginTransaction();
        
        $placeholders = str_repeat('?,', count($form_data['languages']) - 1) . '?';
        $check_stmt = $pdo->prepare("SELECT id FROM programming_languages WHERE id IN ($placeholders)");
        $check_stmt->execute($form_data['languages']);
        $valid_languages = $check_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($valid_languages) != count($form_data['languages'])) {
            throw new Exception("Один или несколько выбранных языков не существуют в базе данных");
        }
        
        $contract_value = ($form_data['agreed_to_contract'] == '1') ? 1 : 0;
        
        if ($is_edit && $edit_id) {
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET full_name = ?, phone = ?, email = ?, birth_date = ?, 
                    gender = ?, biography = ?, agreed_to_contract = ?, is_edited = 1
                WHERE id = ?
            ");
            $stmt->execute([
                $form_data['full_name'],
                $form_data['phone'],
                $form_data['email'],
                $form_data['birth_date'],
                $form_data['gender'],
                $form_data['biography'],
                $contract_value,
                $edit_id
            ]);
            $application_id = $edit_id;
            
            $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$edit_id]);
        } else {
            $login = generate_unique_login($pdo);
            $password = generate_password();
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, agreed_to_contract, login, password_hash) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $form_data['full_name'],
                $form_data['phone'],
                $form_data['email'],
                $form_data['birth_date'],
                $form_data['gender'],
                $form_data['biography'],
                $contract_value,
                $login,
                $password_hash
            ]);
            $application_id = $pdo->lastInsertId();
            
            setcookie('temp_credentials', json_encode(['login' => $login, 'password' => $password]), 0, '/');
        }
        
        $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
        foreach ($valid_languages as $lang_id) {
            $lang_stmt->execute([$application_id, $lang_id]);
        }
        
        $pdo->commit();
        
        setcookie('form_data', json_encode($form_data), time() + 365 * 24 * 60 * 60, '/');
        setcookie('errors', '', time() - 3600, '/');
        
        if ($is_edit) {
            header('Location: index.php?success=1');
        } else {
            header('Location: index.php?success=1&credentials=1');
        }
        exit();
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors['database'] = "Ошибка базы данных: " . $e->getMessage();
        setcookie('errors', json_encode($errors), 0, '/');
        setcookie('form_data', json_encode($form_data), 0, '/');
        header('Location: index.php');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors['general'] = $e->getMessage();
        setcookie('errors', json_encode($errors), 0, '/');
        setcookie('form_data', json_encode($form_data), 0, '/');
        header('Location: index.php');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}

function generate_unique_login($pdo) {
    $base = 'user_' . rand(1000, 9999);
    $login = $base;
    $counter = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM applications WHERE login = ?");
        $stmt->execute([$login]);
        if ($stmt->rowCount() == 0) {
            return $login;
        }
        $login = $base . '_' . $counter;
        $counter++;
    }
}

function generate_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}
?>