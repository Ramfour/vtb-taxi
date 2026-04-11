// Глобальные переменные
let currentRequests = [];
let filteredRequests = [];
let currentPage = 1;
const itemsPerPage = 10;
let currentRequestId = null;

// Загрузка данных при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    loadRequests();
    
    // Настройка поиска
    const searchInput = document.getElementById('search-input');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            filterRequests();
        }, 300);
    });
});

// Загрузка заявок с сервера
async function loadRequests() {
    showLoading(true);
    
    try {
        const response = await fetch('backend.php?action=get_requests');
        const data = await response.json();
        
        if (data.success) {
            currentRequests = data.data;
            filteredRequests = [...currentRequests];
            updateStats();
            renderTable();
            showLoading(false);
        } else {
            throw new Error(data.message || 'Ошибка загрузки данных');
        }
    } catch (error) {
        console.error('Ошибка:', error);
        showNotification('Ошибка загрузки данных: ' + error.message, 'error');
        showLoading(false);
    }
}

// Отображение таблицы
function renderTable() {
    const tbody = document.getElementById('requests-body');
    const noDataElement = document.getElementById('no-data');
    
    if (filteredRequests.length === 0) {
        tbody.innerHTML = '';
        noDataElement.style.display = 'block';
        return;
    }
    
    noDataElement.style.display = 'none';
    
    // Применение пагинации
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageRequests = filteredRequests.slice(startIndex, endIndex);
    
    // Обновление информации о пагинации
    document.getElementById('current-page').textContent = currentPage;
    document.getElementById('total-pages').textContent = Math.ceil(filteredRequests.length / itemsPerPage);
    
    // Генерация строк таблицы
    let html = '';
    
    pageRequests.forEach(request => {
        const statusClass = `status-${request.status}`;
        const statusText = getStatusText(request.status);
        const date = new Date(request.date_time).toLocaleString('ru-RU');
        const created = new Date(request.created_at).toLocaleString('ru-RU');
        
        html += `
            <tr>
                <td>#${request.id}</td>
                <td><strong>${request.employee_name}</strong></td>
                <td>${request.employee_phone}</td>
                <td>${request.address}</td>
                <td>${request.destination}</td>
                <td>${date}</td>
                <td><span class="status ${statusClass}">${statusText}</span></td>
                <td>${created}</td>
                <td>
                    <div class="action-buttons">
                        ${request.status === 'pending' ? `
                            <button class="action-btn action-approve" onclick="changeRequestStatus(${request.id}, 'approved')">
                                <i class="fas fa-check"></i> Одобрить
                            </button>
                            <button class="action-btn action-reject" onclick="changeRequestStatus(${request.id}, 'rejected')">
                                <i class="fas fa-times"></i> Отклонить
                            </button>
                        ` : ''}
                        <button class="action-btn action-edit" onclick="openStatusModal(${request.id})">
                            <i class="fas fa-edit"></i> Изменить
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Обновление статистики
function updateStats() {
    const pending = currentRequests.filter(r => r.status === 'pending').length;
    const approved = currentRequests.filter(r => r.status === 'approved').length;
    const rejected = currentRequests.filter(r => r.status === 'rejected').length;
    
    document.getElementById('pending-count').textContent = pending;
    document.getElementById('approved-count').textContent = approved;
    document.getElementById('rejected-count').textContent = rejected;
    document.getElementById('total-count').textContent = currentRequests.length;
}

// Фильтрация заявок
function filterRequests() {
    const statusFilter = document.getElementById('status-filter').value;
    const dateFilter = document.getElementById('date-filter').value;
    const searchText = document.getElementById('search-input').value.toLowerCase();
    
    filteredRequests = currentRequests.filter(request => {
        // Фильтр по статусу
        if (statusFilter !== 'all' && request.status !== statusFilter) {
            return false;
        }
        
        // Фильтр по дате
        if (dateFilter) {
            const requestDate = new Date(request.date_time).toISOString().split('T')[0];
            if (requestDate !== dateFilter) {
                return false;
            }
        }
        
        // Поиск по тексту
        if (searchText) {
            const searchFields = [
                request.employee_name,
                request.address,
                request.destination,
                request.employee_phone
            ].join(' ').toLowerCase();
            
            if (!searchFields.includes(searchText)) {
                return false;
            }
        }
        
        return true;
    });
    
    currentPage = 1;
    renderTable();
}

// Сброс фильтров
function resetFilters() {
    document.getElementById('status-filter').value = 'all';
    document.getElementById('date-filter').value = '';
    document.getElementById('search-input').value = '';
    filterRequests();
}

// Изменение статуса заявки (быстрое действие)
async function changeRequestStatus(requestId, newStatus) {
    if (!confirm('Вы уверены, что хотите изменить статус заявки?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('id', requestId);
    formData.append('status', newStatus);
    formData.append('comment', 'Статус изменен через быстрое действие');
    
    try {
        const response = await fetch('backend.php?action=update_status', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Статус заявки успешно изменен', 'success');
            loadRequests(); // Перезагружаем данные
        } else {
            throw new Error(data.message || 'Ошибка изменения статуса');
        }
    } catch (error) {
        console.error('Ошибка:', error);
        showNotification('Ошибка изменения статуса: ' + error.message, 'error');
    }
}

// Открытие модального окна для изменения статуса
function openStatusModal(requestId) {
    currentRequestId = requestId;
    const request = currentRequests.find(r => r.id === requestId);
    
    if (!request) return;
    
    const modal = document.getElementById('status-modal');
    const infoElement = document.getElementById('modal-request-info');
    
    infoElement.innerHTML = `
        <div class="request-details">
            <p><strong>Сотрудник:</strong> ${request.employee_name}</p>
            <p><strong>Телефон:</strong> ${request.employee_phone}</p>
            <p><strong>Адрес:</strong> ${request.address}</p>
            <p><strong>Назначение:</strong> ${request.destination}</p>
            <p><strong>Дата/время:</strong> ${new Date(request.date_time).toLocaleString('ru-RU')}</p>
            <p><strong>Текущий статус:</strong> <span class="status status-${request.status}">${getStatusText(request.status)}</span></p>
        </div>
    `;
    
    document.getElementById('new-status').value = request.status;
    document.getElementById('manager-comment').value = request.manager_comment || '';
    
    modal.style.display = 'flex';
}

// Закрытие модального окна
function closeModal() {
    document.getElementById('status-modal').style.display = 'none';
    currentRequestId = null;
}

// Сохранение изменения статуса из модального окна
async function saveStatusChange() {
    const newStatus = document.getElementById('new-status').value;
    const comment = document.getElementById('manager-comment').value;
    
    if (!currentRequestId) return;
    
    const formData = new FormData();
    formData.append('id', currentRequestId);
    formData.append('status', newStatus);
    formData.append('comment', comment);
    
    try {
        const response = await fetch('backend.php?action=update_status', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Статус заявки успешно обновлен', 'success');
            closeModal();
            loadRequests(); // Перезагружаем данные
        } else {
            throw new Error(data.message || 'Ошибка сохранения изменений');
        }
    } catch (error) {
        console.error('Ошибка:', error);
        showNotification('Ошибка сохранения: ' + error.message, 'error');
    }
}

// Экспорт в Excel
function exportToExcel() {
    // Открываем новое окно для скачивания файла
    window.open('backend.php?action=export_excel', '_blank');
    showNotification('Начат экспорт данных в Excel', 'info');
}

// Пагинация
function changePage(delta) {
    const newPage = currentPage + delta;
    const totalPages = Math.ceil(filteredRequests.length / itemsPerPage);
    
    if (newPage < 1 || newPage > totalPages) {
        return;
    }
    
    currentPage = newPage;
    renderTable();
    
    // Прокрутка к верху таблицы
    document.querySelector('.table-container').scrollIntoView({
        behavior: 'smooth'
    });
}

// Показать/скрыть загрузку
function showLoading(show) {
    document.getElementById('loading').style.display = show ? 'block' : 'none';
}

// Уведомления
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    const textElement = document.getElementById('notification-text');
    
    // Установка цвета в зависимости от типа
    const colors = {
        success: '#4caf50',
        error: '#f44336',
        info: '#2196f3',
        warning: '#ff9800'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    textElement.textContent = message;
    notification.style.display = 'block';
    
    // Автоматическое скрытие через 5 секунд
    setTimeout(hideNotification, 5000);
}

function hideNotification() {
    document.getElementById('notification').style.display = 'none';
}

// Вспомогательные функции
function getStatusText(status) {
    const statusMap = {
        'pending': 'Ожидает',
        'approved': 'Одобрено',
        'rejected': 'Отклонено',
        'completed': 'Выполнено'
    };
    
    return statusMap[status] || status;
}

// Настройки уведомлений (заглушка)
function showNotificationSettings() {
    showNotification('Настройки уведомлений будут доступны в полной версии', 'info');
}

// Закрытие модального окна по клику вне его
window.onclick = function(event) {
    const modal = document.getElementById('status-modal');
    if (event.target === modal) {
        closeModal();
    }
};

// Горячие клавиши
document.addEventListener('keydown', function(event) {
    // Esc - закрыть модальное окно
    if (event.key === 'Escape') {
        closeModal();
        hideNotification();
    }
    
    // Ctrl+F - фокус на поиск
    if (event.ctrlKey && event.key === 'f') {
        event.preventDefault();
        document.getElementById('search-input').focus();
    }
    
    // Ctrl+E - экспорт
    if (event.ctrlKey && event.key === 'e') {
        event.preventDefault();
        exportToExcel();
    }
});