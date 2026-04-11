<?php
require_once 'config.php';

header('Content-Type: application/json');
session_start();

// Для демонстрации используем сессию для хранения данных
if (!isset($_SESSION['requests'])) {
    $_SESSION['requests'] = [
        [
            'id' => 1,
            'employee_name' => 'Иванов Иван Иванович',
            'employee_phone' => '+7 (912) 345-67-89',
            'address' => 'ул. Ленина, д. 10, кв. 25',
            'destination' => 'ул. Пушкина, д. 5',
            'date_time' => '2024-12-15 09:30',
            'status' => 'pending',
            'created_at' => '2024-12-14 16:45',
            'manager_comment' => ''
        ],
        [
            'id' => 2,
            'employee_name' => 'Петрова Мария Сергеевна',
            'employee_phone' => '+7 (923) 456-78-90',
            'address' => 'пр. Мира, д. 15',
            'destination' => 'Аэропорт',
            'date_time' => '2024-12-15 14:00',
            'status' => 'approved',
            'created_at' => '2024-12-14 15:20',
            'manager_comment' => 'Одобрено для встречи клиента'
        ],
        [
            'id' => 3,
            'employee_name' => 'Сидоров Алексей Петрович',
            'employee_phone' => '+7 (934) 567-89-01',
            'address' => 'ул. Садовая, д. 3',
            'destination' => 'Офис ВТБ, ул. Банковская, 1',
            'date_time' => '2024-12-16 08:45',
            'status' => 'rejected',
            'created_at' => '2024-12-14 14:10',
            'manager_comment' => 'Превышен лимит поездок'
        ]
    ];
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_requests':
        echo json_encode([
            'success' => true,
            'data' => $_SESSION['requests'],
            'total' => count($_SESSION['requests'])
        ]);
        break;
        
    case 'update_status':
        $requestId = $_POST['id'] ?? 0;
        $newStatus = $_POST['status'] ?? '';
        $comment = $_POST['comment'] ?? '';
        
        foreach ($_SESSION['requests'] as &$request) {
            if ($request['id'] == $requestId) {
                $request['status'] = $newStatus;
                $request['manager_comment'] = $comment;
                $request['processed_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Статус обновлен']);
        break;
        
    case 'export_excel':
        // Генерация CSV с правильной кодировкой для Excel (Windows-1251 для русских версий)
        $filename = 'taxi_orders_' . date('Y-m-d') . '.csv';
        
        // Отправляем заголовки с кодировкой Windows-1251
        header('Content-Type: text/csv; charset=Windows-1251');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Создаем выходной поток
        $output = fopen('php://output', 'w');
        
        // Заголовки столбцов
        $headers = ['ID', 'ФИО', 'Телефон', 'Адрес подачи', 'Пункт назначения', 'Дата/время', 'Статус'];
        
        // Конвертируем заголовки в Windows-1251
        foreach ($headers as &$header) {
            $header = iconv('UTF-8', 'Windows-1251//TRANSLIT//IGNORE', $header);
        }
        fputcsv($output, $headers, ';');
        
        // Данные
        foreach ($_SESSION['requests'] as $request) {
            // Преобразуем статус в русский текст
            $statusText = '';
            switch($request['status']) {
                case 'pending': $statusText = 'Ожидает'; break;
                case 'approved': $statusText = 'Одобрено'; break;
                case 'rejected': $statusText = 'Отклонено'; break;
                case 'completed': $statusText = 'Выполнено'; break;
                default: $statusText = $request['status'];
            }
            
            // Конвертируем все строковые значения в Windows-1251
            $row = [
                $request['id'],
                iconv('UTF-8', 'Windows-1251//TRANSLIT//IGNORE', $request['employee_name']),
                iconv('UTF-8', 'Windows-1251//TRANSLIT//IGNORE', $request['employee_phone']),
                iconv('UTF-8', 'Windows-1251//TRANSLIT//IGNORE', $request['address']),
                iconv('UTF-8', 'Windows-1251//TRANSLIT//IGNORE', $request['destination']),
                iconv('UTF-8', 'Windows-1251//TRANSLIT//IGNORE', $request['date_time']),
                iconv('UTF-8', 'Windows-1251//TRANSLIT//IGNORE', $statusText)
            ];
            
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
}
?>