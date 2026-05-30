// Глобальные функции для всех страниц

// Уведомления
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
        type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 
        type === 'error' ? 'bg-red-50 border border-red-200 text-red-800' :
        'bg-blue-50 border border-blue-200 text-blue-800'
    } alert-slide`;
    alertDiv.innerHTML = `
        <div class="flex items-center space-x-3">
            ${type === 'success' ? '✓' : type === 'error' ? '⚠' : 'ℹ'}
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}

// Загрузка данных через fetch
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        showAlert('Ошибка соединения', 'error');
        return null;
    }
}

// Форматирование даты
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU');
}

// Переключение вкладок (для админки и профиля)
function initTabs() {
    const tabs = document.querySelectorAll('[data-tab]');
    const contents = document.querySelectorAll('[data-tab-content]');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabId = tab.dataset.tab;
            
            tabs.forEach(t => t.classList.remove('active', 'bg-blue-50', 'text-blue-600'));
            tab.classList.add('active', 'bg-blue-50', 'text-blue-600');
            
            contents.forEach(content => {
                content.classList.add('hidden');
                if (content.id === tabId) {
                    content.classList.remove('hidden');
                }
            });
        });
    });
}

// Инициализация при загрузке DOM
document.addEventListener('DOMContentLoaded', () => {
    initTabs();
});