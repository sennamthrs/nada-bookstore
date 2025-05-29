// Admin Panel JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar functionality
    initSidebar();
    
    // Modal functionality
    initModals();
    
    // Form enhancements
    initForms();
    
    // Tooltips
    initTooltips();
    
    // Auto-hide alerts
    initAlerts();
    
    // Keyboard shortcuts
    initKeyboardShortcuts();
    
    // Initialize additional features
    initDataTables();
    initNotifications();
});

// Sidebar Management
function initSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const mainContent = document.querySelector('.admin-main');
    
    if (!sidebar || !sidebarToggle) return;
    
    // Load saved state
    const sidebarState = localStorage.getItem('admin-sidebar-collapsed');
    if (sidebarState === 'true' && window.innerWidth > 1024) {
        sidebar.classList.add('collapsed');
    }
    
    // Toggle functionality
    sidebarToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        
        if (window.innerWidth <= 1024) {
            // Mobile behavior
            sidebar.classList.toggle('show');
        } else {
            // Desktop behavior
            sidebar.classList.toggle('collapsed');
            
            // Save state
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('admin-sidebar-collapsed', isCollapsed);
        }
    });
    
    // Handle window resize
    function handleResize() {
        if (window.innerWidth <= 1024) {
            sidebar.classList.remove('collapsed');
        } else {
            sidebar.classList.remove('show');
            const isCollapsed = localStorage.getItem('admin-sidebar-collapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            }
        }
    }
    
    window.addEventListener('resize', handleResize);
    handleResize();
    
    // Close sidebar on mobile when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
    
    // Add hover effect for collapsed sidebar
    addSidebarHoverEffect();
    
    // Add hover effect when sidebar is collapsed
    function addSidebarHoverEffect() {
        const menuItems = sidebar.querySelectorAll('.sidebar-menu a');
        menuItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                if (sidebar.classList.contains('collapsed')) {
                    this.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
                }
            });
            
            item.addEventListener('mouseleave', function() {
                if (sidebar.classList.contains('collapsed')) {
                    this.style.backgroundColor = '';
                }
            });
        });
    }
}

// Modal Management
function initModals() {
    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Close modal with close button
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

// Form Enhancements
function initForms() {
    // Add loading state to forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="loading-spinner"></span> Memproses...';
                
                // Reset after 10 seconds in case of error
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 10000);
            }
        });
    });
    
    // Real-time search with debouncing
    const searchInputs = document.querySelectorAll('input[name="search"]');
    searchInputs.forEach(input => {
        let searchTimeout;
        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.form) {
                    this.form.submit();
                }
            }, 1000);
        });
    });
    
    // Auto-format inputs
    initInputFormatting();
}

function initInputFormatting() {
    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"], input[name*="phone"], input[name*="telepon"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.startsWith('62')) {
                value = '0' + value.substring(2);
            }
            
            e.target.value = value;
        });
    });
    
    // Postal code formatting
    const postalInputs = document.querySelectorAll('input[name*="postal"], input[name*="kode_pos"]');
    postalInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 5);
        });
    });
}

// Tooltips
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            showTooltip(this);
        });
        
        element.addEventListener('mouseleave', function() {
            hideTooltip(this);
        });
    });
}

function showTooltip(element) {
    const tooltip = element.getAttribute('data-tooltip');
    if (!tooltip) return;
    
    const tooltipEl = document.createElement('div');
    tooltipEl.className = 'custom-tooltip';
    tooltipEl.textContent = tooltip;
    tooltipEl.style.cssText = `
        position: absolute;
        background: #333;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.85rem;
        white-space: nowrap;
        z-index: 9999;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s;
    `;
    
    document.body.appendChild(tooltipEl);
    
    const rect = element.getBoundingClientRect();
    tooltipEl.style.left = rect.left + (rect.width / 2) - (tooltipEl.offsetWidth / 2) + 'px';
    tooltipEl.style.top = rect.top - tooltipEl.offsetHeight - 8 + 'px';
    
    setTimeout(() => {
        tooltipEl.style.opacity = '1';
    }, 10);
    
    element._tooltip = tooltipEl;
}

function hideTooltip(element) {
    if (element._tooltip) {
        element._tooltip.remove();
        delete element._tooltip;
    }
}

