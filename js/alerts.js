document.addEventListener('DOMContentLoaded', function() {
    const alertConfig = {
        'alert-success': 3000,
        'alert-danger': 5000,
        'alert-warning': 4000,
        'alert-info': 3000
    };

    Object.keys(alertConfig).forEach(className => {
        const timeout = alertConfig[className];
        const alerts = document.querySelectorAll('.' + className);

        alerts.forEach(alert => {
            // Apply base styles for smooth transition
            alert.style.transition = 'opacity 0.4s ease-out, transform 0.4s ease-out';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            
            // Ensure parent has relative positioning if we want to absolute position the close button, 
            // but flex is safer. Let's just append a close button nicely.
            if (getComputedStyle(alert).position === 'static') {
                alert.style.position = 'relative';
            }
            alert.style.paddingRight = '2.5rem'; // Make room for close button

            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.innerHTML = '&times;';
            closeBtn.style.position = 'absolute';
            closeBtn.style.right = '0.75rem';
            closeBtn.style.top = '50%';
            closeBtn.style.transform = 'translateY(-50%)';
            closeBtn.style.fontSize = '1.25rem';
            closeBtn.style.lineHeight = '1';
            closeBtn.style.background = 'transparent';
            closeBtn.style.border = 'none';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.color = 'currentColor';
            closeBtn.style.opacity = '0.5';
            closeBtn.onmouseover = () => closeBtn.style.opacity = '1';
            closeBtn.onmouseout = () => closeBtn.style.opacity = '0.5';

            const dismissAlert = () => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 400); // Wait for transition
            };

            closeBtn.onclick = (e) => {
                e.preventDefault();
                dismissAlert();
            };

            alert.appendChild(closeBtn);

            // Fade in
            setTimeout(() => {
                alert.style.opacity = '1';
                alert.style.transform = 'translateY(0)';
            }, 10);

            // Auto-hide timeout
            setTimeout(() => {
                dismissAlert();
            }, timeout);
        });
    });
});
