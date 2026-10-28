import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Solo Inertia',
  description: 'Inertia.js 3 server adapter for PSR-7 and PSR-15: pages, partial reloads, deferred, merge and once props, flash data, history encryption and server-side rendering.',
  base: '/Inertia/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/Inertia/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#8b5cf6' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Solo Inertia' }],
    ['meta', { property: 'og:description', content: 'Inertia.js 3 server adapter for PSR-7 and PSR-15' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/installation' },
      { text: 'Features', link: '/features/responses' },
      { text: 'API', link: '/api/inertia' },
      { text: 'v2.0.0', link: '/guide/upgrading' },
      {
        text: 'Links',
        items: [
          { text: 'GitHub', link: 'https://github.com/SoloPHP/Inertia' },
          { text: 'Packagist', link: 'https://packagist.org/packages/solophp/inertia' },
          { text: 'Svelte skeleton', link: 'https://github.com/SoloPHP/Inertia-Svelte-Skeleton' },
          { text: 'The Inertia protocol', link: 'https://inertiajs.com/docs/v3/core-concepts/the-protocol' },
          { text: 'SoloPHP', link: 'https://github.com/SoloPHP' }
        ]
      }
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Quick Start', link: '/guide/quick-start' },
          { text: 'Upgrading from 1.x', link: '/guide/upgrading' }
        ]
      },
      {
        text: 'Features',
        items: [
          { text: 'Responses', link: '/features/responses' },
          { text: 'Root Template', link: '/features/root-template' },
          { text: 'Shared Data', link: '/features/shared-data' },
          { text: 'Partial Reloads', link: '/features/partial-reloads' },
          { text: 'Deferred Props', link: '/features/deferred-props' },
          { text: 'Merging Props', link: '/features/merging-props' },
          { text: 'Once Props', link: '/features/once-props' },
          { text: 'Infinite Scroll', link: '/features/infinite-scroll' },
          { text: 'Redirects', link: '/features/redirects' },
          { text: 'Validation', link: '/features/validation' },
          { text: 'Flash Data', link: '/features/flash-data' },
          { text: 'History Encryption', link: '/features/history-encryption' },
          { text: 'Asset Versioning', link: '/features/asset-versioning' },
          { text: 'Server-Side Rendering', link: '/features/ssr' },
          { text: 'Testing', link: '/features/testing' }
        ]
      },
      {
        text: 'API Reference',
        items: [
          { text: 'Inertia', link: '/api/inertia' },
          { text: 'Prop & ScrollMetadata', link: '/api/prop' },
          { text: 'Middleware', link: '/api/middleware' },
          { text: 'Flash & Stores', link: '/api/flash' },
          { text: 'Root View & SSR', link: '/api/root-view' },
          { text: 'Header', link: '/api/header' }
        ]
      }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/SoloPHP/Inertia' }
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 SoloPHP'
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/SoloPHP/Inertia/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    }
  }
})
