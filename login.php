<?php
session_start();

$db_host = 'localhost';
$db_name = 'u82192';
$db_user = 'u82192';
$db_pass = '2307509';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        $_SESSION['errors'] = ['login' => 'Введите логин и пароль'];
        header('Location: index.php');
        exit();
    }
    
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT id, password_hash FROM applications WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['edit_id'] = $user['id'];
            $_SESSION['login_success'] = "Вы успешно вошли! Теперь вы можете редактировать свои данные.";
        } else {
            $_SESSION['errors'] = ['login' => 'Неверный логин или пароль'];
        }
        
    } catch (PDOException $e) {
        $_SESSION['errors'] = ['database' => 'Ошибка базы данных: ' . $e->getMessage()];
    }
    
    header('Location: index.php');
    exit();
} else {
    header('Location: index.php');
    exit();
}
?>