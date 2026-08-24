import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.querySelectorAll('.js-carousel').forEach((carousel) => {
    const slides = Array.from(carousel.querySelectorAll('.js-carousel-slide'));
    const dots = Array.from(carousel.querySelectorAll('.js-carousel-dot'));
    const prevBtn = carousel.querySelector('.js-carousel-prev');
    const nextBtn = carousel.querySelector('.js-carousel-next');
    const intervalMs = 5000;
    let current = 0;
    let timer = null;

    const goTo = (index) => {
        current = (index + slides.length) % slides.length;

        slides.forEach((slide, i) => {
            slide.classList.toggle('opacity-100', i === current);
            slide.classList.toggle('opacity-0', i !== current);
        });

        dots.forEach((dot, i) => {
            dot.classList.toggle('w-6', i === current);
            dot.classList.toggle('bg-white', i === current);
            dot.classList.toggle('w-2', i !== current);
            dot.classList.toggle('bg-white/40', i !== current);
        });
    };

    const restart = () => {
        clearInterval(timer);
        timer = setInterval(() => goTo(current + 1), intervalMs);
    };

    prevBtn?.addEventListener('click', () => { goTo(current - 1); restart(); });
    nextBtn?.addEventListener('click', () => { goTo(current + 1); restart(); });
    dots.forEach((dot) => {
        dot.addEventListener('click', () => { goTo(Number(dot.dataset.slide)); restart(); });
    });

    if (slides.length > 1) {
        restart();
    }
});

const openModal = (name) => {
    const modal = document.getElementById(`auth-modal-${name}`);
    modal?.classList.remove('hidden');
    modal?.classList.add('flex');
};

const closeModal = (modal) => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
};

document.querySelectorAll('.js-modal-trigger').forEach((trigger) => {
    trigger.addEventListener('click', () => openModal(trigger.dataset.authModal));
});

document.querySelectorAll('.js-modal').forEach((modal) => {
    const close = () => closeModal(modal);

    modal.querySelectorAll('.js-modal-close').forEach((btn) => btn.addEventListener('click', close));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });

    modal.querySelectorAll('.js-modal-switch').forEach((btn) => {
        btn.addEventListener('click', () => {
            close();
            openModal(btn.dataset.authModal);
        });
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('.js-modal').forEach(closeModal);
    }
});
