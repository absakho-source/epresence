/**
 * e-Présence - Scripts principaux
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            }
        }, 5000);
    });

    // Initialize tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Copy to clipboard functionality
    const copyButtons = document.querySelectorAll('[data-copy]');
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const textToCopy = this.getAttribute('data-copy');
            copyToClipboard(textToCopy, this);
        });
    });

    // Initialize QR codes if containers exist
    initQRCodes();

    // Initialize global search
    initGlobalSearch();
});

/**
 * Copy text to clipboard
 */
function copyToClipboard(text, button) {
    navigator.clipboard.writeText(text).then(() => {
        // Show feedback
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check me-1"></i>Copié !';
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-success');

        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
        }, 2000);
    }).catch(err => {
        console.error('Erreur lors de la copie:', err);
        alert('Impossible de copier le lien. Veuillez le sélectionner manuellement.');
    });
}

/**
 * Initialize QR codes
 */
function initQRCodes() {
    const qrContainers = document.querySelectorAll('[data-qr-code]');
    qrContainers.forEach(container => {
        const url = container.getAttribute('data-qr-code');
        const size = parseInt(container.getAttribute('data-qr-size')) || 200;

        // Clear container first
        container.innerHTML = '';

        if (typeof QRCode !== 'undefined') {
            try {
                new QRCode(container, {
                    text: url,
                    width: size,
                    height: size,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
            } catch (error) {
                console.error('Erreur QR Code:', error);
            }
        }
    });
}

/**
 * Generate QR code for a specific URL
 */
function generateQRCode(containerId, url, size = 200) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Clear existing content
    container.innerHTML = '';

    if (typeof QRCode !== 'undefined') {
        try {
            new QRCode(container, {
                text: url,
                width: size,
                height: size,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
        } catch (error) {
            console.error('Erreur QR Code:', error);
            container.innerHTML = '<p class="text-danger">Erreur lors de la génération du QR code</p>';
        }
    }
}

/**
 * Download QR code as image
 */
function downloadQRCode(containerId, filename = 'qrcode.png') {
    const container = document.getElementById(containerId);
    if (!container) return;

    // qrcodejs creates both canvas and img elements
    const img = container.querySelector('img');
    const canvas = container.querySelector('canvas');

    let dataUrl;
    if (img && img.src) {
        dataUrl = img.src;
    } else if (canvas) {
        dataUrl = canvas.toDataURL('image/png');
    } else {
        return;
    }

    const link = document.createElement('a');
    link.download = filename;
    link.href = dataUrl;
    link.click();
}

/**
 * Print QR code
 */
function printQRCode(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // qrcodejs creates both canvas and img elements
    const img = container.querySelector('img');
    const canvas = container.querySelector('canvas');

    let dataUrl;
    if (img && img.src) {
        dataUrl = img.src;
    } else if (canvas) {
        dataUrl = canvas.toDataURL('image/png');
    } else {
        return;
    }

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>QR Code - e-Présence</title>
            <style>
                body {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                    font-family: Arial, sans-serif;
                }
                .qr-container {
                    text-align: center;
                }
                img {
                    max-width: 300px;
                }
                h2 {
                    margin-top: 20px;
                    color: #333;
                }
            </style>
        </head>
        <body>
            <div class="qr-container">
                <img src="${dataUrl}" alt="QR Code">
                <h2>Scannez pour émarger</h2>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

/**
 * Confirm action with modal
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Format date to French locale
 */
function formatDateFr(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Format time
 */
function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    return `${hours}h${minutes}`;
}

/**
 * Show loading state on button
 */
function setButtonLoading(button, isLoading) {
    if (isLoading) {
        button.disabled = true;
        button.dataset.originalText = button.innerHTML;
        button.innerHTML = '<span class="loading-spinner me-2"></span>Chargement...';
    } else {
        button.disabled = false;
        button.innerHTML = button.dataset.originalText || button.innerHTML;
    }
}

/**
 * AJAX form submission helper
 */
async function submitForm(form, options = {}) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('[type="submit"]');

    if (submitBtn) setButtonLoading(submitBtn, true);

    try {
        const response = await fetch(form.action, {
            method: form.method || 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (options.onSuccess && data.success) {
            options.onSuccess(data);
        } else if (options.onError && !data.success) {
            options.onError(data);
        }

        return data;
    } catch (error) {
        console.error('Erreur de soumission:', error);
        if (options.onError) {
            options.onError({ error: 'Une erreur est survenue. Veuillez réessayer.' });
        }
    } finally {
        if (submitBtn) setButtonLoading(submitBtn, false);
    }
}

/**
 * Initialize global search functionality
 */
function initGlobalSearch() {
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('searchResults');

    if (!searchInput || !searchResults) return;

    let searchTimeout = null;
    let currentQuery = '';

    // Get site URL from a data attribute or infer from current location
    const siteUrl = document.body.dataset.siteUrl || window.location.origin;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        // Clear previous timeout
        if (searchTimeout) clearTimeout(searchTimeout);

        // Hide results if query is too short
        if (query.length < 2) {
            searchResults.classList.remove('show');
            searchResults.innerHTML = '';
            return;
        }

        // Debounce search (300ms)
        searchTimeout = setTimeout(() => {
            if (query !== currentQuery) {
                currentQuery = query;
                performSearch(query);
            }
        }, 300);
    });

    // Close results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.remove('show');
        }
    });

    // Show results when focusing on input with existing query
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2 && searchResults.innerHTML) {
            searchResults.classList.add('show');
        }
    });

    async function performSearch(query) {
        try {
            const response = await fetch(`${siteUrl}/api/search.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            displayResults(data);
        } catch (error) {
            console.error('Erreur de recherche:', error);
            searchResults.innerHTML = '<div class="dropdown-item text-danger">Erreur de recherche</div>';
            searchResults.classList.add('show');
        }
    }

    function displayResults(data) {
        const sheets = data.sheets || [];
        const participants = data.participants || [];

        if (sheets.length === 0 && participants.length === 0) {
            searchResults.innerHTML = '<div class="dropdown-item text-muted">Aucun résultat</div>';
            searchResults.classList.add('show');
            return;
        }

        let html = '';

        // Feuilles
        if (sheets.length > 0) {
            html += '<h6 class="dropdown-header"><i class="bi bi-file-earmark-text me-1"></i>Feuilles</h6>';
            sheets.forEach(sheet => {
                const statusBadge = getStatusBadge(sheet.status);
                html += `
                    <a class="dropdown-item d-flex justify-content-between align-items-center" href="${siteUrl}/pages/dashboard/view.php?id=${sheet.id}">
                        <div>
                            <div class="fw-medium text-truncate" style="max-width: 250px;">${escapeHtml(sheet.title)}</div>
                            <small class="text-muted">${sheet.date} - ${escapeHtml(sheet.creator)}</small>
                        </div>
                        <span class="badge ${statusBadge.class}">${statusBadge.label}</span>
                    </a>
                `;
            });
        }

        // Participants
        if (participants.length > 0) {
            if (sheets.length > 0) {
                html += '<div class="dropdown-divider"></div>';
            }
            html += '<h6 class="dropdown-header"><i class="bi bi-people me-1"></i>Participants</h6>';
            participants.forEach(p => {
                html += `
                    <a class="dropdown-item" href="${siteUrl}/pages/dashboard/view.php?id=${p.sheet_id}">
                        <div class="fw-medium">${escapeHtml(p.name)}</div>
                        <small class="text-muted">${escapeHtml(p.email)}${p.structure ? ' - ' + escapeHtml(p.structure) : ''}</small>
                        <div class="small text-primary"><i class="bi bi-file-earmark me-1"></i>${escapeHtml(p.sheet_title)}</div>
                    </a>
                `;
            });
        }

        searchResults.innerHTML = html;
        searchResults.classList.add('show');
    }

    function getStatusBadge(status) {
        const badges = {
            'active': { class: 'bg-success', label: 'Active' },
            'closed': { class: 'bg-secondary', label: 'Clôturée' },
            'archived': { class: 'bg-dark', label: 'Archivée' }
        };
        return badges[status] || { class: 'bg-secondary', label: status };
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
