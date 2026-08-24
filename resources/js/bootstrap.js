import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Echo/Pusher-js (WebSocket client) is intentionally NOT imported here — this
// file loads on every page via app.js, and only the seat-picker page needs a
// live connection. It's imported directly from resources/js/seat-picker.js
// instead, so pages like the landing/admin/dashboard don't pay for it.
