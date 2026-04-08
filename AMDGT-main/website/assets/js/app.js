// AMDGT App JS - Utility functions
console.log('🧬 AMDGT Drug-Disease Prediction System');

// Active nav link highlight
document.addEventListener('DOMContentLoaded', function() {
    const current = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === current || (current === '' && href === 'index.php')) {
            link.style.color = 'var(--accent-light)';
            link.style.background = 'rgba(99, 102, 241, 0.1)';
        }
    });
});
