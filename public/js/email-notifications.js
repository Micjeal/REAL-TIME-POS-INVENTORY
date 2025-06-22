/**
 * Email Notifications for MTECH UGANDA
 * Handles sending email notifications for user activities
 */

// Base URL for API endpoints
const API_BASE_URL = window.location.origin + '/MTECH%20UGANDA/public/api';

/**
 * Send an email notification
 * @param {string} type - Type of notification ('login', 'logout', 'daily_report')
 * @param {Object} data - Additional data for the email
 * @returns {Promise<Object>} - Response from the server
 */
async function sendEmailNotification(type, data = {}) {
    try {
        const response = await fetch(`${API_BASE_URL}/send_email.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                type,
                username: data.username || 'System',
                timestamp: new Date().toISOString(),
                ...data
            })
        });

        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Failed to send email notification');
        }

        return result;
    } catch (error) {
        console.error('Email notification error:', error);
        throw error;
    }
}

/**
 * Track user login and send notification
 * @param {string} username - Username of the logged-in user
 */
function trackLogin(username) {
    if (!username) return;
    
    // Send login notification
    sendEmailNotification('login', { username })
        .then(() => console.log('Login notification sent'))
        .catch(err => console.error('Failed to send login notification:', err));
}

/**
 * Track user logout and send notification
 * @param {string} username - Username of the logged-out user
 */
function trackLogout(username) {
    if (!username) return;
    
    // Send logout notification
    sendEmailNotification('logout', { username })
        .then(() => console.log('Logout notification sent'))
        .catch(err => console.error('Failed to send logout notification:', err));
}

/**
 * Generate and send daily sales report
 */
async function sendDailySalesReport() {
    try {
        const response = await sendEmailNotification('daily_report');
        console.log('Daily sales report sent:', response);
        return response;
    } catch (error) {
        console.error('Failed to send daily sales report:', error);
        throw error;
    }
}

/**
 * Schedule the daily sales report
 * @param {string} time - Time in 24-hour format (e.g., '23:59')
 */
function scheduleDailyReport(time = '23:59') {
    // Parse the time
    const [hours, minutes] = time.split(':').map(Number);
    
    function checkTime() {
        const now = new Date();
        
        // Check if it's time to send the report
        if (now.getHours() === hours && now.getMinutes() === minutes) {
            sendDailySalesReport();
        }
    }
    
    // Check the time every minute
    setInterval(checkTime, 60000);
    
    // Initial check in case the page loads at exactly the scheduled time
    checkTime();
}

// Initialize the daily report scheduler when the page loads
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize on pages where we're logged in
    if (document.body.classList.contains('logged-in')) {
        scheduleDailyReport();
    }
});

// Export functions for use in other scripts
window.EmailNotifications = {
    trackLogin,
    trackLogout,
    sendDailySalesReport,
    scheduleDailyReport
};
