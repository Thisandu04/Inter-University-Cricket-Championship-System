document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('navToggle');
    const nav = document.getElementById('siteNav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            const isOpen = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                nav.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    const countdownEl = document.getElementById('spotlightCountdown');
    if (countdownEl) {
        const target = new Date(countdownEl.dataset.target).getTime();

        function updateCountdown() {
            const now = Date.now();
            const diff = target - now;

            if (diff <= 0) {
                countdownEl.textContent = 'Starting soon';
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
            const minutes = Math.floor((diff / (1000 * 60)) % 60);

            if (days > 0) {
                countdownEl.textContent = `${days}d ${hours}h`;
            } else if (hours > 0) {
                countdownEl.textContent = `${hours}h ${minutes}m`;
            } else {
                countdownEl.textContent = `${minutes}m`;
            }
        }

        updateCountdown();
        setInterval(updateCountdown, 60000);
    }

    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('iuct-theme', next);
        });
    }

    const staggerTargets = document.querySelectorAll('.data-table tbody tr, .card-grid .card');
    staggerTargets.forEach(function (el, i) {
        el.classList.add('entrance');
        el.style.animationDelay = Math.min(i * 40, 400) + 'ms';
    });

    const banners = document.querySelectorAll('.error-msg, .success-msg');
    if (banners.length > 0) {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            container.setAttribute('role', 'status');
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        banners.forEach(function (banner) {
            const isError = banner.classList.contains('error-msg');
            const toast = document.createElement('div');
            toast.className = 'toast ' + (isError ? 'toast--error' : 'toast--success');
            toast.setAttribute('role', isError ? 'alert' : 'status');

            const icon = document.createElement('i');
            icon.className = 'toast-icon ' + (isError ? 'ti ti-alert-circle' : 'ti ti-check');
            icon.setAttribute('aria-hidden', 'true');

            const body = document.createElement('div');
            body.className = 'toast-body';
            body.textContent = banner.textContent;

            const closeBtn = document.createElement('button');
            closeBtn.className = 'toast-close';
            closeBtn.setAttribute('aria-label', 'Dismiss notification');
            closeBtn.textContent = '\u00D7';
            closeBtn.addEventListener('click', function () { removeToast(toast); });

            toast.appendChild(icon);
            toast.appendChild(body);
            toast.appendChild(closeBtn);
            container.appendChild(toast);

            banner.remove();

            if (!isError) {
                setTimeout(function () { removeToast(toast); }, 4500);
            }
        });
    }

    function removeToast(toast) {
        toast.classList.add('toast--leaving');
        setTimeout(function () { toast.remove(); }, 200);
    }
});