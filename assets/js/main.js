document.addEventListener('DOMContentLoaded', function () {
    // Validation for Signup Page
    const signupForm = document.querySelector('form[action="signup.php"]');
    if (signupForm) {
        const passwordField = signupForm.querySelector('#password');
        const passwordMessage = signupForm.querySelector('#password-message');

        passwordField.addEventListener('input', function () {
            const password = this.value;
            if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password) || !/[!@#$%^&*]/.test(password)) {
                passwordMessage.textContent = 'Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.';
                passwordMessage.style.color = 'red';
            } else {
                passwordMessage.textContent = 'Password is valid!';
                passwordMessage.style.color = 'green';
            }
        });
    }

    // Smooth Scroll for Links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Add Animation to Buttons
    document.querySelectorAll('button').forEach(button => {
        button.addEventListener('click', function () {
            this.classList.add('clicked');
            setTimeout(() => this.classList.remove('clicked'), 300);
        });
    });
});
document.addEventListener('DOMContentLoaded', function () {
    // استرجاع الرسائل من URL
    const urlParams = new URLSearchParams(window.location.search);
    const message = urlParams.get('message');
    const error = urlParams.get('error');

    if (message) {
        showAlert(message, 'success');
    } else if (error) {
        showAlert(error, 'error');
    }

    // دالة لإظهار الرسائل
    function showAlert(text, type) {
        const alertBox = document.createElement('div');
        alertBox.classList.add('alert', type);

        alertBox.innerHTML = `
            <span>${text}</span>
            <span class="close-btn" onclick="this.parentElement.remove();">&times;</span>
        `;

        document.body.appendChild(alertBox);

        // إضافة فئة "show" لإظهار الرسالة
        setTimeout(() => alertBox.classList.add('show'), 50);

        // إزالة الرسالة بعد 5 ثوانٍ
        setTimeout(() => {
            alertBox.classList.remove('show');
            setTimeout(() => alertBox.remove(), 300); // انتظار انتهاء الانتقال
        }, 5000);
    }
});