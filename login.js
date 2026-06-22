document.addEventListener('DOMContentLoaded', function() {
    const userTypeButtons = document.querySelectorAll('.user-type-btn');
    const loginForm = document.getElementById('loginForm');
    const rememberCheckbox = document.getElementById('remember');

    // Handle user type switching
    userTypeButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            userTypeButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
        });
    });

    // Check for saved credentials
    const savedEmail = localStorage.getItem('rememberedEmail');
    const savedPassword = localStorage.getItem('rememberedPassword');
    if (savedEmail && savedPassword) {
        document.getElementById('email').value = savedEmail;
        document.getElementById('password').value = savedPassword;
        rememberCheckbox.checked = true;
    }

    // Form submission handling
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitButton = this.querySelector('.submit-btn');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Logging in...';
        }
        
        try {
            // Get form data
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            const activeButton = document.querySelector('.user-type-btn.active');
            
            if (!emailField || !passwordField) {
                alert('Email and password fields not found!');
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Login';
                }
                return;
            }
            
            const email = emailField.value.trim();
            const password = passwordField.value;
            
            // Validate inputs
            if (!email || !password) {
                alert('Please enter both email and password');
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Login';
                }
                return;
            }
            
            // Handle remember me functionality
            if (rememberCheckbox && rememberCheckbox.checked) {
                localStorage.setItem('rememberedEmail', email);
                localStorage.setItem('rememberedPassword', password);
            } else {
                localStorage.removeItem('rememberedEmail');
                localStorage.removeItem('rememberedPassword');
            }

            // Construct endpoint URL
            const currentUrl = window.location.href;
            const baseUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/') + 1);
            const endpoint = 'login.php';
            
            console.log('Login - Endpoint:', endpoint);
            console.log('Login - Email:', email);
            
            let response;
            try {
                response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ email, password })
                });
                console.log('Login - Response status:', response.status);
            } catch (fetchError) {
                console.error('Login - Fetch error:', fetchError);
                alert('Cannot connect to server!\n\nPlease check:\n1. XAMPP Apache is running\n2. Access via: http://localhost/DEProject/login.html\n3. File login.php exists\n\nError: ' + fetchError.message);
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Login';
                }
                return;
            }

            // Parse response
            let result = null;
            let rawText = '';
            const contentType = response.headers.get('content-type') || '';
            
            try {
                if (contentType.includes('application/json')) {
                    result = await response.json();
                } else {
                    rawText = await response.text();
                    console.log('Login - Response text:', rawText);
                    try { 
                        result = JSON.parse(rawText); 
                    } catch (e) {
                        const preview = rawText
                            ? rawText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220)
                            : '';
                        throw new Error(preview || `Server returned non-JSON response (HTTP ${response.status}). Open this page through http://localhost/DEProject/login.html`);
                    }
                }

                if (!response.ok || !result || !result.success) {
                    const message = (result && result.error) ? result.error : (rawText || `Login failed (HTTP ${response.status})`);
                    alert(message);
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Login';
                    }
                    return;
                }

                // Login successful - redirect based on role
                const role = result.user && result.user.role ? result.user.role : 'student';
                const base = baseUrl; // Use same base URL

                if (result.user) {
                    localStorage.setItem('currentUser', JSON.stringify(result.user));
                }
                
                console.log('Login successful - Role:', role);
                console.log('Redirecting to base:', base);
                
                switch(role) {
                    case 'student':
                        window.location.href = base + 'student-dashboard.html';
                        break;
                    case 'placement coordinator':
                        window.location.href = base + 'placement_cordinator.html';
                        break;
                    case 'admin':
                        window.location.href = base + 'admin-dashboard.html';
                        break;
                    case 'company':
                        window.location.href = base + 'company-dashboard.html';
                        break;
                    default:
                        window.location.href = base + 'student-dashboard.html';
                }
            } catch (parseError) {
                console.error('Login - Parse error:', parseError);
                alert('Error: ' + (parseError.message || 'Failed to process server response'));
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Login';
                }
                return;
            }
        } catch (err) {
            console.error('Login - Unexpected error:', err);
            alert('Unexpected error: ' + (err.message || 'Please try again.'));
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Login';
            }
        }
    });

    // Handle forgot password link (if it exists)
    const forgotPasswordLink = document.querySelector('.forgot-password');
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            const email = prompt('Please enter your email address to reset your password:');
            if (email) {
                // Here you would typically send a password reset email
                alert('Password reset instructions have been sent to your email.');
            }
        });
    }
}); 
