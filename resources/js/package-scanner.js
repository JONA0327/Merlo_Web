import QrScanner from 'qr-scanner';

// Exposed as a plain global rather than an Alpine.data() component: the
// button that triggers scanning is only ever clicked well after every
// script has loaded, so there's no risk of an Alpine/script-order race —
// unlike registering an Alpine component, which has to exist before
// Alpine.start() walks the DOM.
window.QrScanner = QrScanner;
