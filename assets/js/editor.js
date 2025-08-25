/*
 * Editor Entry Point for PrettiOps
 * 
 * This file is loaded by webpack for the editor page.
 * The actual editor functionality is handled by the Stimulus controller.
 */

// Import editor styles
import '../styles/editor-new.scss';

// Import theme utilities
import './utils/theme.js';

// Import Highlight.js styles for email preview
import 'highlight.js/styles/github.css';
import 'highlight.js/styles/github-dark.css';

console.log('🎨 PrettiOps Editor liquid glass styles loaded');