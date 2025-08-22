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

// Start the Stimulus application
import { startStimulusApp } from '@symfony/stimulus-bridge';

export const app = startStimulusApp(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.(j|t)sx?$/
));

// Import Turbo for SPA-like navigation
import * as Turbo from '@hotwired/turbo';

// Configure Turbo
Turbo.session.drive = true;
Turbo.setProgressBarDelay(100);

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