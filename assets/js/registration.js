/**
 * ZipPicks Registration Form JavaScript
 * 
 * Handles form validation, password strength, email suggestions,
 * and reCAPTCHA integration for the registration form
 *
 * @package ZipPicks\Core
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * ZipPicks Registration Handler
     */
    const ZipPicksRegistration = {
        
        // Configuration
        config: {
            emailDomains: ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com'],
            passwordStrengthColors: {
                weak: '#f87171',
                medium: '#facc15',
                strong: '#4ade80'
            },
            debounceDelay: 500
        },
        
        // State
        state: {
            recaptchaSolved: false,
            termsAccepted: false
        },
        
        /**
         * Initialize the registration form
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initializeState();
        },
        
        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$form = $('.zip-registration-form');
            this.$emailInput = $('#user_email');
            this.$emailSuggestion = $('#emailSuggestion');
            this.$passwordInput = $('#user_password');
            this.$passwordStrength = $('#passwordStrength');
            this.$termsCheckbox = $('#terms_agree');
            this.$submitBtn = $('#submitBtn');
            this.$errorMessages = $('.zip-error-messages');
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Email validation and suggestions
            this.$emailInput.on('blur', this.debounce(this.handleEmailBlur.bind(this), this.config.debounceDelay));
            
            // Password strength meter
            this.$passwordInput.on('input', this.handlePasswordInput.bind(this));
            
            // Terms checkbox
            this.$termsCheckbox.on('change', this.handleTermsChange.bind(this));
            
            // Form submission
            this.$form.on('submit', this.handleFormSubmit.bind(this));
            
            // Email suggestion click
            $(document).on('click', '#emailSuggestion a', this.handleEmailSuggestionClick.bind(this));
        },
        
        /**
         * Initialize form state
         */
        initializeState: function() {
            // Check initial state of terms checkbox
            if (this.$termsCheckbox.is(':checked')) {
                this.state.termsAccepted = true;
            }
            
            // Update submit button state
            this.updateSubmitButton();
        },
        
        /**
         * Handle email blur event
         */
        handleEmailBlur: function() {
            const email = this.$emailInput.val().trim();
            
            if (!email) {
                this.$emailSuggestion.hide();
                return;
            }
            
            const match = email.match(/^(.*)@(.*)$/);
            if (!match) {
                this.$emailSuggestion.hide();
                return;
            }
            
            const [, localPart, domain] = match;
            const domainLower = domain.toLowerCase();
            
            // Find potential domain suggestion
            const suggestion = this.findDomainSuggestion(domainLower);
            
            if (suggestion && suggestion !== domainLower) {
                const suggestedEmail = `${localPart}@${suggestion}`;
                this.$emailSuggestion
                    .html(`Did you mean <a href="#" data-email="${suggestedEmail}">${suggestedEmail}</a>?`)
                    .show();
            } else {
                this.$emailSuggestion.hide();
            }
        },
        
        /**
         * Find domain suggestion based on input
         */
        findDomainSuggestion: function(typedDomain) {
            // Check for exact match first
            if (this.config.emailDomains.includes(typedDomain)) {
                return null;
            }
            
            // Find similar domain
            return this.config.emailDomains.find(domain => {
                // Check if it starts with the same letter and contains similar characters
                return domain !== typedDomain && 
                       domain.startsWith(typedDomain[0]) && 
                       this.calculateSimilarity(typedDomain, domain) > 0.5;
            });
        },
        
        /**
         * Calculate string similarity (simple algorithm)
         */
        calculateSimilarity: function(str1, str2) {
            const longer = str1.length > str2.length ? str1 : str2;
            const shorter = str1.length > str2.length ? str2 : str1;
            
            if (longer.length === 0) {
                return 1.0;
            }
            
            const editDistance = this.getEditDistance(longer, shorter);
            return (longer.length - editDistance) / parseFloat(longer.length);
        },
        
        /**
         * Calculate edit distance between two strings
         */
        getEditDistance: function(str1, str2) {
            const matrix = [];
            
            for (let i = 0; i <= str2.length; i++) {
                matrix[i] = [i];
            }
            
            for (let j = 0; j <= str1.length; j++) {
                matrix[0][j] = j;
            }
            
            for (let i = 1; i <= str2.length; i++) {
                for (let j = 1; j <= str1.length; j++) {
                    if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                        matrix[i][j] = matrix[i - 1][j - 1];
                    } else {
                        matrix[i][j] = Math.min(
                            matrix[i - 1][j - 1] + 1, // substitution
                            matrix[i][j - 1] + 1,     // insertion
                            matrix[i - 1][j] + 1      // deletion
                        );
                    }
                }
            }
            
            return matrix[str2.length][str1.length];
        },
        
        /**
         * Handle email suggestion click
         */
        handleEmailSuggestionClick: function(e) {
            e.preventDefault();
            const suggestedEmail = $(e.target).data('email');
            this.$emailInput.val(suggestedEmail);
            this.$emailSuggestion.hide();
            this.$emailInput.focus();
        },
        
        /**
         * Handle password input
         */
        handlePasswordInput: function() {
            const password = this.$passwordInput.val();
            const strength = this.calculatePasswordStrength(password);
            
            const percent = Math.min(strength * 20, 100);
            let color = this.config.passwordStrengthColors.weak;
            
            if (percent >= 80) {
                color = this.config.passwordStrengthColors.strong;
            } else if (percent >= 40) {
                color = this.config.passwordStrengthColors.medium;
            }
            
            this.$passwordStrength.css({
                width: percent + '%',
                backgroundColor: color
            });
        },
        
        /**
         * Calculate password strength
         */
        calculatePasswordStrength: function(password) {
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Character variety checks
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[@$!%*?&#^()_+\-=\[\]{};':"\\|,.<>\/]/.test(password)) strength++;
            
            return strength;
        },
        
        /**
         * Handle terms checkbox change
         */
        handleTermsChange: function() {
            this.state.termsAccepted = this.$termsCheckbox.is(':checked');
            this.updateSubmitButton();
        },
        
        /**
         * Update submit button state
         */
        updateSubmitButton: function() {
            const shouldEnable = this.state.termsAccepted && 
                               (this.state.recaptchaSolved || !window.grecaptcha);
            
            this.$submitBtn.prop('disabled', !shouldEnable);
        },
        
        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            // Clear any existing error messages
            this.$errorMessages.slideUp(200);
            
            // Perform client-side validation
            const errors = this.validateForm();
            
            if (errors.length > 0) {
                e.preventDefault();
                this.displayErrors(errors);
                return false;
            }
            
            // Form is valid, allow submission
            return true;
        },
        
        /**
         * Validate form fields
         */
        validateForm: function() {
            const errors = [];
            
            // First name validation
            if (!$('input[name="first_name"]').val().trim()) {
                errors.push('First name is required.');
            }
            
            // Last name validation
            if (!$('input[name="last_name"]').val().trim()) {
                errors.push('Last name is required.');
            }
            
            // User role validation
            if (!$('select[name="user_role"]').val()) {
                errors.push('Please select a user role.');
            }
            
            // ZIP code validation
            const zipCode = $('input[name="zip_code"]').val().trim();
            if (!zipCode) {
                errors.push('ZIP code is required.');
            } else if (!/^\d{5}$/.test(zipCode)) {
                errors.push('Please enter a valid 5-digit ZIP code.');
            }
            
            // Email validation
            const email = this.$emailInput.val().trim();
            if (!email) {
                errors.push('Email address is required.');
            } else if (!this.isValidEmail(email)) {
                errors.push('Please enter a valid email address.');
            }
            
            // Password validation
            const password = this.$passwordInput.val();
            const confirmPassword = $('input[name="confirm_password"]').val();
            
            if (!password) {
                errors.push('Password is required.');
            } else if (password.length < 8) {
                errors.push('Password must be at least 8 characters long.');
            }
            
            if (password !== confirmPassword) {
                errors.push('Passwords do not match.');
            }
            
            // Terms validation
            if (!this.state.termsAccepted) {
                errors.push('You must agree to the Terms and Privacy Policy.');
            }
            
            // reCAPTCHA validation
            if (window.grecaptcha && !this.state.recaptchaSolved) {
                errors.push('Please complete the reCAPTCHA verification.');
            }
            
            return errors;
        },
        
        /**
         * Validate email format
         */
        isValidEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        /**
         * Display validation errors
         */
        displayErrors: function(errors) {
            const errorHtml = '<div class="zip-error-messages" role="alert"><ul>' +
                            errors.map(error => `<li>${this.escapeHtml(error)}</li>`).join('') +
                            '</ul></div>';
            
            this.$form.before(errorHtml);
            
            // Scroll to errors
            $('html, body').animate({
                scrollTop: this.$form.offset().top - 100
            }, 300);
        },
        
        /**
         * Escape HTML for security
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, m => map[m]);
        },
        
        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // reCAPTCHA callback functions (global scope)
    window.onRecaptchaSuccessCallback = function(token) {
        console.log('reCAPTCHA solved successfully');
        ZipPicksRegistration.state.recaptchaSolved = true;
        ZipPicksRegistration.updateSubmitButton();
    };
    
    window.onRecaptchaExpiredCallback = function() {
        console.warn('reCAPTCHA expired');
        ZipPicksRegistration.state.recaptchaSolved = false;
        ZipPicksRegistration.updateSubmitButton();
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.zip-registration-form').length) {
            ZipPicksRegistration.init();
        }
    });
    
})(jQuery);