// Alert Management
function initAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Add close button if not exists
        if (!alert.querySelector('.alert-close')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'alert-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = `
                position: absolute;
                top: 10px;
                right: 15px;
                background: none;
                border: none;
                font-size: 1.2rem;
                cursor: pointer;
                opacity: 0.7;
            `;
            
            closeBtn.addEventListener('click', () => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
            
            alert.style.position = 'relative';
            alert.appendChild(closeBtn);
        }
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 300);
            }
        }, 5000);
    });
}

// Keyboard Shortcuts
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // ESC to close modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                closeModal(modal.id);
            });
        }
        
        // Ctrl+/ to toggle sidebar
        if (e.ctrlKey && e.key === '/') {
            e.preventDefault();
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            if (sidebarToggle) {
                sidebarToggle.click();
            }
        }
        
        // Alt+1-9 for menu navigation
        if (e.altKey && e.key >= '1' && e.key <= '9') {
            e.preventDefault();
            const menuItems = document.querySelectorAll('.sidebar-menu a');
            const index = parseInt(e.key) - 1;
            if (menuItems[index]) {
                menuItems[index].click();
            }
        }
    });
}

// Data Table Enhancements
function initDataTables() {
    const tables = document.querySelectorAll('.admin-table');
    
    tables.forEach(table => {
        // Add sorting functionality
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            if (header.textContent.trim() && !header.querySelector('.sort-icon')) {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => {
                    sortTable(table, index);
                });
                
                // Add sort indicator
                const sortIcon = document.createElement('i');
                sortIcon.className = 'fas fa-sort sort-icon';
                sortIcon.style.marginLeft = '5px';
                sortIcon.style.opacity = '0.5';
                header.appendChild(sortIcon);
            }
        });
        
        // Add row hover effects
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8fafc';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    });
}

function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Get current sort direction
    const header = table.querySelectorAll('th')[columnIndex];
    const currentDirection = header.getAttribute('data-sort') || 'asc';
    const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
    
    // Clear all sort indicators
    table.querySelectorAll('th').forEach(th => {
        th.removeAttribute('data-sort');
        const icon = th.querySelector('.sort-icon');
        if (icon) {
            icon.className = 'fas fa-sort sort-icon';
        }
    });
    
    // Set new sort direction
    header.setAttribute('data-sort', newDirection);
    const sortIcon = header.querySelector('.sort-icon');
    if (sortIcon) {
        sortIcon.className = `fas fa-sort-${newDirection === 'asc' ? 'up' : 'down'} sort-icon`;
    }
    
    // Sort rows
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        
        // Try to parse as numbers
        const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
        const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return newDirection === 'asc' ? aNum - bNum : bNum - aNum;
        } else {
            return newDirection === 'asc' 
                ? aText.localeCompare(bText)
                : bText.localeCompare(aText);
        }
    });
    
    // Reorder DOM
    rows.forEach(row => tbody.appendChild(row));
}

// Notification System
function initNotifications() {
    // Create notification container
    if (!document.getElementById('notification-container')) {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
}

function showNotification(message, type = 'info', duration = 5000) {
    const container = document.getElementById('notification-container');
    if (!container) return;
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span class="notification-message">${message}</span>
            <button class="notification-close">&times;</button>
        </div>
    `;
    
    notification.style.cssText = `
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 15px 20px;
        margin-bottom: 10px;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    
    notification.style.borderLeft = `4px solid ${colors[type] || colors.info}`;
    
    container.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Close button
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => {
        closeNotification(notification);
    });
    
    closeBtn.style.cssText = `
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        opacity: 0.7;
        margin-left: auto;
    `;
    
    // Auto close
    if (duration > 0) {
        setTimeout(() => {
            closeNotification(notification);
        }, duration);
    }
    
    return notification;
}

function closeNotification(notification) {
    notification.style.transform = 'translateX(100%)';
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 300);
}

function getNotificationIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-triangle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    return icons[type] || icons.info;
}

// Loading States
function showLoading(element, text = 'Memuat...') {
    if (!element) return;
    
    const loader = document.createElement('div');
    loader.className = 'loading-overlay';
    loader.innerHTML = `
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">${text}</div>
        </div>
    `;
    
    loader.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        border-radius: inherit;
    `;
    
    element.style.position = 'relative';
    element.appendChild(loader);
    
    return loader;
}

function hideLoading(element) {
    if (!element) return;
    
    const loader = element.querySelector('.loading-overlay');
    if (loader) {
        loader.remove();
    }
}

// Export Functions
function exportData(data, filename, type = 'csv') {
    if (type === 'csv') {
        exportToCSV(data, filename);
    } else if (type === 'json') {
        exportToJSON(data, filename);
    }
}

function exportToCSV(data, filename) {
    if (!data || data.length === 0) {
        showNotification('Tidak ada data untuk diekspor', 'warning');
        return;
    }
    
    const csv = data.map(row => 
        Object.values(row).map(val => 
            `"${String(val).replace(/"/g, '""')}"`
        ).join(',')
    ).join('\n');
    
    const headers = Object.keys(data[0]).map(key => `"${key}"`).join(',');
    const csvContent = headers + '\n' + csv;
    
    downloadFile(csvContent, filename + '.csv', 'text/csv');
}

function exportToJSON(data, filename) {
    const jsonContent = JSON.stringify(data, null, 2);
    downloadFile(jsonContent, filename + '.json', 'application/json');
}

function downloadFile(content, filename, contentType) {
    const blob = new Blob([content], { type: contentType });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Focus management for accessibility
document.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        document.body.classList.add('user-is-tabbing');
    }
});

document.addEventListener('mousedown', function() {
    document.body.classList.remove('user-is-tabbing');
});

// Global error handler
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
    if (e.error && e.error.message && !e.error.message.includes('Script error')) {
        showNotification('Terjadi kesalahan teknis. Silakan refresh halaman.', 'error');
    }
});

// Performance monitoring
if ('performance' in window) {
    window.addEventListener('load', function() {
        setTimeout(function() {
            const perfData = performance.getEntriesByType('navigation')[0];
            if (perfData && perfData.loadEventEnd > 3000) {
                console.warn('Slow page load detected:', perfData.loadEventEnd + 'ms');
            }
        }, 100);
    });
}

// Make functions globally available
window.closeModal = closeModal;
window.showNotification = showNotification;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.exportData = exportData;