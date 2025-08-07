/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
    "./vendor/livewire/flux/stubs/**/*.blade.php",
    "./vendor/livewire/flux-pro/stubs/**/*.blade.php",
    "./app/Toasts/**/*.php",
    "./config/toasts.php",
  ],

  safelist: [
    // Toast positioning classes
    'fixed', 'top-4', 'right-4', 'left-4', 'bottom-4', 'left-1/2', '-translate-x-1/2', 'z-50', 'max-w-sm',
    'flex-col', 'flex-col-reverse', 'items-start', 'items-end', 'items-center',
    
    // Toast type styling classes using custom status colors
    'bg-status-success-50', 'dark:bg-status-success-700', 'dark:bg-opacity-30', 'border-status-success-100', 'dark:border-status-success-600',
    'text-status-success-700', 'dark:text-status-success-100', 'text-status-success-500',
    'hover:bg-status-success-100', 'dark:hover:bg-status-success-600',
    
    'bg-status-error-50', 'dark:bg-status-error-700', 'border-status-error-100', 'dark:border-status-error-600',
    'text-status-error-700', 'dark:text-status-error-100', 'text-status-error-500',
    'hover:bg-status-error-100', 'dark:hover:bg-status-error-600',
    
    'bg-status-warning-50', 'dark:bg-status-warning-700', 'border-status-warning-100', 'dark:border-status-warning-600',
    'text-status-warning-700', 'dark:text-status-warning-100', 'text-status-warning-500',
    'hover:bg-status-warning-100', 'dark:hover:bg-status-warning-600',
    
    'bg-status-info-50', 'dark:bg-status-info-700', 'border-status-info-100', 'dark:border-status-info-600',
    'text-status-info-700', 'dark:text-status-info-100', 'text-status-info-500',
    'hover:bg-status-info-100', 'dark:hover:bg-status-info-600',
    
    // Animation classes
    'opacity-0', 'opacity-100', 'transform', 'translate-x-full', 'translate-x-0',
    'scale-95', 'scale-100', 'transition-all', 'duration-300', 'duration-200',
    'ease-in-out', 'ease-out',
  ],
  
  darkMode: 'class', // Enable class-based dark mode
  
  theme: {
    extend: {
      // Enterprise-grade color palette
      colors: {
        // Primary neutral palette (keeping your zinc preference)
        zinc: {
          25: '#fcfcfd',
          50: '#fafafa',
          100: '#f5f5f5', 
          150: '#f0f0f0',
          200: '#e5e5e5',
          250: '#dedede',
          300: '#d4d4d4',
          350: '#c4c4c4',
          400: '#a3a3a3',
          450: '#8a8a8a',
          500: '#737373',
          550: '#666666',
          600: '#525252',
          650: '#474747',
          700: '#404040',
          750: '#363636',
          800: '#262626',
          850: '#1f1f1f',
          900: '#171717',
          925: '#141414',
          950: '#0a0a0a',
        },
        
        // Semantic status colors optimized for data interfaces
        status: {
          success: {
            50: '#f0fdf4',
            100: '#dcfce7',
            200: '#bbf7d0',
            300: '#86efac', 
            400: '#4ade80',
            500: '#22c55e',
            600: '#16a34a',
            700: '#15803d',
            800: '#166534',
            900: '#14532d',
          },
          warning: {
            50: '#fffbeb',
            100: '#fef3c7',
            200: '#fde68a',
            300: '#fcd34d',
            400: '#fbbf24',
            500: '#f59e0b',
            600: '#d97706', 
            700: '#b45309',
            800: '#92400e',
            900: '#78350f',
          },
          error: {
            50: '#fef2f2',
            100: '#fee2e2',
            200: '#fecaca',
            300: '#fca5a5',
            400: '#f87171',
            500: '#ef4444',
            600: '#dc2626',
            700: '#b91c1c',
            800: '#991b1b',
            900: '#7f1d1d',
          },
          info: {
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
        },
        
        // Interactive states
        interactive: {
          hover: {
            light: '#f8fafc',
            dark: '#1e293b',
          },
          selected: {
            light: '#eff6ff', 
            dark: '#1e3a8a',
          },
          pressed: {
            light: '#e0f2fe',
            dark: '#164e63',
          }
        }
      },

      // Typography scale for data interfaces
      fontSize: {
        '2xs': ['0.6875rem', { lineHeight: '1rem' }],     // 11px
        'xs': ['0.75rem', { lineHeight: '1.125rem' }],    // 12px
        'sm': ['0.875rem', { lineHeight: '1.375rem' }],   // 14px
        'base': ['1rem', { lineHeight: '1.5rem' }],       // 16px
        'lg': ['1.125rem', { lineHeight: '1.75rem' }],    // 18px
        'xl': ['1.25rem', { lineHeight: '1.875rem' }],    // 20px
      },

      // Spacing scale optimized for data density
      spacing: {
        '0.5': '0.125rem',  // 2px
        '1.5': '0.375rem',  // 6px
        '2.5': '0.625rem',  // 10px
        '3.5': '0.875rem',  // 14px
        '4.5': '1.125rem',  // 18px
        '5.5': '1.375rem',  // 22px
        '6.5': '1.625rem',  // 26px
        '7.5': '1.875rem',  // 30px
        '18': '4.5rem',     // 72px
        '22': '5.5rem',     // 88px
      },

      // Border radius for modern data interfaces
      borderRadius: {
        'xs': '0.125rem',   // 2px
        'sm': '0.25rem',    // 4px  
        'md': '0.375rem',   // 6px
        'lg': '0.5rem',     // 8px
        'xl': '0.75rem',    // 12px
        '2xl': '1rem',      // 16px
      },

      // Enhanced box shadows for premium interfaces
      boxShadow: {
        'xs': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
        'sm': '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px 0 rgb(0 0 0 / 0.06)',
        'md': '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
        'lg': '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
        'xl': '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
        '2xl': '0 25px 50px -12px rgb(0 0 0 / 0.25)',
        'inner': 'inset 0 2px 4px 0 rgb(0 0 0 / 0.06)',
        // Premium floating shadows
        'floating': '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1), 0 0 0 1px rgb(0 0 0 / 0.05)',
        'floating-lg': '0 32px 64px -12px rgb(0 0 0 / 0.25), 0 0 0 1px rgb(0 0 0 / 0.05)',
        // Subtle row hover shadows
        'row': '0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px 0 rgb(0 0 0 / 0.04)',
        'row-hover': '0 4px 6px -1px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.05)',
      },

      // Animation timing for micro-interactions
      transitionDuration: {
        '75': '75ms',
        '100': '100ms', 
        '150': '150ms',
        '200': '200ms',
        '250': '250ms',
        '300': '300ms',
        '400': '400ms',
      },

      // Enhanced backdrop blur utilities
      backdropBlur: {
        'xs': '2px',
        'sm': '4px',
        'md': '8px',
        'lg': '12px',
        'xl': '16px',
        '2xl': '24px',
        '3xl': '32px',
      },

      // Custom animations for premium UI
      animation: {
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'fade-in': 'fadeIn 0.3s ease-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'scale-in': 'scaleIn 0.2s ease-out',
        'tab-content': 'tabContent 0.25s ease-out',
        'tab-border': 'tabBorder 0.2s ease-out',
      },

      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        scaleIn: {
          '0%': { transform: 'scale(0.95)', opacity: '0' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
        tabContent: {
          '0%': { 
            opacity: '0', 
            transform: 'translateY(4px)',
          },
          '100%': { 
            opacity: '1', 
            transform: 'translateY(0)',
          },
        },
        tabBorder: {
          '0%': {
            transform: 'scaleX(0)',
            transformOrigin: 'left',
          },
          '100%': {
            transform: 'scaleX(1)',
            transformOrigin: 'left',
          },
        },
      },
      
      // Font family (keeping your existing choice)
      fontFamily: {
        sans: ['Instrument Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  
  plugins: [
    // Add form plugin for better form styling
    require('@tailwindcss/forms'),
  ],
}