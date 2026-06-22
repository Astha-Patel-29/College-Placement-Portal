document.addEventListener('DOMContentLoaded', function() {
    const userTypeButtons = document.querySelectorAll('.user-type-btn');
    const userSpecificFields = document.querySelectorAll('.user-specific');
    const registrationForm = document.getElementById('registrationForm');

    if (!registrationForm) {
        console.error('Registration form not found!');
        return;
    }

    function getFriendlyServerMessage(rawText, status) {
        const text = String(rawText || '').trim();
        if (!text) {
            return `Registration failed (HTTP ${status})`;
        }
        const withoutTags = text.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        return withoutTags || `Registration failed (HTTP ${status})`;
    }

    function setSectionEnabled(sectionElement, isEnabled) {
        const fields = sectionElement.querySelectorAll('input, select, textarea');
        fields.forEach(el => {
            el.disabled = !isEnabled;
        });
    }

    function showFieldsForUserType(userType) {
        userSpecificFields.forEach(field => {
            field.style.display = 'none';
            setSectionEnabled(field, false);
        });
        const userTypeClass = userType.toLowerCase().replace(/\s+/g, '-');
        const specificFields = document.querySelector(`.${userTypeClass}-fields`);
        if (specificFields) {
            specificFields.style.display = 'block';
            setSectionEnabled(specificFields, true);
        }
    }

    userTypeButtons.forEach(button => {
        button.addEventListener('click', function() {
            userTypeButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            const userType = this.getAttribute('data-type');
            showFieldsForUserType(userType);
        });
    });

    const initiallyActive = document.querySelector('.user-type-btn.active');
    if (initiallyActive) {
        showFieldsForUserType(initiallyActive.getAttribute('data-type'));
    }

    registrationForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitButton = this.querySelector('.submit-btn');
        submitButton.disabled = true;
        submitButton.textContent = 'Registering...';

        try {
            const data = {};

            // --- 1. Get active user type FIRST (needed for conditional validation) ---
            const activeButton = document.querySelector('.user-type-btn.active');
            if (!activeButton) {
                alert('Please select a user type');
                submitButton.disabled = false;
                submitButton.textContent = 'Register';
                return;
            }

            let userType = activeButton.getAttribute('data-type');
            if (userType === 'Placement coordinator') {
                userType = 'placement coordinator';
            }
            data.userType = userType;

            // --- 2. Collect & validate common required fields ---
            const nameField = document.getElementById('name');
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirmPassword');

            data.name = nameField ? nameField.value.trim() : '';
            data.email = emailField ? emailField.value.trim() : '';
            data.password = passwordField ? passwordField.value : '';
            data.confirmPassword = confirmPasswordField ? confirmPasswordField.value : '';

            if (!data.name || !data.email || !data.password || !data.confirmPassword) {
                const missing = [];
                if (!data.name) missing.push('Name');
                if (!data.email) missing.push('Email');
                if (!data.password) missing.push('Password');
                if (!data.confirmPassword) missing.push('Confirm Password');
                alert('Please fill in all required fields: ' + missing.join(', '));
                submitButton.disabled = false;
                submitButton.textContent = 'Register';
                return;
            }

            // --- 3. Password validation ---
            if (data.password.length < 8) {
                alert('Password must be at least 8 characters long.');
                passwordField.focus();
                submitButton.disabled = false;
                submitButton.textContent = 'Register';
                return;
            }

            const hasLower = /[a-z]/.test(data.password);
            const hasUpper = /[A-Z]/.test(data.password);
            const hasNumber = /[0-9]/.test(data.password);
            const hasSpecial = /[^a-zA-Z0-9]/.test(data.password);

            if (!hasLower || !hasUpper || !hasNumber || !hasSpecial) {
                alert('Password must contain:\n- At least 8 characters\n- One lowercase letter\n- One uppercase letter\n- One number\n- One special character');
                passwordField.focus();
                submitButton.disabled = false;
                submitButton.textContent = 'Register';
                return;
            }

            if (data.password !== data.confirmPassword) {
                alert('Passwords do not match!');
                confirmPasswordField.focus();
                submitButton.disabled = false;
                submitButton.textContent = 'Register';
                return;
            }

            // --- 4. Student-only fields (phone & enrollment) ---
            if (userType === 'student') {
                const phoneField = document.getElementById('phone');
                const rollField = document.getElementById('rollNumber');

                const phone = phoneField ? phoneField.value.trim() : '';
                const enroll = rollField ? rollField.value.trim() : '';

                const phonePattern = /^[0-9]{10}$/;
                if (!phonePattern.test(phone)) {
                    alert('Phone number must be exactly 10 digits');
                    submitButton.disabled = false;
                    submitButton.textContent = 'Register';
                    return;
                }

                const enrollPattern = /^[0-9]{12}$/;
                if (!enrollPattern.test(enroll)) {
                    alert('Enrollment number must be exactly 12 digits');
                    submitButton.disabled = false;
                    submitButton.textContent = 'Register';
                    return;
                }

                data.phone = phone;
                data.rollNumber = enroll;

                const departmentField = document.getElementById('department');
                if (departmentField && departmentField.value) {
                    data.department = departmentField.value;
                }
            }

            // --- 5. Placement coordinator fields ---
            if (userType === 'placement coordinator') {
                const rollNumberField = document.getElementById('rollNumber');
                const phoneField = document.getElementById('phone');
                const departmentField = document.getElementById('department');
                const hodDepartmentField = document.getElementById('hodDepartment');

                if (rollNumberField && rollNumberField.value) data.rollNumber = rollNumberField.value.trim();
                if (phoneField && phoneField.value) data.phone = phoneField.value.trim();
                if (departmentField && departmentField.value) data.department = departmentField.value;
                if (hodDepartmentField && hodDepartmentField.value) data.department = hodDepartmentField.value;
            }

            // --- 6. Company fields ---
            if (userType === 'company') {
                const companyNameField = document.getElementById('companyName');
                const companyTypeField = document.getElementById('companyType');

                if (companyNameField && companyNameField.value) data.companyName = companyNameField.value.trim();
                if (companyTypeField && companyTypeField.value) data.companyType = companyTypeField.value;
            }

            // Admin and Recruiter only need the common fields — no extra validation needed.

            console.log('Sending data:', { ...data, password: '***', confirmPassword: '***' });

            const currentUrl = window.location.href;
            const baseUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/') + 1);
            const endpoint = baseUrl + 'register.php';

            console.log('Endpoint URL:', endpoint);

            let response;
            try {
                response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
            } catch (fetchError) {
                let errorMsg = 'Cannot connect to server!\n\n';
                errorMsg += 'Please check:\n';
                errorMsg += '1. XAMPP Apache is running\n';
                errorMsg += '2. You are accessing via: http://localhost/DEProject/registration.html\n';
                errorMsg += '3. File register.php exists in the same folder\n';
                errorMsg += 'Error: ' + fetchError.message;
                alert(errorMsg);
                submitButton.disabled = false;
                submitButton.textContent = 'Register';
                return;
            }

            let result = null;
            let rawText = '';
            const contentType = response.headers.get('content-type') || '';

            try {
                if (contentType.includes('application/json')) {
                    result = await response.json();
                } else {
                    rawText = await response.text();
                    try {
                        result = JSON.parse(rawText);
                    } catch (e) {
                        throw new Error(getFriendlyServerMessage(rawText, response.status));
                    }
                }

                if (!response.ok || !result || !result.success) {
                    const message = (result && result.error) ? result.error : getFriendlyServerMessage(rawText, response.status);
                    alert(message);
                    submitButton.disabled = false;
                    submitButton.textContent = 'Register';
                    return;
                }

                alert('Registration successful!');
                const regType = String(data.userType || '').toLowerCase();
                if (regType === 'student') {
                    localStorage.setItem('currentUser', JSON.stringify({
                        id: result.id || null,
                        name: result.name || data.name,
                        email: result.email || data.email,
                        role: regType
                    }));
                    window.location.href = baseUrl + 'student_details.html';
                    return;
                }
                this.reset();
                submitButton.textContent = 'Register';
            } catch (parseError) {
                console.error('Error parsing response:', parseError);
                alert('Error: ' + (parseError.message || 'Failed to process server response'));
                submitButton.disabled = false;
                submitButton.textContent = 'Register';
            }
        } catch (err) {
            console.error('Unexpected error:', err);
            alert('An error occurred: ' + (err.message || 'Please check the browser console (F12) for details.'));
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Register';
            }
        }
    });
});