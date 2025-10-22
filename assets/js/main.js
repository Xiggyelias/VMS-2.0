// Form Validation Functions
const validateForm = (formElement) => {
    const requiredFields = formElement.querySelectorAll('[required]');
    let isValid = true;
    
    // Reset previous error messages
    clearErrors(formElement);
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showError(field, 'This field is required');
            isValid = false;
        } else if (field.dataset.type) {
            // Validate specific field types
            if (!validateFieldType(field, field.dataset.type)) {
                isValid = false;
            }
        }
    });
    
    return isValid;
};

const validateFieldType = (field, type) => {
    const value = field.value.trim();
    let isValid = true;
    
    switch (type) {
        case 'phone':
            if (!/^\+?[\d\s-]{10,}$/.test(value)) {
                showError(field, 'Please enter a valid phone number');
                isValid = false;
            }
            break;
        case 'license':
            if (!/^[A-Z0-9\s-]{5,}$/.test(value)) {
                showError(field, 'Please enter a valid license number');
                isValid = false;
            }
            break;
        case 'email':
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                showError(field, 'Please enter a valid email address');
                isValid = false;
            }
            break;
    }
    
    return isValid;
};

const showError = (field, message) => {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    field.classList.add('error');
    field.parentNode.appendChild(errorDiv);
};

const clearErrors = (form) => {
    form.querySelectorAll('.error-message').forEach(error => error.remove());
    form.querySelectorAll('.error').forEach(field => field.classList.remove('error'));
};

// Dynamic Form Functions
const addVehicleSection = (containerId) => {
    const container = document.getElementById(containerId);
    const vehicleTemplate = document.getElementById('vehicle-template');
    const newSection = vehicleTemplate.content.cloneNode(true);
    
    // Reset form fields
    newSection.querySelectorAll('input').forEach(input => {
        input.value = '';
    });
    
    // Add remove button
    const removeBtn = document.createElement('button');
    removeBtn.textContent = 'Remove Vehicle';
    removeBtn.className = 'btn btn-danger remove-vehicle';
    removeBtn.onclick = (e) => {
        e.preventDefault();
        confirmAction('Are you sure you want to remove this vehicle?', (confirmed) => {
            if (confirmed) {
                e.target.closest('.vehicle-section').remove();
            }
        });
    };
    
    newSection.querySelector('.vehicle-section').appendChild(removeBtn);
    container.appendChild(newSection);
};

const addDriverSection = (vehicleSection) => {
    const driverTemplate = document.getElementById('driver-template');
    const driversContainer = vehicleSection.querySelector('.drivers-container');
    const newDriver = driverTemplate.content.cloneNode(true);
    
    // Reset form fields
    newDriver.querySelectorAll('input').forEach(input => {
        input.value = '';
    });
    
    // Add remove button
    const removeBtn = document.createElement('button');
    removeBtn.textContent = 'Remove Driver';
    removeBtn.className = 'btn btn-danger remove-driver';
    removeBtn.onclick = (e) => {
        e.preventDefault();
        confirmAction('Are you sure you want to remove this driver?', (confirmed) => {
            if (confirmed) {
                e.target.closest('.driver-section').remove();
            }
        });
    };
    
    newDriver.querySelector('.driver-section').appendChild(removeBtn);
    driversContainer.appendChild(newDriver);
};

// UI Feedback Functions
const showToast = (message, type = 'success') => {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    // Show toast
    setTimeout(() => toast.classList.add('show'), 100);
    
    // Hide and remove toast
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
};

// Modal Functions
const showModal = (modalId) => {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
};

const closeModal = (modalId) => {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
};

const showLogoutPopup = () => {
    showModal('logout-modal');
};

const closeLogoutPopup = () => {
    closeModal('logout-modal');
};

const confirmLogout = () => {
    // Add actual logout logic here
    window.location.href = 'login.html';
};

// Confirmation Dialog
const confirmAction = (message, callback) => {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <p>${message}</p>
            <div class="modal-buttons">
                <button class="btn btn-danger">Confirm</button>
                <button class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'flex';
    
    const confirmBtn = modal.querySelector('.btn-danger');
    const cancelBtn = modal.querySelector('.btn-secondary');
    
    confirmBtn.onclick = () => {
        callback(true);
        modal.remove();
    };
    
    cancelBtn.onclick = () => {
        callback(false);
        modal.remove();
    };
};

// Page Utility Functions
const smoothScroll = (target) => {
    const element = document.querySelector(target);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
};

// Loading State Management
const setLoading = (button, isLoading) => {
    if (isLoading) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.innerHTML = '<span class="spinner"></span> Loading...';
    } else {
        button.disabled = false;
        button.textContent = button.dataset.originalText;
    }
};

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    // Form validation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', (e) => {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });
    });
    
    // Back button functionality
    document.querySelectorAll('.btn-back').forEach(button => {
        button.addEventListener('click', () => {
            window.history.back();
        });
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
}); 