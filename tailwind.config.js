/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './assets/**/*.js',
    './templates/**/*.html.twig',
    '../backend/src/**/*.php'
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // PrettiOps Brand Colors - Enhanced with Tiptap gradients
        purple: {
          primary: '#6f00ff',
          hover: '#5c00d9', 
          light: '#f3ebff',
          dark: '#4a0099',
          50: '#f8f4ff',
          100: '#f3ebff',
          200: '#e4d1ff',
          300: '#d1b1ff',
          400: '#b885ff',
          500: '#9f59ff',
          600: '#8a2be2',
          700: '#6f00ff',
          800: '#5c00d9',
          900: '#4a0099',
        },
        // Azure/Blue complement for gradients
        azure: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
        // Custom grays matching design system
        gray: {
          50: '#fafafa',
          100: '#f5f4f4', 
          200: '#e7e5e4',
          300: '#d6d3d1',
          400: '#a8a29e',
          500: '#78716c',
          600: '#57534e',
          700: '#44403c',
          800: '#292524',
          900: '#1c1917',
        },
        // Semantic colors
        success: {
          DEFAULT: '#10b981',
          light: '#d1fae5',
          dark: '#065f46',
        },
        warning: {
          DEFAULT: '#f59e0b',
          light: '#fef3c7', 
          dark: '#92400e',
        },
        error: {
          DEFAULT: '#ef4444',
          light: '#fef2f2',
          dark: '#991b1b',
        },
        info: {
          DEFAULT: '#3b82f6',
          light: '#eff6ff',
          dark: '#1e40af',
        },
        // Dark mode colors
        dark: {
          bg: '#0f172a',
          surface: '#1e293b',
          border: '#334155', 
          text: '#cbd5e1',
          'text-muted': '#94a3b8',
        }
      },
      backgroundImage: {
        // Tiptap-inspired gradient patterns
        'gradient-noise-purple-azure': 'linear-gradient(135deg, #6f00ff 0%, #8a2be2 25%, #4169e1 75%, #1e90ff 100%)',
        'gradient-noise-dark': 'linear-gradient(135deg, #0f0f23 0%, #1a1a2e 25%, #16213e 75%, #0f3460 100%)',
        'gradient-purple-azure': 'linear-gradient(135deg, #6f00ff 0%, #8a2be2 50%, #4169e1 100%)',
        'gradient-purple-light': 'linear-gradient(135deg, #f8f4ff 0%, #f3ebff 50%, #e4d1ff 100%)',
        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
        'gradient-conic': 'conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))',
        // Noise texture overlays
        'noise-light': 'radial-gradient(circle at 25% 25%, rgba(111, 0, 255, 0.05) 0%, transparent 50%), radial-gradient(circle at 75% 75%, rgba(65, 105, 225, 0.05) 0%, transparent 50%)',
        'noise-dark': 'radial-gradient(circle at 25% 25%, rgba(111, 0, 255, 0.1) 0%, transparent 50%), radial-gradient(circle at 75% 75%, rgba(65, 105, 225, 0.1) 0%, transparent 50%)',
      },
      fontFamily: {
        display: ['DM Sans', 'system-ui', '-apple-system', 'sans-serif'],
        body: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
        mono: ['Fira Code', 'SF Mono', 'Monaco', 'Cascadia Code', 'Roboto Mono', 'monospace'],
      },
      fontSize: {
        xs: ['0.75rem', { lineHeight: '1rem' }],
        sm: ['0.875rem', { lineHeight: '1.25rem' }],
        base: ['1rem', { lineHeight: '1.5rem' }],
        lg: ['1.125rem', { lineHeight: '1.75rem' }],
        xl: ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
        '5xl': ['3rem', { lineHeight: '1' }],
        '6xl': ['3.75rem', { lineHeight: '1' }],
        '7xl': ['4.5rem', { lineHeight: '1' }],
        '8xl': ['6rem', { lineHeight: '1' }],
        // Tiptap typography scale
        'heading-xxlarge': ['4.5rem', { lineHeight: '1.1', fontWeight: '700' }],
        'heading-xlarge': ['3.75rem', { lineHeight: '1.15', fontWeight: '700' }],
        'heading-large': ['3rem', { lineHeight: '1.2', fontWeight: '600' }],
        'heading-medium': ['2.25rem', { lineHeight: '1.25', fontWeight: '600' }],
        'heading-small': ['1.875rem', { lineHeight: '1.3', fontWeight: '600' }],
        'heading-x-small': ['1.5rem', { lineHeight: '1.35', fontWeight: '500' }],
      },
      spacing: {
        '0.5': '0.125rem',  // 2px
        '1.5': '0.375rem',  // 6px
        '2.5': '0.625rem',  // 10px
        '3.5': '0.875rem',  // 14px
        '18': '4.5rem',     // 72px
        '88': '22rem',      // 352px
      },
      borderRadius: {
        'sm': '0.25rem',    // 4px
        'md': '0.5rem',     // 8px (default in design system)
        'lg': '0.75rem',    // 12px
        'xl': '1rem',       // 16px
        '2xl': '1.5rem',    // 24px
        '3xl': '2rem',      // 32px
      },
      boxShadow: {
        'sm': '0 1px 2px rgba(0, 0, 0, 0.05)',
        'md': '0 4px 6px rgba(0, 0, 0, 0.07)', 
        'lg': '0 10px 15px rgba(0, 0, 0, 0.1)',
        'xl': '0 20px 25px rgba(0, 0, 0, 0.1)',
        '2xl': '0 25px 50px rgba(0, 0, 0, 0.25)',
        'purple': '0 10px 25px rgba(111, 0, 255, 0.15)',
        'success': '0 10px 25px rgba(16, 185, 129, 0.15)',
        'warning': '0 10px 25px rgba(245, 158, 11, 0.15)',
        'error': '0 10px 25px rgba(239, 68, 68, 0.15)',
        'inner': 'inset 0 2px 4px rgba(0, 0, 0, 0.06)',
        // Tiptap-inspired shadows
        'glass': '0 8px 32px rgba(31, 38, 135, 0.15)',
        'glass-strong': '0 8px 32px rgba(31, 38, 135, 0.3)',
        'glow-purple': '0 0 20px rgba(111, 0, 255, 0.3)',
        'glow-purple-strong': '0 0 40px rgba(111, 0, 255, 0.4)',
        'feature-hover': '0 20px 60px rgba(111, 0, 255, 0.15)',
      },
      backdropBlur: {
        'glass': 'blur(10px)',
        'glass-strong': 'blur(20px)',
      },
      transitionDuration: {
        '150': '150ms',
        '200': '200ms',
        '300': '300ms', 
        '500': '500ms',
      },
      transitionTimingFunction: {
        'bounce': 'cubic-bezier(0.68, -0.6, 0.32, 1.6)',
        'back': 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
      },
      animation: {
        'spin-slow': 'spin 2s linear infinite',
        'pulse-slow': 'pulse 3s infinite',
        'bounce-gentle': 'bounce 2s infinite',
        // Tiptap-inspired animations
        'gradient-shift': 'gradient-shift 6s ease-in-out infinite',
        'float': 'float 6s ease-in-out infinite',
        'glow': 'glow 2s ease-in-out infinite alternate',
        'slide-up': 'slide-up 0.6s ease-out',
        'slide-down': 'slide-down 0.6s ease-out',
        'scale-in': 'scale-in 0.3s ease-out',
      },
      keyframes: {
        'gradient-shift': {
          '0%, 100%': {
            'background-position': '0% 50%'
          },
          '50%': {
            'background-position': '100% 50%'
          }
        },
        'float': {
          '0%, 100%': {
            'transform': 'translateY(0px)'
          },
          '50%': {
            'transform': 'translateY(-10px)'
          }
        },
        'glow': {
          '0%': {
            'box-shadow': '0 0 20px rgba(111, 0, 255, 0.3)'
          },
          '100%': {
            'box-shadow': '0 0 30px rgba(111, 0, 255, 0.5), 0 0 40px rgba(111, 0, 255, 0.2)'
          }
        },
        'slide-up': {
          '0%': {
            'transform': 'translateY(100%)',
            'opacity': '0'
          },
          '100%': {
            'transform': 'translateY(0)',
            'opacity': '1'
          }
        },
        'slide-down': {
          '0%': {
            'transform': 'translateY(-100%)',
            'opacity': '0'
          },
          '100%': {
            'transform': 'translateY(0)',
            'opacity': '1'
          }
        },
        'scale-in': {
          '0%': {
            'transform': 'scale(0.9)',
            'opacity': '0'
          },
          '100%': {
            'transform': 'scale(1)',
            'opacity': '1'
          }
        }
      },
      zIndex: {
        '60': '60',
        '70': '70',
        '80': '80',
        '90': '90',
        '100': '100',
      }
    },
  },
  plugins: [
    // Add form styles plugin for consistent form styling
    require('@tailwindcss/forms'),
    // Add typography plugin for rich text content
    require('@tailwindcss/typography'),
    // Enhanced plugin for glass morphism and Tiptap-inspired utilities
    function({ addUtilities, addComponents, theme }) {
      const newUtilities = {
        '.glass': {
          background: 'rgba(255, 255, 255, 0.85)',
          backdropFilter: 'blur(10px)',
          borderColor: 'rgba(255, 255, 255, 0.2)',
        },
        '.glass-dark': {
          background: 'rgba(15, 23, 42, 0.85)',
          backdropFilter: 'blur(10px)', 
          borderColor: 'rgba(255, 255, 255, 0.1)',
        },
        '.glass-card': {
          background: 'rgba(255, 255, 255, 0.05)',
          backdropFilter: 'blur(20px)',
          border: '1px solid rgba(255, 255, 255, 0.1)',
          borderRadius: '24px',
        },
        '.glass-purple': {
          background: 'rgba(111, 0, 255, 0.1)',
          backdropFilter: 'blur(20px)',
          border: '1px solid rgba(111, 0, 255, 0.2)',
        },
        '.text-gradient-primary': {
          background: 'linear-gradient(135deg, #6f00ff 0%, #8a2be2 50%, #4169e1 100%)',
          '-webkit-background-clip': 'text',
          'background-clip': 'text',
          '-webkit-text-fill-color': 'transparent',
        },
        '.gradient-noise-overlay': {
          position: 'relative',
        },
        '.gradient-noise-overlay::before': {
          content: '""',
          position: 'absolute',
          top: '0',
          left: '0',
          right: '0',
          bottom: '0',
          backgroundImage: theme('backgroundImage.noise-light'),
          pointerEvents: 'none',
          opacity: '0.5',
        }
      }

      const components = {
        '.tt-button': {
          display: 'inline-flex',
          alignItems: 'center',
          justifyContent: 'center',
          gap: '0.5rem',
          padding: '0.75rem 1.5rem',
          fontSize: '0.875rem',
          fontWeight: '500',
          borderRadius: '0.75rem',
          transition: 'all 0.2s ease',
          cursor: 'pointer',
          border: 'none',
          textDecoration: 'none',
          position: 'relative',
          overflow: 'hidden',
        },
        '.tt-button.btn-primary': {
          background: 'linear-gradient(135deg, #6f00ff 0%, #8a2be2 100%)',
          color: '#ffffff',
          boxShadow: '0 4px 14px rgba(111, 0, 255, 0.2)',
        },
        '.tt-button.btn-primary:hover': {
          transform: 'translateY(-2px)',
          boxShadow: '0 8px 25px rgba(111, 0, 255, 0.3)',
        },
        '.tt-button.btn-secondary': {
          background: 'rgba(255, 255, 255, 0.1)',
          backdropFilter: 'blur(10px)',
          color: theme('colors.gray.700'),
          border: '1px solid rgba(255, 255, 255, 0.2)',
        },
        '.tt-feature-card': {
          background: 'rgba(255, 255, 255, 0.9)',
          backdropFilter: 'blur(10px)',
          borderRadius: '1.5rem',
          padding: '2rem',
          border: '1px solid rgba(255, 255, 255, 0.2)',
          boxShadow: '0 8px 32px rgba(0, 0, 0, 0.1)',
          transition: 'all 0.3s ease',
        },
        '.tt-feature-card:hover': {
          transform: 'translateY(-8px)',
          boxShadow: '0 20px 60px rgba(111, 0, 255, 0.15)',
          borderColor: 'rgba(111, 0, 255, 0.3)',
        }
      }

      addUtilities(newUtilities)
      addComponents(components)
    }
  ],
}