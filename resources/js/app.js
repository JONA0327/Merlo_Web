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

// Trip search box on the homepage. Origen/Destino are custom-styled
// dropdowns — a native <select>'s open option list can't be restyled
// consistently across browsers, so we drive a styled <ul> menu instead
// and keep a hidden input in sync for the actual GET submission. Destino
// only ever offers the cities actually paired with the chosen Origen in
// an active LandingRoute — the company doesn't run routes to "all of
// Mexico". At least one date (viaje or regreso) is required to submit.
const tripSearchForm = document.getElementById('trip-search-form');
if (tripSearchForm && window.__TRIP_SEARCH__) {
    const pairs = window.__TRIP_SEARCH__.pairs ?? [];
    const dateInput = document.getElementById('trip-search-date');
    const returnDateInput = document.getElementById('trip-search-return-date');
    const errorEl = document.getElementById('trip-search-error');
    const cityStates = new Map();

    const closeCityMenu = (state) => {
        state.menu.classList.add('invisible', 'scale-95', 'opacity-0');
        state.trigger.setAttribute('aria-expanded', 'false');
        state.trigger.querySelector('[data-city-chevron]')?.classList.remove('rotate-180');
    };

    const closeAllCityMenus = () => cityStates.forEach(closeCityMenu);

    const openCityMenu = (state) => {
        if (state.trigger.disabled) return;
        closeAllCityMenus();
        state.menu.classList.remove('invisible', 'scale-95', 'opacity-0');
        state.trigger.setAttribute('aria-expanded', 'true');
        state.trigger.querySelector('[data-city-chevron]')?.classList.add('rotate-180');
    };

    const renderCityOptions = (role, cities, selected) => {
        const state = cityStates.get(role);
        if (!state) return;

        state.cities = cities;
        state.menu.innerHTML = '';

        cities.forEach((city) => {
            const li = document.createElement('li');
            const optionBtn = document.createElement('button');
            optionBtn.type = 'button';
            optionBtn.className = 'flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium transition hover:bg-[#8C1D2B]/8';

            const labelSpan = document.createElement('span');
            labelSpan.textContent = city;
            optionBtn.appendChild(labelSpan);

            const isSelected = city === selected;
            optionBtn.classList.toggle('text-[#8C1D2B]', isSelected);
            optionBtn.classList.toggle('font-bold', isSelected);
            optionBtn.classList.toggle('bg-[#8C1D2B]/10', isSelected);
            optionBtn.classList.toggle('text-[#2B1113]', !isSelected);
            if (isSelected) {
                optionBtn.insertAdjacentHTML('beforeend', '<svg class="h-4 w-4 shrink-0 text-[#8C1D2B]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>');
            }

            optionBtn.addEventListener('click', () => {
                state.input.value = city;
                state.label.textContent = city;
                state.label.classList.remove('text-[#2B1113]/40');
                state.label.classList.add('text-[#2B1113]');
                closeCityMenu(state);
                state.container.dispatchEvent(new CustomEvent('city:change', { detail: { value: city } }));
                renderCityOptions(role, cities, city);
            });

            li.appendChild(optionBtn);
            state.menu.appendChild(li);
        });

        const isValid = selected && cities.includes(selected);
        state.input.value = isValid ? selected : '';
        state.label.textContent = isValid ? selected : state.placeholder;
        state.label.classList.toggle('text-[#2B1113]/40', !isValid);
        state.label.classList.toggle('text-[#2B1113]', isValid);
        state.trigger.disabled = cities.length === 0;
    };

    document.querySelectorAll('[data-city-select]').forEach((container) => {
        const role = container.dataset.role;
        const state = {
            container,
            role,
            trigger: container.querySelector('[data-city-trigger]'),
            label: container.querySelector('[data-city-label]'),
            input: container.querySelector('[data-city-input]'),
            menu: container.querySelector('[data-city-menu]'),
            placeholder: container.dataset.placeholder,
            cities: [],
        };
        cityStates.set(role, state);

        state.trigger.addEventListener('click', () => {
            const isOpen = !state.menu.classList.contains('invisible');
            isOpen ? closeCityMenu(state) : openCityMenu(state);
        });
    });

    const updateDestinations = (preserveSelected) => {
        const toState = cityStates.get('to');
        const from = cityStates.get('from')?.input.value;
        const destinations = [...new Set(pairs.filter((p) => p.from === from).map((p) => p.to))];
        toState.placeholder = from ? 'Selecciona una ciudad' : 'Elige primero el origen';
        renderCityOptions('to', destinations, preserveSelected ? toState.input.value : null);
    };

    // Initial render: seed Origen with every registered city, Destino
    // with whatever's valid for the preselected Origen (a validation
    // failure re-renders the page with both preselected server-side).
    const fromContainer = document.querySelector('[data-city-select][data-role="from"]');
    const origins = [...new Set(pairs.map((p) => p.from))];
    renderCityOptions('from', origins, fromContainer?.dataset.initial || null);
    cityStates.get('to').input.value = document.querySelector('[data-city-select][data-role="to"]')?.dataset.initial || '';
    updateDestinations(true);

    fromContainer?.addEventListener('city:change', () => updateDestinations(false));

    document.addEventListener('click', (event) => {
        cityStates.forEach((state) => {
            if (!state.container.contains(event.target)) closeCityMenu(state);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAllCityMenus();
    });

    tripSearchForm.addEventListener('submit', (event) => {
        const from = cityStates.get('from').input.value;
        const to = cityStates.get('to').input.value;

        if (!from || !to) {
            event.preventDefault();
            closeAllCityMenus();
            errorEl?.classList.remove('hidden');
            errorEl.textContent = 'Selecciona un origen y un destino para buscar.';
            return;
        }

        if (!dateInput.value && !returnDateInput.value) {
            event.preventDefault();
            errorEl?.classList.remove('hidden');
            errorEl.textContent = 'Indica al menos una fecha (de viaje o de regreso) para buscar.';
            dateInput.focus();
            return;
        }

        errorEl?.classList.add('hidden');
    });
}
