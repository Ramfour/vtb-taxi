<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ВТБ - Управление корпоративным такси</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- Шапка -->
        <header class="header">
            <div class="logo">
                <img src="vtb_logo_main_ru.png" alt="ВТБ Логотип" height="40">
                <span>Управление корпоративным такси</span>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <span class="user-name">Иванов А.С.</span>
                    <span class="user-role">Руководитель отдела</span>
                </div>
                <button class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </header>

        <!-- Панель управления -->
        <div class="control-panel">
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value" id="pending-count">1</div>
                    <div class="stat-label">Ожидают</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="approved-count">1</div>
                    <div class="stat-label">Одобрено</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="rejected-count">1</div>
                    <div class="stat-label">Отклонено</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="total-count">3</div>
                    <div class="stat-label">Всего</div>
                </div>
            </div>
            
            <div class="actions">
                <button class="btn btn-primary" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Экспорт в Excel
                </button>
                <button class="btn btn-secondary" onclick="showNotificationSettings()">
                    <i class="fas fa-bell"></i> Настройки уведомлений
                </button>
                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Поиск по ФИО или адресу...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="filters">
            <select id="status-filter" onchange="filterRequests()">
                <option value="all">Все статусы</option>
                <option value="pending">Ожидают</option>
                <option value="approved">Одобрено</option>
                <option value="rejected">Отклонено</option>
                <option value="completed">Выполнено</option>
            </select>
            
            <input type="date" id="date-filter" onchange="filterRequests()">
            
            <button class="btn btn-link" onclick="resetFilters()">
                <i class="fas fa-redo"></i> Сбросить фильтры
            </button>
        </div>

        <!-- Таблица заявок -->
        <div class="table-container">
            <table id="requests-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО сотрудника</th>
                        <th>Телефон</th>
                        <th>Адрес подачи</th>
                        <th>Пункт назначения</th>
                        <th>Дата/время</th>
                        <th>Статус</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="requests-body">
                    <!-- Данные будут загружены через JavaScript -->
                </tbody>
            </table>
            
            <div class="loading" id="loading">
                <i class="fas fa-spinner fa-spin"></i> Загрузка данных...
            </div>
            
            <div class="no-data" id="no-data" style="display: none;">
                <i class="fas fa-inbox"></i>
                <p>Нет заявок, соответствующих фильтрам</p>
            </div>
        </div>

        <!-- Пагинация -->
        <div class="pagination">
            <button class="page-btn" onclick="changePage(-1)">
                <i class="fas fa-chevron-left"></i> Назад
            </button>
            <span class="page-info">Страница <span id="current-page">1</span> из <span id="total-pages">1</span></span>
            <button class="page-btn" onclick="changePage(1)">
                Вперед <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <!-- Модальное окно для изменения статуса -->
    <div class="modal" id="status-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Изменение статуса заявки</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="request-info" id="modal-request-info"></div>
                
                <div class="form-group">
                    <label>Новый статус:</label>
                    <select id="new-status">
                        <option value="pending">Ожидает</option>
                        <option value="approved">Одобрено</option>
                        <option value="rejected">Отклонено</option>
                        <option value="completed">Выполнено</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Комментарий руководителя:</label>
                    <textarea id="manager-comment" rows="3" placeholder="Укажите причину одобрения/отклонения..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeModal()">Отмена</button>
                    <button class="btn btn-primary" onclick="saveStatusChange()">Сохранить</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Уведомления -->
    <div class="notification" id="notification">
        <div class="notification-content">
            <span id="notification-text"></span>
            <button onclick="hideNotification()">&times;</button>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>