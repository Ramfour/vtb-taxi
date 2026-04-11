<?php
// Конфигурация базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'vtb_taxi');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Настройки приложения
define('SITE_NAME', 'ВТБ - Управление заявками');
define('ITEMS_PER_PAGE', 10);

// Статусы заявок
$statuses = [
    'pending' => 'Ожидает',
    'approved' => 'Одобрено',
    'rejected' => 'Отклонено',
    'completed' => 'Выполнено'
];

// Роли пользователей
$roles = [
    'employee' => 'Сотрудник',
    'manager' => 'Руководитель',
    'admin' => 'Администратор'
];
?>