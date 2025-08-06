// BusyLancer App JavaScript
class BusyLancerApp {
    constructor() {
        this.apiBase = '/api/';
        this.init();
    }

    init() {
        this.bindEvents();
        this.checkAuthStatus();
        this.loadJobs();
    }

    bindEvents() {
        // Registration form
        const registerForm = document.querySelector('#loginModal form');
        if (registerForm) {
            registerForm.addEventListener('submit', (e) => this.handleRegister(e));
        }

        // Login form
        const loginForm = document.querySelector('form[data-form="login"]');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Job search form
        const searchForm = document.querySelector('form[data-form="search"]');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => this.handleJobSearch(e));
        }

        // Job posting form
        const jobForm = document.querySelector('form[data-form="job"]');
        if (jobForm) {
            jobForm.addEventListener('submit', (e) => this.handleJobPost(e));
        }

        // Application form
        const applyForm = document.querySelector('form[data-form="apply"]');
        if (applyForm) {
            applyForm.addEventListener('submit', (e) => this.handleJobApply(e));
        }
    }

    async handleRegister(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const data = {
            email: formData.get('email') || form.querySelector('input[type="email"]').value,
            name: formData.get('name') || form.querySelector('input[type="text"]').value,
            user_type: formData.get('user_type') || form.querySelector('select').value,
            phone: formData.get('phone') || '',
            location: formData.get('location') || ''
        };

        try {
            this.showLoading(form);
            const response = await this.apiCall('register.php', data);
            
            if (response.success) {
                this.showSuccess('Registration successful! Please check your email for confirmation.');
                form.reset();
                // Close modal if it exists
                const modal = document.getElementById('loginModal');
                if (modal) {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                }
            } else {
                this.showError(response.error || 'Registration failed');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.hideLoading(form);
        }
    }

    async handleLogin(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const data = {
            email: formData.get('email') || form.querySelector('input[type="email"]').value
        };

        try {
            this.showLoading(form);
            const response = await this.apiCall('login.php', data);
            
            if (response.success) {
                this.showSuccess('Login successful!');
                this.updateUIForLoggedInUser(response.user);
                // Redirect to dashboard
                setTimeout(() => {
                    window.location.href = this.getDashboardUrl(response.user.user_type);
                }, 1000);
            } else {
                this.showError(response.error || 'Login failed');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.hideLoading(form);
        }
    }

    async handleJobSearch(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const params = new URLSearchParams({
            search: formData.get('search') || '',
            location: formData.get('location') || '',
            job_type: formData.get('job_type') || '',
            page: 1
        });

        try {
            this.showLoading(form);
            const response = await this.apiCall(`jobs.php?${params}`);
            
            if (response.success) {
                this.displayJobs(response.jobs);
            } else {
                this.showError(response.error || 'Failed to load jobs');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.hideLoading(form);
        }
    }

    async handleJobPost(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const data = {
            title: formData.get('title'),
            description: formData.get('description'),
            location: formData.get('location'),
            job_type: formData.get('job_type'),
            salary_min: formData.get('salary_min'),
            salary_max: formData.get('salary_max')
        };

        try {
            this.showLoading(form);
            const response = await this.apiCall('jobs.php', data);
            
            if (response.success) {
                this.showSuccess('Job posted successfully!');
                form.reset();
            } else {
                this.showError(response.error || 'Failed to post job');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        } finally {
            this.hideLoading(form);
        }
    }

    async loadJobs() {
        try {
            const response = await this.apiCall('jobs.php');
            if (response.success) {
                this.displayJobs(response.jobs);
            }
        } catch (error) {
            console.error('Failed to load jobs:', error);
        }
    }

    displayJobs(jobs) {
        const container = document.querySelector('.job-listings') || document.querySelector('.jobs-container');
        if (!container) return;

        if (jobs.length === 0) {
            container.innerHTML = '<div class="text-center py-5"><h4>No jobs found</h4><p>Try adjusting your search criteria.</p></div>';
            return;
        }

        const jobsHTML = jobs.map(job => `
            <div class="job-card mb-4 p-4 border rounded">
                <h5 class="mb-2">${this.escapeHtml(job.title)}</h5>
                <p class="text-muted mb-2">${this.escapeHtml(job.employer_name || 'Anonymous')} • ${this.escapeHtml(job.location)}</p>
                <p class="mb-3">${this.escapeHtml(job.description.substring(0, 200))}${job.description.length > 200 ? '...' : ''}</p>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="badge bg-primary">${this.escapeHtml(job.job_type)}</span>
                    ${job.salary_min ? `<span class="text-success">$${job.salary_min}${job.salary_max ? ' - $' + job.salary_max : ''}</span>` : ''}
                    <button class="btn btn-outline-primary btn-sm" onclick="app.applyToJob(${job.id})">Apply Now</button>
                </div>
            </div>
        `).join('');

        container.innerHTML = jobsHTML;
    }

    async applyToJob(jobId) {
        if (!this.isLoggedIn()) {
            this.showError('Please login to apply for jobs');
            return;
        }

        try {
            const response = await this.apiCall('apply.php', { job_id: jobId });
            if (response.success) {
                this.showSuccess('Application submitted successfully!');
            } else {
                this.showError(response.error || 'Failed to submit application');
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
        }
    }

    async apiCall(endpoint, data = null) {
        const url = this.apiBase + endpoint;
        const options = {
            method: data ? 'POST' : 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'API call failed');
        }

        return result;
    }

    showLoading(form) {
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
        }
    }

    hideLoading(form) {
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = false;
            button.innerHTML = button.getAttribute('data-original-text') || 'Submit';
        }
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    checkAuthStatus() {
        // Check if user is logged in by making a simple API call
        fetch(this.apiBase + 'check-auth.php')
            .then(response => response.json())
            .then(data => {
                if (data.logged_in) {
                    this.updateUIForLoggedInUser(data.user);
                }
            })
            .catch(error => console.error('Auth check failed:', error));
    }

    updateUIForLoggedInUser(user) {
        // Update navigation to show user info
        const loginBtn = document.querySelector('.signin-btn');
        if (loginBtn) {
            loginBtn.textContent = user.name;
            loginBtn.href = this.getDashboardUrl(user.user_type);
        }

        // Hide login/register buttons
        const registerBtn = document.querySelector('.btn-five');
        if (registerBtn) {
            registerBtn.style.display = 'none';
        }
    }

    getDashboardUrl(userType) {
        switch (userType) {
            case 'business':
                return 'dashboard/employer-dashboard-index.html';
            case 'busylancer':
                return 'dashboard/candidate-dashboard-index.html';
            default:
                return 'dashboard/candidate-dashboard-index.html';
        }
    }

    isLoggedIn() {
        // Simple check - in production, you'd verify with the server
        return document.cookie.includes('PHPSESSID');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new BusyLancerApp();
});

// Global functions for inline onclick handlers
window.applyToJob = (jobId) => window.app.applyToJob(jobId);
window.searchJobs = () => window.app.handleJobSearch(event);