import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';

// Import design system CSS variables
// import '../../design/design-system/tokens.css'; // TODO: Add this file or remove import

// Stimulus is already started in bootstrap.js, just import it here
import { app } from './bootstrap.js';

// Import Turbo for SPA-like navigation
import * as Turbo from '@hotwired/turbo';

// Configure Turbo
Turbo.session.drive = true;
// Use modern configuration for progress bar delay
if (Turbo.config && Turbo.config.progressBar) {
    Turbo.config.progressBar.delay = 100;
} else {
    // Fallback for older versions
    Turbo.setProgressBarDelay && Turbo.setProgressBarDelay(100);
}

// Global JavaScript utilities
import './js/utils/error-boundary.js';
import './js/utils/accessibility.js';
import './js/utils/theme.js';
import './js/utils/notifications.js';
import './js/utils/performance-monitor.js';
import './js/utils/loading-states.js';
import './js/utils/service-worker-manager.js';
import './js/utils/state-manager.js';

console.log('🚀 PrettiOps frontend loaded successfully!');